<?php

namespace App\Services\EditorialExtraction;

use DomainException;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;

class PrepareDocument
{
    public const VERSION = 'editorial-dom-v2';

    public function prepare(string $body, string $mime, string $baseUrl): PreparedDocument
    {
        if (strlen($body) > 262144 || str_contains($body, "\0") || ! mb_check_encoding($body, 'UTF-8')) {
            throw new DomainException('editorial_invalid_text');
        }
        if (! in_array($mime, ['text/html', 'text/plain'], true)) {
            throw new DomainException('editorial_unsupported_mime');
        }
        $units = [];
        if ($mime === 'text/plain') {
            $contexts = [];
            $inChain = false;
            $exhausted = false;
            $overflow = false;
            foreach (explode("\n", $body) as $index => $line) {
                $normalized = trim(preg_replace('/\s+/u', ' ', $line));
                if ($normalized === '') {
                    $contexts = [];
                    $inChain = false;
                    $exhausted = false;
                    $overflow = false;

                    continue;
                }
                $label = strtolower(trim($normalized, " .:\t\r"));
                $article = isset(EditorialVocabulary::ARTICLES[$label]);
                $qualifier = $this->isQualifier($normalized, false);
                if (str_ends_with($normalized, ':') || $article || $qualifier) {
                    if (! $inChain) {
                        $contexts = [];
                        $exhausted = false;
                        $overflow = false;
                    }
                    // A second article/condition in a chain is ambiguous, never silently replaced.
                    if (count($contexts) >= 8 || strlen($normalized) > 128) {
                        $overflow = true;
                    } else {
                        $contexts[] = ['kind' => $qualifier ? 'qualifier' : 'heading', 'text' => $normalized, 'locator' => 'line:'.($index + 1)];
                    }
                    $inChain = true;

                    continue;
                }
                $ambiguous = $exhausted || $overflow;
                foreach ($contexts as $context) {
                    $ambiguous = $ambiguous || strlen($context['text']) > 128;
                }
                $this->append($units, $line, 'line:'.($index + 1), '', null, array_slice($contexts, 0, 8), $ambiguous);
                // Plain text has no reliable outline: a label chain scopes one following line only.
                // Further prose abstains until a blank boundary or a new explicit label chain.
                $exhausted = $exhausted || $overflow || $contexts !== [];
                $inChain = false;
            }
        } else {
            $previous = libxml_use_internal_errors(true);
            try {
                $dom = new DOMDocument;
                // NONET, no DTD loading, entity expansion, XInclude or resource resolution.
                if (! $dom->loadHTML('<?xml encoding="UTF-8">'.$body, LIBXML_NONET | LIBXML_NOERROR | LIBXML_NOWARNING)) {
                    throw new DomainException('editorial_invalid_html');
                }
                $xpath = new DOMXPath($dom);
                if ($xpath->query('//*')->length > 4096) {
                    throw new DomainException('editorial_document_limit');
                }
                // Locators are captured before filtering so they resolve in the original DOM.
                $paths = new \SplObjectStorage;
                foreach ($xpath->query('//*') as $node) {
                    $paths[$node] = $node->getNodePath();
                }
                foreach (iterator_to_array($xpath->query('//script|//style|//noscript|//nav|//header|//footer|//aside|//template|//del|//s|//strike|//*[@hidden or @aria-hidden="true"]')) as $node) {
                    $node->parentNode?->removeChild($node);
                }
                foreach (iterator_to_array($xpath->query('//*[@style]')) as $node) {
                    if (preg_match('/(?:display\s*:\s*none|visibility\s*:\s*hidden)/i', $node->getAttribute('style'))) {
                        $node->parentNode?->removeChild($node);
                    }
                }
                $headingContexts = $this->headingContexts($xpath);
                foreach ($xpath->query('//p|//li|//tr|//dd|//a[@href]') as $node) {
                    if ($node->tagName !== 'a' && $xpath->query('.//p|.//li|.//tr|.//dd', $node)->length > 0) {
                        continue; // No duplicated container text or concatenated unrelated blocks.
                    }
                    $text = $node->textContent;
                    if ($node->tagName === 'tr') {
                        $cells = $xpath->query('./th|./td', $node);
                        if ($cells->length !== 2) {
                            continue;
                        }
                        $text = $cells->item(0)->textContent.': '.$cells->item(1)->textContent;
                    } elseif ($node->tagName === 'dd') {
                        $label = $this->previousElement($node);
                        if ($label?->tagName !== 'dt') {
                            continue;
                        }
                        $text = $label->textContent.': '.$text;
                    }
                    $heading = $this->previousElement($node);
                    if ($node->tagName === 'li') {
                        $heading = $this->previousElement($node->parentNode);
                    }
                    $headingText = $heading && preg_match('/^h[1-6]$/', $heading->tagName) ? $heading->textContent : '';
                    $locator = $paths[$node] ?? '';
                    [$contexts, $ambiguous] = $this->contexts($node, $xpath, $paths, $headingContexts[$node] ?? []);
                    $this->append($units, $text, $locator, $headingText, $node->tagName === 'a' ? $node->getAttribute('href') : null, $contexts, $ambiguous);
                }
            } finally {
                libxml_clear_errors();
                libxml_use_internal_errors($previous);
            }
        }

        return new PreparedDocument($baseUrl, $units);
    }

    private function previousElement(DOMNode $node): ?DOMElement
    {
        $previous = $node->previousSibling;
        while ($previous && ! $previous instanceof DOMElement) {
            if (trim($previous->textContent) !== '') {
                return null;
            }
            $previous = $previous->previousSibling;
        }

        return $previous;
    }

    private function contexts(DOMElement $node, DOMXPath $xpath, \SplObjectStorage $paths, array $headings): array
    {
        $contexts = [];
        $ambiguous = false;
        $add = function (DOMElement $element, string $kind) use (&$contexts, &$ambiguous, $paths): void {
            $text = trim(preg_replace('/\s+/u', ' ', $element->textContent));
            $locator = $paths[$element] ?? '';
            if ($text === '' || strlen($text) > 128 || $locator === '' || strlen($locator) > 512) {
                $ambiguous = true;

                return;
            }
            $contexts[$locator] = ['kind' => $kind, 'text' => $text, 'locator' => $locator];
            if (count($contexts) > 8) {
                $ambiguous = true;
            }
        };
        foreach ($headings as $entry) {
            $add($entry['node'], 'heading');
            if ($entry['qualifier'] !== null) {
                $add($entry['qualifier'], 'qualifier');
            }
        }
        $cursor = $node;
        for ($depth = 0; $cursor instanceof DOMElement && ! in_array($cursor->tagName, ['html', 'head'], true); $depth++) {
            if ($depth >= 8) {
                $ambiguous = true;
                break;
            }
            if ($cursor->tagName === 'table') {
                foreach ($xpath->query('./caption', $cursor) as $caption) {
                    $add($caption, 'caption');
                }
                $row = $xpath->query('./tr[1]|./thead/tr[1]|./tbody/tr[1]', $cursor)->item(0);
                if ($row && $xpath->query('./td', $row)->length === 0) {
                    foreach ($xpath->query('./th', $row) as $cell) {
                        $add($cell, 'table_header');
                    }
                }
            }
            if ($cursor->tagName === 'figure') {
                foreach ($xpath->query('./figcaption', $cursor) as $caption) {
                    $add($caption, 'caption');
                }
            }
            // Preserve only immediately associated, explicitly qualifier-shaped prose.
            $previous = $this->previousElement($cursor);
            if ($previous && in_array($previous->tagName, ['p', 'div'], true)
                && $this->isQualifier($previous->textContent)) {
                $add($previous, 'qualifier');
            }
            $cursor = $cursor->parentNode;
        }

        return [array_values(array_slice($contexts, 0, 8, true)), $ambiguous];
    }

    /** Document-order h1..h6 hierarchy; div wrappers never reset heading levels. */
    private function headingContexts(DOMXPath $xpath): \SplObjectStorage
    {
        $result = new \SplObjectStorage;
        $stack = [];
        foreach ($xpath->query('//*') as $node) {
            // Headings introduced inside explicit sections cannot escape their container.
            foreach ($stack as $level => $entry) {
                if ($entry['owner'] !== null && ! $this->within($node, $entry['owner'])) {
                    foreach (array_keys($stack) as $active) {
                        if ($active >= $level) {
                            unset($stack[$active]);
                        }
                    }
                    break;
                }
            }
            if (preg_match('/^h([1-6])$/', $node->tagName, $match)) {
                $level = (int) $match[1];
                foreach (array_keys($stack) as $active) {
                    if ($active >= $level) {
                        unset($stack[$active]);
                    }
                }
                $owner = $node->parentNode;
                while ($owner instanceof DOMElement && ! in_array($owner->tagName, ['section', 'article', 'main'], true)) {
                    $owner = $owner->parentNode;
                }
                $previous = $this->previousElement($node);
                $qualifier = $previous && in_array($previous->tagName, ['p', 'div'], true)
                    && $this->isQualifier($previous->textContent) ? $previous : null;
                $stack[$level] = ['node' => $node, 'owner' => $owner instanceof DOMElement ? $owner : null, 'qualifier' => $qualifier];
            }
            $result[$node] = array_values($stack);
        }

        return $result;
    }

    private function within(DOMNode $node, DOMElement $owner): bool
    {
        for ($cursor = $node; $cursor !== null; $cursor = $cursor->parentNode) {
            if ($cursor === $owner) {
                return true;
            }
        }

        return false;
    }

    private function isQualifier(string $text, bool $colon = true): bool
    {
        $text = trim($text);
        // This only vetoes extraction; a bounded prefix never supplies a fact or drops a qualifier from evidence.
        $prefix = mb_substr($text, 0, 128);

        return ($colon && str_ends_with($text, ':')) || (bool) preg_match('/^(?:For\b|If\b|When\b|Unless\b|Except\b|During\b|Only\b|Illustration\b)|\b(?:only|invited|waiv(?:e[dr]?|ers?)|discounts?|promotional|temporary|eligible|examples?|previously|obsolete)\b/i', $prefix);
    }

    private function append(array &$units, string $text, string $locator, string $heading = '', ?string $href = null, array $contexts = [], bool $ambiguous = false): void
    {
        // Never truncate a rule input: a qualifier at the end must not disappear.
        $text = trim(preg_replace('/\s+/u', ' ', $text));
        $heading = trim(preg_replace('/\s+/u', ' ', $heading));
        if ($text === '' || strlen($text) > 1024 || strlen($heading) > 128 || strlen($locator) > 512 || ($href !== null && strlen($href) > 2048)) {
            return;
        }
        if (count($units) >= 512) {
            throw new DomainException('editorial_document_limit');
        }
        $units[] = new EvidenceUnit($text, $locator, $heading, $href, $contexts, $ambiguous);
    }
}
