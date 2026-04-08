<?php

namespace App\Jobs;

use App\Models\Resource;
use App\Services\Scrapers\ScraperRegistry;
use App\Support\ResourceFingerprint;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Arr;
use Illuminate\Support\Collection;
use Illuminate\Support\Str;

class SyncSourceResourcesJob implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public string $source,
        public string $query,
    ) {}

    public function handle(ScraperRegistry $registry): void
    {
        $label = $registry->labelFor($this->source);
        $scraper = $registry->forSource($this->source);
        $payloads = collect($scraper->fetch($this->query))
            ->map(fn (array $item) => $this->normalizePayload($item, $label))
            ->filter()
            ->values();

        if ($payloads->isEmpty()) {
            return;
        }

        $resources = Resource::query()->get()->keyBy('id');
        $originalIndex = [];
        $fingerprintIndex = [];

        foreach ($resources as $resource) {
            if ($resource->original_id) {
                $originalIndex[$resource->source_name.'|'.$resource->original_id] = $resource->id;
            }

            $fingerprintIndex[ResourceFingerprint::make($resource->title, $resource->author)] = $resource->id;
        }

        foreach ($payloads as $payload) {
            $resourceId = null;

            if ($payload['original_id']) {
                $resourceId = $originalIndex[$payload['source_name'].'|'.$payload['original_id']] ?? null;
            }

            $resourceId ??= $fingerprintIndex[$payload['fingerprint']] ?? null;

            $resource = $resourceId
                ? $resources->get($resourceId)
                : new Resource();

            $attributes = Arr::except($payload, 'fingerprint');
            $resource->fill($attributes);
            $resource->slug = $this->uniqueSlug(
                title: $attributes['title'],
                year: $attributes['year'],
                ignoreId: $resource->id,
            );
            $resource->save();

            $resources->put($resource->id, $resource);

            if ($resource->original_id) {
                $originalIndex[$resource->source_name.'|'.$resource->original_id] = $resource->id;
            }

            $fingerprintIndex[$payload['fingerprint']] = $resource->id;
        }
    }

    private function normalizePayload(array $payload, string $sourceLabel): ?array
    {
        $title = $this->stringValue($payload, 'title');
        $author = $this->stringValue($payload, 'author') ?: 'Auteur inconnu';
        $sourceUrl = $this->stringValue($payload, 'source_url');
        $year = $this->yearValue($payload['year'] ?? null);

        if ($title === null || $sourceUrl === null || $year === null) {
            return null;
        }

        $tags = collect($payload['tags'] ?? [])
            ->when(! is_array($payload['tags'] ?? null), fn (Collection $collection) => $collection->push($payload['tags']))
            ->filter(fn (mixed $tag) => is_scalar($tag) && trim((string) $tag) !== '')
            ->map(fn (mixed $tag) => Str::title(Str::lower(trim((string) $tag))))
            ->unique()
            ->values()
            ->all();

        return [
            'title' => $title,
            'author' => $author,
            'year' => $year,
            'abstract' => $this->stringValue($payload, 'abstract'),
            'source_name' => $this->stringValue($payload, 'source_name') ?? $sourceLabel,
            'source_url' => $sourceUrl,
            'original_id' => $this->stringValue($payload, 'original_id'),
            'type' => $this->normalizeType($this->stringValue($payload, 'type')),
            'domain' => $this->stringValue($payload, 'domain')
                ?? $this->stringValue($payload, 'domain_name')
                ?? 'Interdisciplinaire',
            'institution' => $this->stringValue($payload, 'institution')
                ?? $this->stringValue($payload, 'university')
                ?? $this->stringValue($payload, 'university_name'),
            'tags' => $tags === [] ? null : $tags,
            'fingerprint' => ResourceFingerprint::make($title, $author),
        ];
    }

    private function stringValue(array $payload, string $key): ?string
    {
        $value = $payload[$key] ?? null;

        if (is_array($value)) {
            $value = $value[0] ?? null;
        }

        if (! is_scalar($value)) {
            return null;
        }

        $value = trim((string) $value);

        return $value !== '' ? $value : null;
    }

    private function yearValue(mixed $value): ?int
    {
        if (is_numeric($value)) {
            return (int) $value;
        }

        if (is_string($value) && preg_match('/(19|20)\d{2}/', $value, $matches)) {
            return (int) $matches[0];
        }

        return null;
    }

    private function normalizeType(?string $type): string
    {
        $value = Str::lower((string) $type);

        return Str::contains($value, ['thesis', 'these', 'doctorat', 'phd']) ? 'thesis' : 'article';
    }

    private function uniqueSlug(string $title, int $year, ?int $ignoreId = null): string
    {
        $base = Str::slug(Str::limit($title, 80, '')).'-'.$year;
        $base = trim($base, '-') ?: 'resource-'.$year;
        $slug = $base;
        $counter = 2;

        while (
            Resource::query()
                ->where('slug', $slug)
                ->when($ignoreId, fn ($query) => $query->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$counter}";
            $counter++;
        }

        return $slug;
    }
}
