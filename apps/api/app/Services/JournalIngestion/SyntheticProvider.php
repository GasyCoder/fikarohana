<?php

namespace App\Services\JournalIngestion;

use App\Enums\JournalSourceType;
use App\Models\JournalSource;
use App\Support\CatalogNormalizer;

/** Local synthetic journal metadata only; this adapter has no transport. */
class SyntheticProvider implements ProviderAdapter
{
    public function key(): string
    {
        return 'synthetic';
    }

    public function mappingVersion(): string
    {
        return 'synthetic-v1';
    }

    public function sourceLocator(string $field): string
    {
        return $field;
    }

    public function accepts(JournalSource $source): bool
    {
        return $source->type === JournalSourceType::Other;
    }

    public function validatePayload(array $payload): void
    {
        (new ProposalValidator)->validateShape($payload);
    }

    public function normalize(array $payload): array
    {
        $this->validatePayload($payload);
        $payload['title'] = trim($payload['title']);
        foreach ($payload['identifiers'] as &$identifier) {
            $identifier['original_value'] = $identifier['value'];
            $identifier['value'] = CatalogNormalizer::issn($identifier['value']);
        }
        unset($identifier);
        foreach (['website', 'submission_url'] as $field) {
            if (isset($payload[$field])) {
                $payload[$field] = CatalogNormalizer::url($payload[$field]);
            }
        }
        if (isset($payload['country'])) {
            $payload['country'] = strtoupper($payload['country']);
        }
        foreach ($payload['fees'] ?? [] as $index => $fee) {
            if ($fee['currency'] !== null) {
                $payload['fees'][$index]['currency'] = strtoupper($fee['currency']);
            }
        }

        return $payload;
    }
}
