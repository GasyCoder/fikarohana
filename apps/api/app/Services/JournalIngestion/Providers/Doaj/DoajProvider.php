<?php

namespace App\Services\JournalIngestion\Providers\Doaj;

use App\Enums\JournalSourceType;
use App\Models\JournalSource;
use App\Services\JournalIngestion\PostgresText;
use App\Services\JournalIngestion\ProviderAdapter;
use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Support\Facades\Validator;

/** Public v4 journal mapping only. Raw public record stays in Phase 3A staging. */
class DoajProvider implements ProviderAdapter
{
    public function key(): string
    {
        return 'doaj';
    }

    public function mappingVersion(): string
    {
        return 'doaj-v4-csv-v2';
    }

    public function accepts(JournalSource $source): bool
    {
        return $source->type === JournalSourceType::Doaj;
    }

    public function sourceLocator(string $field, array $payload = []): string
    {
        if (($payload['_acquisition']['transport'] ?? null) === 'csv') {
            return '_csv_row.'.match ($field) {
                'title' => 'Journal title', 'identifiers' => 'URL in DOAJ',
                'publisher' => 'Publisher', 'languages' => 'Languages in which the journal accepts manuscripts',
                'subjects' => 'Subjects', 'website' => 'Journal URL', 'fees.0' => 'APC',
                default => throw new DomainException('doaj_unmapped_field'),
            };
        }

        return match ($field) {
            'title' => 'bibjson.title',
            'identifiers' => 'bibjson',
            'publisher' => 'bibjson.publisher.name',
            'languages' => 'bibjson.language',
            'subjects' => 'bibjson.subject',
            'website' => 'bibjson.ref.journal',
            'fees.0' => 'bibjson.apc',
            default => throw new DomainException('doaj_unmapped_field'),
        };
    }

    public function envelopes(array $records, string $transport = 'api'): array
    {
        if (! in_array($transport, ['api', 'csv'], true)) {
            throw new DomainException('doaj_invalid_transport');
        }
        $retrievedAt = now()->utc()->format('Y-m-d\TH:i:s\Z');

        return array_map(function ($record) use ($retrievedAt, $transport): array {
            // Invalid IDs are left invalid for quarantine, never converted into a plausible ID.
            $id = is_array($record) ? ($record['id'] ?? null) : null;

            if (is_array($record)) {
                $record['_acquisition'] = ['provider' => 'doaj', 'transport' => $transport, 'source' => $transport === 'csv' ? DoajCsvClient::SOURCE : 'https://doaj.org/api/v4'];
            }

            return ['external_id' => $id, 'source_url' => $transport === 'csv' ? DoajCsvClient::SOURCE : (is_string($id) && preg_match('/^[0-9a-f]{32}$/D', $id)
                ? 'https://doaj.org/api/v4/journals/'.$id : null), 'retrieved_at' => $retrievedAt, 'payload' => $record];
        }, $records);
    }

    public function validatePayload(array $payload): void
    {
        PostgresText::validate($payload);
        $this->validateUrls($payload);
        if (($payload['_acquisition']['transport'] ?? null) === 'csv') {
            if (isset($payload['_csv_error'])) {
                throw new DomainException($payload['_csv_error'] === 'postgres_incompatible_text' ? 'postgres_incompatible_text' : 'doaj_csv_invalid_row');
            }
            Validator::make($payload, ['id' => ['required', 'string', 'regex:/^(?:[0-9a-f]{32}|csv\.[0-9a-f]{64})$/D'], '_csv_row' => 'required|array'])->validate();

            return;
        }
        Validator::make($payload, [
            'id' => 'required|string|regex:/^[0-9a-f]{32}$/D',
            'bibjson' => 'required|array',
            'admin' => 'sometimes|array:in_doaj,ticked,last_full_review',
            // Semantic mapping validation follows retention of structurally safe observations.
        ])->validate();
    }

    private function validateUrls(array $values): void
    {
        foreach ($values as $value) {
            if (is_array($value)) {
                $this->validateUrls($value);
            } elseif (is_string($value) && preg_match('/^https?:\/\//i', trim($value))) {
                $url = CatalogNormalizer::url($value);
                if (parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                    throw new DomainException('doaj_unsafe_url');
                }
            }
        }
    }

    public function normalize(array $payload): array
    {
        $this->validatePayload($payload);
        if (($payload['_acquisition']['transport'] ?? null) === 'csv') {
            $payload['bibjson'] = app(DoajCsvNormalizer::class)->normalize($payload['_csv_row']);
        }
        Validator::make($payload, [
            'bibjson.title' => 'required|string|max:255',
            'bibjson.pissn' => 'sometimes|nullable|string|max:32',
            'bibjson.eissn' => 'sometimes|nullable|string|max:32',
            'bibjson.publisher' => 'sometimes|array',
            'bibjson.publisher.name' => 'sometimes|string|max:255',
            'bibjson.publisher.country' => 'sometimes|string|size:2',
            'bibjson.language' => 'sometimes|array|list|max:30',
            'bibjson.language.*' => 'required|string|regex:/^[a-z]{2,3}$/D',
            'bibjson.subject' => 'sometimes|array|list|max:30',
            'bibjson.subject.*' => 'required|array',
            'bibjson.subject.*.scheme' => 'required|string|max:30',
            'bibjson.subject.*.code' => 'required|string|max:40',
            'bibjson.subject.*.term' => 'required|string|max:150',
            'bibjson.ref' => 'sometimes|array',
            'bibjson.ref.journal' => 'sometimes|nullable|string|max:2048',
            'bibjson.apc' => 'sometimes|array',
            'bibjson.apc.has_apc' => 'sometimes|boolean',
            'bibjson.apc.max' => 'sometimes|array|list|max:20',
            'admin' => 'sometimes|array',
            'admin.in_doaj' => 'sometimes|boolean',
        ])->validate();
        $b = $payload['bibjson'];
        if (isset($payload['admin']['in_doaj']) && $payload['admin']['in_doaj'] !== true) {
            throw new DomainException('doaj_not_public');
        }
        $proposal = ['title' => trim($b['title']), 'identifiers' => []];
        foreach (['pissn' => 'issn', 'eissn' => 'eissn'] as $key => $type) {
            if (isset($b[$key]) && $b[$key] !== '') {
                $proposal['identifiers'][] = ['type' => $type, 'value' => CatalogNormalizer::issn($b[$key]), 'original_value' => $b[$key]];
            }
        }
        if (isset($b['publisher']['name'])) {
            $proposal['publisher'] = trim($b['publisher']['name']);
            if (isset($b['publisher']['country'])) {
                $proposal['publisher_country'] = strtoupper($b['publisher']['country']);
            }
        }
        // Publisher country is not silently relabelled as the journal's country.
        if (isset($b['ref']['journal'])) {
            $proposal['website'] = CatalogNormalizer::url($b['ref']['journal']);
        }
        $proposal['languages'] = array_values(array_unique($b['language'] ?? []));
        $proposal['subjects'] = array_values(array_unique(array_map(fn ($subject) => 'DOAJ '.$subject['scheme'].': '.$subject['code'].' — '.$subject['term'], $b['subject'] ?? [])));
        $apc = $b['apc'] ?? [];
        if (array_key_exists('has_apc', $apc) && ! is_bool($apc['has_apc'])) {
            throw new DomainException('doaj_invalid_apc');
        }
        foreach ($apc['max'] ?? [] as $max) {
            if (! is_array($max) || ! isset($max['price'], $max['currency']) || ! is_int($max['price']) || $max['price'] < 0
                || ! is_string($max['currency']) || ! preg_match('/^[A-Z]{3}$/D', $max['currency'])) {
                throw new DomainException('doaj_invalid_apc');
            }
        }
        $fee = ['fee_type' => 'apc', 'availability' => 'unknown', 'amount' => null, 'currency' => null,
            'basis' => 'doaj_reported_maximum', 'applicability' => 'DOAJ-reported APC; article type, tax and waiver applicability unspecified.'];
        if (($apc['has_apc'] ?? null) === false) {
            if (($apc['max'] ?? []) !== []) {
                throw new DomainException('doaj_conflicting_apc');
            }
            // The scalar amount remains unknown: explicit absence is preserved in basis/context and raw flag.
            $fee['basis'] = 'doaj_reported_no_apc';
            $fee['applicability'] = 'DOAJ explicitly reports no APC; other charges are not implied absent.';
        } elseif (($apc['has_apc'] ?? null) === true && count($apc['max'] ?? []) === 1) {
            $max = $apc['max'][0];
            $fee['availability'] = 'known';
            $fee['amount'] = (string) $max['price'];
            $fee['currency'] = $max['currency'];
        }
        $proposal['fees'] = [$fee];

        return $proposal;
    }
}
