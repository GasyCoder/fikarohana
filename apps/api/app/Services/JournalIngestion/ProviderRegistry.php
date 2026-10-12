<?php

namespace App\Services\JournalIngestion;

use App\Services\JournalIngestion\Providers\Crossref\CrossrefProvider;
use App\Services\JournalIngestion\Providers\Doaj\DoajProvider;
use App\Services\JournalIngestion\Providers\OpenAlex\OpenAlexProvider;
use DomainException;

class ProviderRegistry
{
    public function get(string $key): ProviderAdapter
    {
        return match ($key) {
            'synthetic' => new SyntheticProvider,
            'doaj' => new DoajProvider,
            'crossref' => new CrossrefProvider,
            'openalex' => new OpenAlexProvider,
            default => throw new DomainException('provider_not_registered'),
        };
    }
}
