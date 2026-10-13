<?php

namespace App\Services\EditorialExtraction;

use App\Support\CatalogNormalizer;
use GuzzleHttp\Psr7\Uri;
use GuzzleHttp\Psr7\UriResolver;
use InvalidArgumentException;

class LinkRule implements EditorialRule
{
    public function __construct(private readonly bool $template) {}

    public function id(): string
    {
        return $this->template ? 'explicit_template_link' : 'explicit_submission_link';
    }

    public function field(): string
    {
        return $this->template ? 'template' : 'submission_url';
    }

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array
    {
        if (! RuleContext::allows($unit) || $unit->href === null || trim($unit->href) === '' || str_starts_with($unit->href, '#') || preg_match('/[\x00-\x20\x7f\\\\]/', $unit->href)) {
            return [];
        }
        $pattern = $this->template
            ? '/^(?:Download )?(?:manuscript|article) template(?: \((?:DOCX|Word|LaTeX|PDF)\))?$/iD'
            : '/^(?:Submit your manuscript|Online submission|Submit an article)$/iD';
        if (! preg_match($pattern, $unit->text)) {
            return [];
        }
        try {
            // Existing URI implementation, pure string resolution; ignore page-controlled <base>.
            $url = CatalogNormalizer::url((string) UriResolver::resolve(new Uri($document->baseUrl), new Uri($unit->href)));
        } catch (InvalidArgumentException $exception) {
            return [];
        }
        if (strlen($url) > 2048) {
            return [];
        }
        if ($this->template) {
            // Outside-origin template ownership cannot be established from one registered page.
            $origin = fn ($value) => [parse_url($value, PHP_URL_SCHEME), parse_url($value, PHP_URL_HOST), parse_url($value, PHP_URL_PORT) ?? (parse_url($value, PHP_URL_SCHEME) === 'https' ? 443 : 80)];
            if ($origin($url) !== $origin($document->baseUrl)) {
                return [];
            }
            $extension = strtolower(pathinfo(parse_url($url, PHP_URL_PATH) ?? '', PATHINFO_EXTENSION));
            $format = match ($extension) {
                'doc', 'docx' => 'word', 'tex' => 'latex', default => 'other'
            };

            return [['format' => $format, 'official_url' => $url, 'label' => $unit->text]];
        }

        return [['text_value' => $url]];
    }
}
