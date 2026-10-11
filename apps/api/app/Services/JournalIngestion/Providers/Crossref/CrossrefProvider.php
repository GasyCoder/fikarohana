<?php

namespace App\Services\JournalIngestion\Providers\Crossref;

use App\Enums\JournalSourceType;
use App\Models\JournalSource;
use App\Services\JournalIngestion\PostgresText;
use App\Services\JournalIngestion\ProviderAdapter;
use App\Support\CatalogNormalizer;
use DomainException;

class CrossrefProvider implements ProviderAdapter
{
    public function key(): string
    {
        return 'crossref';
    }

    public function mappingVersion(): string
    {
        return 'crossref-journal-v1';
    }

    public function accepts(JournalSource $source): bool
    {
        return $source->type === JournalSourceType::Crossref;
    }

    public function envelopes(array $records): array
    {
        $retrieved = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return array_map(fn ($record) => [
            'external_id' => $record['_lookup_issn'],
            'source_url' => CrossrefClient::BASE.'/journals/'.CatalogNormalizer::issn($record['_lookup_issn']),
            'retrieved_at' => $retrieved, 'payload' => $record,
        ], $records);
    }

    public function validatePayload(array $payload): void
    {
        PostgresText::validate($payload);
        if (($payload['status'] ?? null) !== 'ok' || ($payload['message-type'] ?? null) !== 'journal'
            || ! is_string($payload['message-version'] ?? null) || ! is_array($payload['message'] ?? null)
            || ! is_string($payload['_lookup_issn'] ?? null)) {
            throw new DomainException('crossref_invalid_response');
        }
        CatalogNormalizer::issn($payload['_lookup_issn']);
    }

    public function normalize(array $payload): array
    {
        $this->validatePayload($payload);

        return app(CrossrefNormalizer::class)->normalize($payload);
    }

    public function sourceLocator(string $field, array $payload = []): string
    {
        return match ($field) {
            'title' => 'message.title', 'publisher' => 'message.publisher', 'identifiers' => 'message.ISSN',
            default => throw new DomainException('crossref_unmapped_field'),
        };
    }
}
