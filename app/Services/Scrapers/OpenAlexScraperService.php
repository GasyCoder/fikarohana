<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class OpenAlexScraperService implements ScraperServiceInterface
{
    public function fetch(string $query): array
    {
        $response = Http::baseUrl('https://api.openalex.org')
            ->retry(2, 300)
            ->timeout(20)
            ->get('/works', [
                'search' => $query,
                'per-page' => 20,
                'filter' => 'has_abstract:true',
            ])
            ->throw();

        return collect($response->json('results', []))
            ->map(function (array $work): array {
                $firstAuthorship = $work['authorships'][0] ?? [];
                $institutions = collect($work['authorships'] ?? [])
                    ->flatMap(fn (array $authorship) => $authorship['institutions'] ?? [])
                    ->pluck('display_name')
                    ->filter()
                    ->unique()
                    ->values();

                return [
                    'title' => $work['display_name'] ?? null,
                    'author' => data_get($firstAuthorship, 'author.display_name'),
                    'year' => $work['publication_year'] ?? null,
                    'abstract' => $this->abstractFromInvertedIndex($work['abstract_inverted_index'] ?? null),
                    'source_name' => 'OpenAlex',
                    'source_url' => data_get($work, 'primary_location.landing_page_url')
                        ?? data_get($work, 'primary_location.pdf_url')
                        ?? $work['id']
                        ?? null,
                    'original_id' => isset($work['id']) ? basename((string) $work['id']) : null,
                    'type' => Str::contains(Str::lower((string) ($work['type'] ?? '')), ['dissertation', 'thesis']) ? 'thesis' : 'article',
                    'domain' => data_get($work, 'concepts.0.display_name') ?? 'Interdisciplinaire',
                    'institution' => $institutions->first(),
                    'tags' => $institutions->take(3)->all(),
                ];
            })
            ->all();
    }

    /**
     * @param  array<string, array<int, int>>|null  $index
     */
    private function abstractFromInvertedIndex(?array $index): ?string
    {
        if (! is_array($index) || $index === []) {
            return null;
        }

        $words = [];

        foreach ($index as $word => $positions) {
            foreach ($positions as $position) {
                $words[(int) $position] = $word;
            }
        }

        ksort($words);

        return trim(implode(' ', $words)) ?: null;
    }
}
