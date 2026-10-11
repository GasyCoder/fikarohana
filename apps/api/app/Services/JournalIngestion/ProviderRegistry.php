<?php

namespace App\Services\JournalIngestion;

use DomainException;

class ProviderRegistry
{
    public function get(string $key): ProviderAdapter
    {
        return match ($key) {
            'synthetic' => new SyntheticProvider,
            default => throw new DomainException('provider_not_registered'),
        };
    }
}
