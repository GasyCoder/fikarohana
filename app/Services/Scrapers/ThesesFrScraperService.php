<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperServiceInterface;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Str;

class ThesesFrScraperService implements ScraperServiceInterface
{
    public function fetch(string $query): array
    {
        $response = Http::retry(2, 300)
            ->timeout(20)
            ->acceptJson()
            ->get('https://theses.fr/', [
                'q' => $query,
                'format' => 'json',
            ]);

        if ($response->successful() && is_array($response->json())) {
            return $this->mapJsonResults($response->json());
        }

        return $this->scrapeSearchResults($query);
    }

    /**
     * @param  array<string, mixed>  $payload
     * @return array<int, array<string, mixed>>
     */
    private function mapJsonResults(array $payload): array
    {
        $items = collect($payload['theses'] ?? $payload['results'] ?? $payload['response'] ?? [])
            ->filter(fn (mixed $item) => is_array($item));

        return $items
            ->map(fn (array $item) => [
                'title' => $item['titrePrincipal'] ?? $item['title'] ?? null,
                'author' => $item['auteur'] ?? data_get($item, 'author.nom') ?? null,
                'year' => $item['dateSoutenance'] ?? $item['year'] ?? null,
                'abstract' => $item['resume'] ?? $item['abstract'] ?? null,
                'source_name' => 'Theses.fr',
                'source_url' => isset($item['id']) ? 'https://theses.fr/'.ltrim((string) $item['id'], '/') : ($item['url'] ?? null),
                'original_id' => $item['id'] ?? null,
                'type' => 'thesis',
                'domain' => data_get($item, 'discipline') ?? 'Recherche doctorale',
                'tags' => array_filter([
                    data_get($item, 'discipline'),
                    data_get($item, 'etabSoutenanceN'),
                ]),
            ])
            ->all();
    }

    /**
     * @return array<int, array<string, mixed>>
     */
    private function scrapeSearchResults(string $query): array
    {
        $html = Http::retry(2, 300)
            ->timeout(20)
            ->get('https://theses.fr/', ['q' => $query])
            ->throw()
            ->body();

        preg_match_all('/href="\/([^"]+)"[^>]*>\s*<span[^>]*class="title"[^>]*>(.*?)<\/span>.*?Soutenue en\s*(\d{4}).*?par\s*(.*?)</si', $html, $matches, PREG_SET_ORDER);

        return collect($matches)
            ->map(fn (array $match) => [
                'title' => trim(strip_tags($match[2] ?? '')),
                'author' => trim(strip_tags($match[4] ?? '')),
                'year' => $match[3] ?? null,
                'abstract' => null,
                'source_name' => 'Theses.fr',
                'source_url' => 'https://theses.fr/'.ltrim((string) ($match[1] ?? ''), '/'),
                'original_id' => $match[1] ?? null,
                'type' => 'thesis',
                'domain' => 'Recherche doctorale',
                'tags' => [Str::headline($query)],
            ])
            ->all();
    }
}
