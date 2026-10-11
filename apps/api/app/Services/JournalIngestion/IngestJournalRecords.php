<?php

namespace App\Services\JournalIngestion;

use App\Models\IngestionItem;
use App\Models\IngestionRun;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Support\CatalogNormalizer;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class IngestJournalRecords
{
    public const MAX_RECORDS = 100;

    public function __construct(private ProposalValidator $validator, private ResolveJournalIdentity $identity, private DetectJournalConflicts $conflicts) {}

    /** A bounded local batch. Dry-run returns decisions and performs no writes. */
    public function ingest(ProviderAdapter $provider, JournalSource $source, string $key, array $records, bool $dryRun = false): array
    {
        if (! $provider->accepts($source)) {
            throw new DomainException('provider_source_mismatch');
        }
        $binding = DB::table('ingestion_source_providers')->where('journal_source_id', $source->id)->value('provider');
        if ($binding !== null && $binding !== $provider->key()) {
            throw new DomainException('source_bound_to_different_provider');
        }
        if (! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key) || ! array_is_list($records) || count($records) > self::MAX_RECORDS || $records === []) {
            throw new DomainException('invalid_batch');
        }
        if (strlen(CanonicalJson::encode($records)) > 1024 * 1024) {
            throw new DomainException('batch_too_large');
        }
        // Fetch time is observation metadata, not a new input revision on an identical retry.
        $identityRecords = array_map(fn ($record) => is_array($record) ? array_diff_key($record, ['retrieved_at' => true]) : $record, $records);
        $hash = CanonicalJson::hash([$provider->key(), $provider->mappingVersion(), $identityRecords]);
        $prepared = array_map(fn ($record) => $this->prepare($provider, $record), $records);
        if ($dryRun) {
            return ['dry_run' => true, 'items' => array_map(function ($item) {
                if ($item['errors'] !== []) {
                    return ['status' => 'quarantined', 'errors' => $item['errors']];
                }
                try {
                    $journalId = $this->identity->resolve($item['normalized']['identifiers']);
                    $conflicts = $journalId === null ? [] : $this->conflicts->proposal($journalId, $item['normalized']);

                    return ['status' => $conflicts === [] ? 'valid' : 'conflicting', 'identity' => $journalId === null ? 'new' : 'existing',
                        'journal_id' => $journalId, 'conflict_ids' => $conflicts];
                } catch (DomainException) {
                    return ['status' => 'quarantined', 'errors' => ['identity_conflict']];
                }
            }, $prepared)];
        }

        return DB::transaction(function () use ($provider, $source, $key, $hash, $prepared): array {
            // Shared with materialization: protects source reuse and identifier ownership on concurrent runs.
            DB::select('SELECT pg_advisory_xact_lock(170003)');
            DB::table('ingestion_source_providers')->insertOrIgnore(['journal_source_id' => $source->id, 'provider' => $provider->key()]);
            if (DB::table('ingestion_source_providers')->where('journal_source_id', $source->id)->value('provider') !== $provider->key()) {
                throw new DomainException('source_bound_to_different_provider');
            }
            $run = IngestionRun::where('journal_source_id', $source->id)->where('idempotency_key', $key)->first();
            if ($run !== null) {
                if (! hash_equals($run->input_hash, $hash)) {
                    throw new DomainException('idempotency_key_reused_with_changed_input');
                }

                return ['run_id' => $run->id, 'replayed' => true, 'counters' => $run->counters()];
            }
            $run = IngestionRun::create([
                'journal_source_id' => $source->id, 'provider' => $provider->key(),
                'mapping_version' => $provider->mappingVersion(), 'idempotency_key' => $key, 'input_hash' => $hash,
            ]);
            foreach ($prepared as $position => $entry) {
                $observation = null;
                if ($entry['observation'] !== null) {
                    $observation = SourceRecord::firstOrCreate([
                        'journal_source_id' => $source->id, 'external_id' => $entry['observation']['external_id'],
                        'revision_fingerprint' => $entry['fingerprint'],
                    ], [
                        'source_url' => $entry['observation']['source_url'], 'retrieved_at' => $entry['observation']['retrieved_at'],
                        'content_hash' => $entry['fingerprint'], 'status' => 'captured',
                    ]);
                }
                $item = IngestionItem::firstOrCreate([
                    'journal_source_id' => $source->id, 'item_key' => $entry['item_key'], 'mapping_version' => $provider->mappingVersion(),
                ], [
                    'source_record_id' => $observation?->id, 'payload' => $entry['payload'], 'errors' => $entry['errors'],
                    'status' => $entry['errors'] === [] ? 'staged' : 'quarantined',
                ]);
                DB::table('ingestion_run_items')->insert([
                    'ingestion_run_id' => $run->id, 'ingestion_item_id' => $item->id, 'position' => $position,
                    'unchanged' => ! $item->wasRecentlyCreated,
                ]);
            }

            return ['run_id' => $run->id, 'replayed' => false, 'counters' => $run->counters()];
        });
    }

    private function prepare(ProviderAdapter $provider, mixed $record): array
    {
        $result = ['item_key' => CanonicalJson::hash($record), 'fingerprint' => null, 'observation' => null, 'payload' => null, 'normalized' => null, 'errors' => []];
        try {
            PostgresText::validate($record);
            Validator::make(['record' => $record], [
                'record' => 'required|array:external_id,source_url,retrieved_at,payload',
                'record.external_id' => 'required|string|max:255|regex:/^[A-Za-z0-9_.:-]+$/D',
                'record.source_url' => 'required|string|max:2048',
                'record.retrieved_at' => ['required', 'string', 'regex:/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:Z|[+-]\d{2}:\d{2})$/D', 'date'],
                'record.payload' => 'required|array',
            ])->validate();
            $provider->validatePayload($record['payload']);
            if (strlen(CanonicalJson::encode($record['payload'])) > 60000) {
                throw new DomainException('payload_too_large');
            }
            foreach (['website', 'submission_url'] as $field) {
                if (isset($record['payload'][$field])) {
                    $candidate = CatalogNormalizer::url($record['payload'][$field]);
                    if (parse_url($candidate, PHP_URL_QUERY) !== null || parse_url($candidate, PHP_URL_FRAGMENT) !== null) {
                        throw new DomainException('payload_url_query_or_fragment');
                    }
                }
            }
            $url = CatalogNormalizer::url($record['source_url']);
            if (parse_url($url, PHP_URL_QUERY) !== null || parse_url($url, PHP_URL_FRAGMENT) !== null) {
                throw new DomainException('source_url_query_or_fragment');
            }
            $time = CarbonImmutable::parse($record['retrieved_at'])->utc();
            if ($time->isFuture()) {
                throw new DomainException('future_retrieval');
            }
            $result['payload'] = $record['payload'];
            $result['observation'] = ['external_id' => $record['external_id'], 'source_url' => $url, 'retrieved_at' => $time];
            $result['fingerprint'] = CanonicalJson::hash([$url, $record['payload']]);
            $result['item_key'] = CanonicalJson::hash([$record['external_id'], $result['fingerprint']]);
        } catch (ValidationException|DomainException|InvalidArgumentException $exception) {
            $result['errors'] = [$exception instanceof DomainException && $exception->getMessage() === 'postgres_incompatible_text'
                ? 'postgres_incompatible_text' : 'invalid_record'];

            return $result;
        }
        try {
            $result['normalized'] = $provider->normalize($result['payload']);
            $this->validator->validate($result['normalized']);
        } catch (ValidationException|DomainException|InvalidArgumentException) {
            $result['errors'] = ['invalid_proposal'];
        }

        return $result;
    }
}
