<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperServiceInterface;
use DOMDocument;
use DOMElement;
use DOMNode;
use DOMXPath;
use Illuminate\Support\Facades\Http;

class CairnScraperService implements ScraperServiceInterface
{
    public function fetch(string $query): array
    {
        $html = Http::retry(2, 300)
            ->timeout(20)
            ->get('https://www.cairn.info/resultats_recherche.php', [
                'searchTerm' => $query,
            ])
            ->throw()
            ->body();

        $dom = new DOMDocument();
        @$dom->loadHTML($html);

        $xpath = new DOMXPath($dom);
        $links = $xpath->query('//a[contains(@href, "-article-")]');
        $results = [];

        foreach ($links ?: [] as $link) {
            if (! $link instanceof DOMElement) {
                continue;
            }

            $title = trim($link->textContent);
            $href = trim($link->getAttribute('href'));

            if ($title === '' || $href === '') {
                continue;
            }

            $context = $this->surroundingText($link);
            preg_match('/(19|20)\d{2}/', $context, $yearMatch);
            preg_match('/(?:par|Par)\s+([^.,|]+)/u', $context, $authorMatch);

            $results[] = [
                'title' => $title,
                'author' => trim($authorMatch[1] ?? 'Auteur Cairn'),
                'year' => $yearMatch[0] ?? null,
                'abstract' => null,
                'source_name' => 'Cairn',
                'source_url' => str_starts_with($href, 'http') ? $href : 'https://www.cairn.info/'.$href,
                'original_id' => sha1($href),
                'type' => 'article',
                'domain' => 'Sciences humaines et sociales',
                'tags' => [$query],
            ];
        }

        return collect($results)
            ->unique('source_url')
            ->values()
            ->all();
    }

    private function surroundingText(DOMNode $node): string
    {
        $current = $node;

        while ($current->parentNode instanceof DOMElement) {
            $current = $current->parentNode;

            if (in_array($current->nodeName, ['article', 'li', 'div'], true)) {
                return preg_replace('/\s+/u', ' ', trim($current->textContent)) ?? '';
            }
        }

        return preg_replace('/\s+/u', ' ', trim($node->textContent)) ?? '';
    }
}
