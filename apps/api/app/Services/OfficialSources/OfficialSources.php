<?php

namespace App\Services\OfficialSources;

use App\Enums\JournalSourceType;
use App\Models\Journal;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class OfficialSources
{
    public const PURPOSES = ['website', 'guidelines', 'submission', 'fees', 'template', 'article_types', 'editorial'];

    public function __construct(private OfficialUrl $urls, private PublicDns $dns, private OfficialSourceClient $client) {}

    public function register(Journal $journal, string $type, string $purpose, string $url, bool $dryRun = false): JournalSource
    {
        if (! in_array($type, ['official_journal', 'official_publisher'], true) || ! in_array($purpose, self::PURPOSES, true)) {
            throw new DomainException('official_invalid_classification');
        }
        $url = $this->urls->normalize($url);
        $attributes = ['official_journal_id' => $journal->id, 'official_url' => $url];
        $values = ['type' => $type, 'name' => 'Official '.$purpose.' page', 'base_url' => $this->urls->origin($url),
            'official_origin' => $this->urls->origin($url), 'official_purpose' => $purpose,
            'first_observed_at' => now()];
        $existing = JournalSource::where($attributes)->first();
        if ($existing) {
            if ($existing->type->value !== $type || $existing->official_purpose !== $purpose) {
                throw new DomainException('official_registration_conflict');
            }

            return $existing;
        }
        $candidate = new JournalSource($attributes + $values);
        $candidate->approval_status = 'candidate';
        if ($dryRun) {
            return $candidate;
        }
        // Internal creation sets candidate state explicitly; protected approval fields never enter fill().
        $source = DB::transaction(function () use ($journal, $attributes, $candidate): JournalSource {
            Journal::lockForUpdate()->findOrFail($journal->id);
            $source = JournalSource::where($attributes)->first();
            if ($source) {
                return $source;
            }
            $candidate->save();

            return $candidate;
        });
        if ($source->type->value !== $type || $source->official_purpose !== $purpose) {
            throw new DomainException('official_registration_conflict');
        }

        return $source;
    }

    /** Operator attests ownership, applicability, access/robots policy and internal retention rights. */
    public function approve(JournalSource $source, string $actor, string $basis, bool $dryRun = false): JournalSource
    {
        if (! preg_match('/^[A-Za-z0-9_.-]{1,128}$/D', $actor)
            || ! preg_match('/^[\x20-\x7e]{1,512}$/D', $basis) || trim($basis) === '') {
            throw new DomainException('official_invalid_approval');
        }
        $source = $source->fresh();
        if (! $source || ! $source->official_journal_id || ! in_array($source->type, [JournalSourceType::OfficialJournal, JournalSourceType::OfficialPublisher], true)
            || $source->approval_status === 'inactive') {
            throw new DomainException('official_invalid_source');
        }
        $url = $this->urls->normalize($source->official_url);
        $this->dns->validate($this->dns->resolve(parse_url($url, PHP_URL_HOST)));
        if ($source->approval_status === 'approved' || $dryRun) {
            return $source;
        }

        return DB::transaction(function () use ($source, $actor, $basis): JournalSource {
            $locked = JournalSource::lockForUpdate()->findOrFail($source->id);
            if ($locked->approval_status !== 'candidate') {
                throw new DomainException('official_approval_changed');
            }
            $locked->forceFill(['approval_status' => 'approved', 'approved_by' => $actor,
                'approval_basis' => $basis, 'approved_at' => now()])->save();

            return $locked;
        });
    }

    public function deactivate(JournalSource $source, bool $dryRun = false): void
    {
        if (! $source->official_journal_id || $source->approval_status !== 'approved') {
            throw new DomainException('official_not_approved');
        }
        if (! $dryRun) {
            $source->forceFill(['approval_status' => 'inactive'])->save();
        }
    }

    public function fetch(JournalSource $source, bool $dryRun = false): array
    {
        // A session advisory lock serializes one source, without a long transaction during DNS/HTTP.
        $lock = 'official-source:'.$source->id;
        if (! DB::selectOne('SELECT pg_try_advisory_lock(hashtextextended(?, 0)) AS acquired', [$lock])->acquired) {
            throw new DomainException('official_source_busy');
        }
        try {
            $source = $source->fresh();
            if (! $source || ! $source->official_journal_id || $source->approval_status !== 'approved') {
                throw new DomainException('official_not_approved');
            }
            $previous = $source->last_source_record_id ? SourceRecord::findOrFail($source->last_source_record_id) : null;
            try {
                $result = $this->client->fetch($source, $previous);
            } catch (Throwable $exception) {
                $code = $exception instanceof DomainException && preg_match('/^(official_[a-z0-9_]+|postgres_incompatible_text)$/D', $exception->getMessage())
                    ? $exception->getMessage() : 'official_operation_failed';
                if (! $dryRun) {
                    $source->update(['last_checked_at' => now(), 'last_outcome' => $code]);
                }
                throw new DomainException($code);
            }
            if ($dryRun) {
                return $this->summary($result) + ['dry_run' => true, 'source_id' => $source->id];
            }

            return DB::transaction(function () use ($source, $result): array {
                $source = JournalSource::lockForUpdate()->findOrFail($source->id);
                // Approval might have been revoked during acquisition. Never persist that observation.
                if ($source->approval_status !== 'approved') {
                    throw new DomainException('official_approval_changed');
                }
                $checked = now();
                if ($result['outcome'] === 'not_modified') {
                    $source->update(['last_checked_at' => $checked, 'last_outcome' => 'not_modified']);

                    return $this->summary($result) + ['dry_run' => false, 'source_id' => $source->id,
                        'source_record_id' => $source->last_source_record_id];
                }
                $record = SourceRecord::firstOrCreate(['journal_source_id' => $source->id,
                    'external_id' => hash('sha256', $source->official_url), 'revision_fingerprint' => $result['fingerprint']],
                    ['source_url' => $source->official_url, 'retrieved_at' => $checked,
                        'content_hash' => $result['content_hash'], 'status' => 'captured',
                        'raw_content' => $result['body'], 'snapshot_metadata' => $result['metadata']]);
                $changed = $source->last_source_record_id !== $record->id;
                $outcome = $changed ? 'changed' : 'unchanged';
                $values = ['last_checked_at' => $checked, 'last_outcome' => $outcome,
                    'last_source_record_id' => $record->id, 'last_etag' => $result['metadata']['etag'],
                    'last_modified' => $result['metadata']['last_modified'], 'last_final_url' => $result['metadata']['final_url']];
                if ($changed) {
                    $values['last_changed_at'] = $checked;
                }
                $source->update($values);

                return $this->summary($result) + ['dry_run' => false, 'source_id' => $source->id,
                    'source_record_id' => $record->id, 'change' => $outcome];
            });
        } finally {
            DB::select('SELECT pg_advisory_unlock(hashtextextended(?, 0))', [$lock]);
        }
    }

    private function summary(array $result): array
    {
        if ($result['outcome'] === 'not_modified') {
            return $result;
        }

        // CLI/operator summaries never contain page bodies, raw headers or validators.
        return ['outcome' => $result['outcome'], 'final_url' => $result['metadata']['final_url'],
            'http_status' => 200, 'content_type' => $result['metadata']['content_type'],
            'bytes' => $result['metadata']['bytes'], 'fingerprint' => $result['fingerprint']];
    }
}
