<?php

namespace App\Services\JournalIngestion\Providers\OpenAlex;

use App\Enums\JournalSourceType;
use App\Models\JournalSource;
use App\Services\JournalIngestion\PostgresText;
use App\Services\JournalIngestion\ProviderAdapter;
use App\Support\CatalogNormalizer;
use DomainException;

class OpenAlexProvider implements ProviderAdapter
{
    public function key(): string
    {
        return 'openalex';
    }

    public function mappingVersion(): string
    {
        return 'openalex-source-topics-v1';
    }

    public function accepts(JournalSource $source): bool
    {
        return $source->type === JournalSourceType::Openalex;
    }

    public function envelopes(array $records): array
    {
        $retrieved = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return array_map(function ($record) use ($retrieved): array {
            if (! is_string($record['id'] ?? null) || ! preg_match('~^https://openalex\.org/(S[1-9][0-9]{0,19})$~D', $record['id'], $match)) {
                throw new DomainException('openalex_invalid_response');
            }

            return ['external_id' => $match[1], 'source_url' => OpenAlexClient::BASE.'/sources/'.$match[1],
                'retrieved_at' => $retrieved, 'payload' => $record];
        }, $records);
    }

    public function validatePayload(array $payload): void
    {
        PostgresText::validate($payload);
        if (! is_string($payload['id'] ?? null) || ! preg_match('~^https://openalex\.org/S[1-9][0-9]{0,19}$~D', $payload['id'])
            || ! is_string($payload['_lookup_issn'] ?? null)) {
            throw new DomainException('openalex_invalid_response');
        }
        CatalogNormalizer::issn($payload['_lookup_issn']);
    }

    public function normalize(array $payload): array
    {
        $this->validatePayload($payload);

        return app(OpenAlexNormalizer::class)->normalize($payload);
    }

    public function sourceLocator(string $field, array $payload = []): string
    {
        return match ($field) {
            'title' => 'display_name', 'publisher' => 'host_organization_name', 'identifiers' => 'issn',
            default => throw new DomainException('openalex_unmapped_field'),
        };
    }
}
