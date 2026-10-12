<?php

namespace App\Console\Commands;

use App\Enums\VerificationStatus;
use App\Models\IngestionItem;
use App\Models\IngestionRun;
use App\Models\JournalAssertion;
use App\Models\JournalSource;
use App\Services\JournalIngestion\ExistingJournalCandidates;
use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\ProcessIngestionRun;
use App\Services\JournalIngestion\ProviderRegistry;
use App\Services\JournalIngestion\Providers\Crossref\CrossrefClient;
use App\Services\JournalIngestion\Providers\Crossref\CrossrefProvider;
use App\Services\JournalIngestion\Providers\Doaj\DoajClient;
use App\Services\JournalIngestion\Providers\Doaj\DoajCsvClient;
use App\Services\JournalIngestion\Providers\Doaj\DoajProvider;
use App\Services\JournalIngestion\Providers\OpenAlex\OpenAlexClient;
use App\Services\JournalIngestion\Providers\OpenAlex\OpenAlexProvider;
use App\Services\JournalIngestion\PublishIngestionItem;
use App\Services\JournalIngestion\ReviewIngestionAssertion;
use DomainException;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Throwable;

class JournalIngestion extends Command
{
    protected $signature = 'journals:ingestion {action : source|stage|process|status|review|publish} {id?}
        {--issn=*} {--transport=} {--provider=synthetic} {--source=} {--file=} {--key=} {--dry-run}
        {--status=} {--actor=} {--method=} {--reason=} {--assertions=} {--limit=} {--query=_exists_:bibjson.title}';

    protected $description = 'Controlled journal ingestion: synthetic fixtures, DOAJ retrieval or explicit Crossref/OpenAlex enrichment';

    public function handle(ProviderRegistry $providers): int
    {
        try {
            if ($this->option('dry-run') && $this->argument('action') !== 'stage') {
                throw new DomainException('dry_run_requires_stage');
            }
            if ($this->option('limit') !== null && $this->argument('action') !== 'stage') {
                $this->error('--limit is supported only for stage.');

                return self::INVALID;
            }
            if ($this->option('limit') !== null && (! preg_match('/^[1-9][0-9]*$/D', (string) $this->option('limit'))
                || strlen((string) $this->option('limit')) > 3 || (int) $this->option('limit') > IngestJournalRecords::MAX_RECORDS)) {
                $this->error('--limit must be an integer from 1 to '.IngestJournalRecords::MAX_RECORDS.'.');

                return self::INVALID;
            }
            if ($this->option('transport') !== null && ($this->argument('action') !== 'stage' || $this->option('provider') !== 'doaj' || ! in_array($this->option('transport'), ['api', 'csv'], true))) {
                throw new DomainException('invalid_transport');
            }
            if ($this->option('issn') !== [] && ($this->argument('action') !== 'stage' || ! in_array($this->option('provider'), ['crossref', 'openalex'], true))) {
                throw new DomainException('issn_requires_enrichment_stage');
            }
            $result = match ($this->argument('action')) {
                'source' => $this->source(),
                'stage' => $this->stage($providers),
                'process' => $this->process($providers),
                'status' => $this->status(),
                'review' => $this->review(),
                'publish' => $this->publish(),
                default => throw new DomainException('unsupported_action'),
            };
            $this->line(json_encode($result, JSON_THROW_ON_ERROR | JSON_UNESCAPED_SLASHES));

            return self::SUCCESS;
        } catch (Throwable) {
            // Payloads, paths, SQL and exception text may contain secrets. Never print/report them.
            $this->error('Ingestion operation failed; inspect staged status/error codes and command arguments.');

            return self::FAILURE;
        }
    }

    private function source(): array
    {
        if ($this->option('provider') === 'doaj') {
            return ['source_id' => $this->doajSource()->id];
        }
        if (in_array($this->option('provider'), ['crossref', 'openalex'], true)) {
            return ['source_id' => $this->enrichmentSource((string) $this->option('provider'))->id];
        }
        $name = (string) $this->argument('id');
        if (! preg_match('/^[a-z0-9][a-z0-9_.-]{0,63}$/D', $name) || $this->option('provider') !== 'synthetic') {
            throw new DomainException('invalid_synthetic_source');
        }
        $source = DB::transaction(function () use ($name) {
            DB::select('SELECT pg_advisory_xact_lock(170003)');

            return JournalSource::firstOrCreate(['type' => 'other', 'name' => 'synthetic:'.$name], ['base_url' => 'https://catalog.example.test']);
        });

        return ['source_id' => $source->id];
    }

    private function stage(ProviderRegistry $providers): array
    {
        $provider = $providers->get((string) $this->option('provider'));
        if ($provider instanceof CrossrefProvider || $provider instanceof OpenAlexProvider) {
            return $this->stageEnrichment($provider);
        }
        if ($provider instanceof DoajProvider) {
            if ($this->option('file') !== null) {
                throw new DomainException('doaj_file_not_supported');
            }
            $dryRun = (bool) $this->option('dry-run');
            $source = $this->option('source') !== null ? JournalSource::findOrFail($this->option('source'))
                : JournalSource::where('type', 'doaj')->where('name', 'DOAJ')->first();
            if ($source !== null && ! $provider->accepts($source)) {
                throw new DomainException('provider_source_mismatch');
            }
            // Reject invalid caller keys before any outbound request or source creation.
            $key = (string) $this->option('key');
            if (! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key)) {
                throw new DomainException('invalid_batch_key');
            }
            $limit = $this->option('limit') === null ? IngestJournalRecords::MAX_RECORDS : (int) $this->option('limit');
            $transport = $this->option('transport') ?? 'api';
            if ($transport === 'csv' && $this->option('query') !== '_exists_:bibjson.title') {
                throw new DomainException('csv_query_not_supported');
            }
            $records = $transport === 'csv' ? app(DoajCsvClient::class)->records($limit)
                : app(DoajClient::class)->search((string) $this->option('query'), $limit);
            if ($records === []) {
                return ['dry_run' => $dryRun, 'records_observed' => 0, 'items' => []];
            }
            $source ??= $dryRun ? new JournalSource(['type' => 'doaj', 'name' => 'DOAJ', 'base_url' => 'https://doaj.org']) : $this->doajSource();

            return app(IngestJournalRecords::class)->ingest($provider, $source, $key, $provider->envelopes($records, $transport), $dryRun);
        }
        $file = realpath((string) $this->option('file'));
        if ($file === false || ! is_file($file) || ! is_readable($file)) {
            throw new DomainException('local_file_required');
        }
        $input = file_get_contents($file, false, null, 0, 1024 * 1024 + 1);
        if ($input === false || strlen($input) > 1024 * 1024) {
            throw new DomainException('batch_too_large');
        }
        $records = json_decode($input, true, 32, JSON_THROW_ON_ERROR);
        if (! is_array($records) || ! array_is_list($records)) {
            throw new DomainException('batch_list_required');
        }
        // Bound the selected prefix before validating or staging any provider record.
        $limit = $this->option('limit') === null ? IngestJournalRecords::MAX_RECORDS : (int) $this->option('limit');
        $records = array_slice($records, 0, $limit);

        return app(IngestJournalRecords::class)->ingest(
            $provider, JournalSource::findOrFail($this->option('source')),
            (string) $this->option('key'), $records, (bool) $this->option('dry-run'),
        );
    }

    private function stageEnrichment(CrossrefProvider|OpenAlexProvider $provider): array
    {
        $providerKey = $provider->key();
        $name = $providerKey === 'crossref' ? 'Crossref' : 'OpenAlex';
        $client = $providerKey === 'crossref' ? app(CrossrefClient::class) : app(OpenAlexClient::class);
        if ($this->option('file') !== null || $this->option('query') !== '_exists_:bibjson.title') {
            throw new DomainException($providerKey.'_identifier_lookup_only');
        }
        $key = (string) $this->option('key');
        $limit = $this->option('limit') === null ? IngestJournalRecords::MAX_RECORDS : (int) $this->option('limit');
        $identifiers = $this->option('issn');
        if (! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $key) || $identifiers === [] || count($identifiers) > $limit) {
            throw new DomainException($providerKey.'_invalid_request');
        }
        // Existing canonical/staged identity only; finish preflight before any HTTP or writes.
        foreach ($identifiers as $identifier) {
            app(ExistingJournalCandidates::class)->identifiers($identifier, $providerKey);
        }
        $source = $this->option('source') !== null ? JournalSource::findOrFail($this->option('source'))
            : JournalSource::where('type', $providerKey)->where('name', $name)->first();
        if ($source !== null && ! $provider->accepts($source)) {
            throw new DomainException('provider_source_mismatch');
        }
        $lookup = $client->lookup($identifiers, $limit);
        $dryRun = (bool) $this->option('dry-run');
        $diagnostics = ['lookups' => $lookup['lookups'], 'records_observed' => count($lookup['records']), 'not_found' => $lookup['not_found']];
        if ($lookup['records'] === []) {
            return ['dry_run' => $dryRun, ...$diagnostics, 'items' => []];
        }
        $source ??= $dryRun ? new JournalSource(['type' => $providerKey, 'name' => $name, 'base_url' => $client::BASE]) : $this->enrichmentSource($providerKey);

        return [...app(IngestJournalRecords::class)->ingest($provider, $source, $key, $provider->envelopes($lookup['records']), $dryRun), ...$diagnostics];
    }

    private function enrichmentSource(string $provider): JournalSource
    {
        return DB::transaction(function () use ($provider): JournalSource {
            DB::select('SELECT pg_advisory_xact_lock(170003)');

            return JournalSource::firstOrCreate(['type' => $provider, 'name' => $provider === 'crossref' ? 'Crossref' : 'OpenAlex'], ['base_url' => $provider === 'crossref' ? CrossrefClient::BASE : OpenAlexClient::BASE]);
        });
    }

    private function doajSource(): JournalSource
    {
        return DB::transaction(function (): JournalSource {
            DB::select('SELECT pg_advisory_xact_lock(170003)');

            return JournalSource::firstOrCreate(['type' => 'doaj', 'name' => 'DOAJ'], ['base_url' => 'https://doaj.org']);
        });
    }

    private function process(ProviderRegistry $providers): array
    {
        $run = IngestionRun::findOrFail($this->argument('id'));
        $run = app(ProcessIngestionRun::class)->process($run, $providers->get($run->provider));

        return $this->runStatus($run->fresh());
    }

    private function status(): array
    {
        return $this->runStatus(IngestionRun::findOrFail($this->argument('id')));
    }

    private function runStatus(IngestionRun $run): array
    {
        return ['run_id' => $run->id, 'status' => $run->status, 'errors' => $run->errors, 'counters' => $run->counters(),
            'items' => $run->items->unique('id')->values()->map(fn ($item) => [
                'item_id' => $item->id, 'status' => $item->status, 'errors' => $item->errors, 'conflict_ids' => $item->conflict_ids,
                'journal_id' => $item->journal_id, 'catalog_revision_id' => $item->catalog_revision_id,
                'assertions' => $item->assertions->map(fn ($claim) => ['id' => $claim->id, 'field' => $claim->field->value, 'status' => $claim->verificationStatus()->value])->all(),
            ])->all()];
    }

    private function review(): array
    {
        $review = app(ReviewIngestionAssertion::class)->review(
            JournalAssertion::findOrFail($this->argument('id')), VerificationStatus::from((string) $this->option('status')),
            (string) $this->option('actor'), (string) $this->option('method'), (string) $this->option('reason'),
        );

        return ['review_id' => $review->id, 'status' => $review->status->value];
    }

    private function publish(): array
    {
        $ids = explode(',', (string) $this->option('assertions'));
        foreach ($ids as $id) {
            if (! Str::isUuid($id)) {
                throw new DomainException('invalid_selection_ids');
            }
        }
        $revision = app(PublishIngestionItem::class)->publish(IngestionItem::findOrFail($this->argument('id')), $ids);

        return ['catalog_revision_id' => $revision->id, 'version' => $revision->version];
    }
}
