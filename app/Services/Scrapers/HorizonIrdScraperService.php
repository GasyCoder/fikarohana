<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperServiceInterface;
use Illuminate\Support\Facades\Http;

class HorizonIrdScraperService implements ScraperServiceInterface
{
    public function fetch(string $query): array
    {
        $html = Http::retry(2, 300)
            ->timeout(20)
            ->get('https://horizon.documentation.ird.fr/exl-search/search', [
                'query' => "any,contains,{$query}",
                'tab' => 'default_tab',
                'search_scope' => 'default_scope',
                'sortby' => 'rank',
            ])
            ->throw()
            ->body();

        preg_match_all('/href="([^"]*display[^"]*docid[^"]*)"[^>]*>(.*?)<\/a>.*?(19|20)\d{2}/si', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(function (array $match): array {
                preg_match('/(19|20)\d{2}/', $match[0], $yearMatch);

                return [
                    'title' => trim(strip_tags($match[2] ?? '')),
                    'author' => 'Collectif IRD',
                    'year' => $yearMatch[0] ?? null,
                    'abstract' => null,
                    'source_name' => 'Horizon IRD',
                    'source_url' => str_starts_with($match[1] ?? '', 'http')
                        ? $match[1]
                        : 'https://horizon.documentation.ird.fr'.$match[1],
                    'original_id' => sha1((string) ($match[1] ?? '')),
                    'type' => 'article',
                    'domain' => 'Recherche IRD',
                    'tags' => [],
                ];
            })
            ->filter(fn (array $item) => $item['title'] !== '')
            ->all();
    }
}
