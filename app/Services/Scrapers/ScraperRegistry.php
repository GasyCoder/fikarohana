<?php

namespace App\Services\Scrapers;

use App\Contracts\ScraperServiceInterface;
use Illuminate\Support\Str;
use InvalidArgumentException;

class ScraperRegistry
{
    public function __construct(
        private readonly HalScraperService $hal,
        private readonly OpenAlexScraperService $openAlex,
        private readonly ThesesFrScraperService $thesesFr,
        private readonly CairnScraperService $cairn,
        private readonly HorizonIrdScraperService $horizonIrd,
    ) {}

    /**
     * @return array<string, string>
     */
    public function availableSources(): array
    {
        return config('fikarohana.sources', []);
    }

    public function forSource(string $source): ScraperServiceInterface
    {
        return match ($this->normalize($source)) {
            'hal' => $this->hal,
            'openalex' => $this->openAlex,
            'thesesfr' => $this->thesesFr,
            'cairn' => $this->cairn,
            'horizon' => $this->horizonIrd,
            'horizon-ird' => $this->horizonIrd,
            default => throw new InvalidArgumentException("Unsupported source [{$source}]."),
        };
    }

    public function labelFor(string $source): string
    {
        $normalized = $this->normalize($source);

        return $this->availableSources()[$normalized] ?? Str::headline($normalized);
    }

    public function normalize(string $source): string
    {
        return Str::of($source)
            ->lower()
            ->replace('_', '-')
            ->replace(' ', '-')
            ->toString();
    }
}
