<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class HalScraperService implements ScraperServiceInterface
{
    public function fetch(string $query): array
    {
        $response = Http::baseUrl('https://api.archives-ouvertes.fr')
            ->retry(2, 300)
            ->timeout(20)
            ->get('/search/', [
                'q' => $query,
                'rows' => 20,
                'wt' => 'json',
                'fl' => implode(',', [
                    'docid',
                    'title_s',
                    'authFullName_s',
                    'producedDateY_i',
                    'abstract_s',
                    'uri_s',
                    'docType_s',
                    'domainAllCode_s',
                    'keyword_s',
                ]),
            ])
            ->throw();

        return collect($response->json('response.docs', []))
            ->map(fn (array $doc) => [
                'title' => $doc['title_s'][0] ?? null,
                'author' => implode(', ', $doc['authFullName_s'] ?? []),
                'year' => $doc['producedDateY_i'] ?? null,
                'abstract' => $doc['abstract_s'][0] ?? null,
                'source_name' => 'HAL',
                'source_url' => $doc['uri_s'] ?? null,
                'original_id' => isset($doc['docid']) ? (string) $doc['docid'] : null,
                'type' => Str::contains(Str::lower((string) ($doc['docType_s'] ?? '')), 'these') ? 'thesis' : 'article',
                'domain' => $doc['domainAllCode_s'][0] ?? 'Interdisciplinaire',
                'tags' => $doc['keyword_s'] ?? [],
            ])
            ->all();
    }
}
