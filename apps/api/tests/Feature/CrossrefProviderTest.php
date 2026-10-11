<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\IngestionRun;
use App\Models\JournalIdentifier;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\JournalIngestion\CanonicalJson;
use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\ProcessIngestionRun;
use App\Services\JournalIngestion\Providers\Crossref\CrossrefClient;
use App\Services\JournalIngestion\Providers\Crossref\CrossrefProvider;
use App\Services\JournalIngestion\Providers\Doaj\DoajProvider;
use App\Services\JournalIngestion\PublishIngestionItem;
use App\Services\JournalIngestion\ReviewIngestionAssertion;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class CrossrefProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        config(['journal_ingestion.crossref.mailto' => '']);
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/crossref/journal.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    private function candidate(bool $process = true, string $issn = '1234-5679', ?string $eissn = '2049-3630'): IngestionRun
    {
        $record = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journal.json')), true, 32, JSON_THROW_ON_ERROR);
        $record['bibjson']['pissn'] = $issn;
        $record['bibjson']['eissn'] = $eissn;
        $record['bibjson']['publisher'] = ['name' => 'Synthetic Academic Publisher'];
        $record['id'] = md5($issn);
        $source = JournalSource::firstOrCreate(['type' => 'doaj', 'name' => 'DOAJ']);
        $provider = new DoajProvider;
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, 'candidate-'.$issn, $provider->envelopes([$record]));
        $run = IngestionRun::findOrFail($result['run_id']);
        if ($process) {
            app(ProcessIngestionRun::class)->process($run, $provider);
        }

        return $run->fresh();
    }

    private function stage(array $message, string $key = 'crossref', string $lookup = '1234-5679'): IngestionRun
    {
        $message['_lookup_issn'] = $lookup;
        $provider = new CrossrefProvider;
        $source = JournalSource::firstOrCreate(['type' => 'crossref', 'name' => 'Crossref'], ['base_url' => CrossrefClient::BASE]);
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, $key, $provider->envelopes([$message]));
        $run = IngestionRun::findOrFail($result['run_id']);
        app(ProcessIngestionRun::class)->process($run, $provider);

        return $run->fresh();
    }

    public function test_identity_mapping_provenance_and_pending_boundaries(): void
    {
        $doaj = $this->candidate();
        $record = $this->fixture();
        $run = $this->stage($record);
        $item = $run->items->first();
        $this->assertSame('pending', $item->status);
        $this->assertSame($doaj->items->first()->journal_id, $item->journal_id);
        $this->assertSame('crossref-journal-v1', $item->mapping_version);
        $this->assertSame(CanonicalJson::hash($record['message']), CanonicalJson::hash($item->payload['message']));
        $this->assertSame('1234-5679', $item->sourceRecord->external_id);
        $this->assertSame('https://api.crossref.org/journals/1234-5679', $item->sourceRecord->source_url);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/', $item->sourceRecord->content_hash);
        $this->assertSame(1700000000000, $item->payload['message']['last-status-check-time']);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertSame(['publisher', 'title'], $item->assertions()->orderBy('field')->pluck('field')->map(fn ($field) => $field->value)->all());
        foreach ($item->assertions as $claim) {
            $this->assertSame(VerificationStatus::Pending, $claim->verificationStatus());
            $this->assertSame($item->source_record_id, $claim->source_record_id);
        }
        $title = $item->assertions()->where('field', 'title')->first();
        $this->assertSame('message.title', $title->source_locator);
        $this->assertSame($record['message']['title'], $title->original_wording);
        $this->assertDatabaseCount('journal_revisions', 0);
        $this->assertDatabaseCount('catalog_revisions', 0);
        $this->expectException(DomainException::class);
        app(PublishIngestionItem::class)->publish($item, [$title->id]);
    }

    public function test_explicit_review_publishes_through_existing_revisions_only(): void
    {
        $this->candidate();
        $item = $this->stage($this->fixture())->items->first();
        $title = $item->assertions()->where('field', 'title')->first();
        app(ReviewIngestionAssertion::class)->review($title, VerificationStatus::Verified, 'operator:test', 'synthetic_review', 'Synthetic fixture checked.');
        $revision = app(PublishIngestionItem::class)->publish($item, [$title->id]);
        $this->assertNotNull($revision->published_at);
        $this->assertDatabaseCount('catalog_revisions', 1);
        $this->assertDatabaseCount('journal_revisions', 1);
        $this->assertSame($revision->id, app(PublishIngestionItem::class)->publish($item->fresh(), [$title->id])->id);
        Http::assertNothingSent();
    }

    public function test_duplicate_replays_preserve_first_observation_and_changed_payload_is_append_only(): void
    {
        $this->candidate();
        $record = $this->fixture();
        $first = $this->stage($record);
        $count = DB::table('journal_assertions')->count();
        $source = $first->items->first()->sourceRecord;
        $this->travel(1)->hours();
        $sameKey = $this->stage($record);
        $second = $this->stage($record, 'new-key');
        $this->assertSame($first->id, $sameKey->id);
        $this->assertSame($first->items->first()->id, $second->items->first()->id);
        $this->assertSame($count, DB::table('journal_assertions')->count());
        $this->assertSame($source->retrieved_at->toISOString(), $source->fresh()->retrieved_at->toISOString());
        $record['message']['title'] = 'Changed Crossref title';
        $changed = $this->stage($record, 'changed');
        $this->assertNotSame($source->id, $changed->items->first()->source_record_id);
        $this->assertSame('conflicting', $changed->items->first()->status);
        $this->assertSame($first->items->first()->payload, $first->items->first()->fresh()->payload);
        $this->assertDatabaseCount('source_records', 3);
        $this->assertSame(1, $changed->counters()['records_seen']);
        $this->expectException(DomainException::class);
        $this->stage($record, 'crossref');
    }

    public function test_eissn_and_both_lookup_identifiers_converge_without_title_matching(): void
    {
        $candidate = $this->candidate();
        Http::fake(['api.crossref.org/*' => Http::response($this->fixture(), 200)]);
        $result = app(CrossrefClient::class)->lookup(['20493630', '12345679', '2049-3630'], 3);
        $this->assertSame(2, $result['lookups']);
        $this->assertCount(2, $result['records']);
        $provider = new CrossrefProvider;
        $source = JournalSource::create(['type' => 'crossref', 'name' => 'Crossref']);
        $staged = app(IngestJournalRecords::class)->ingest($provider, $source, 'both', $provider->envelopes($result['records']));
        $run = app(ProcessIngestionRun::class)->process(IngestionRun::findOrFail($staged['run_id']), $provider);
        foreach ($run->items as $item) {
            $this->assertSame($candidate->items->first()->journal_id, $item->journal_id);
        }
        $this->assertDatabaseCount('journals', 1);
        Http::assertSentCount(2);
    }

    public function test_staged_candidate_can_be_enriched_without_doaj_processing(): void
    {
        $this->candidate(false);
        $this->assertDatabaseCount('journals', 0);
        Http::fake(['api.crossref.org/*' => Http::response($this->fixture(), 200)]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'crossref', '--issn' => ['1234-5679'], '--key' => 'staged', '--limit' => '1'])->assertSuccessful();
        $run = IngestionRun::where('provider', 'crossref')->first();
        $this->assertSame('staged', $run->items->first()->status);
        app(ProcessIngestionRun::class)->process($run, new CrossrefProvider);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public function test_missing_optional_fields_reuses_local_types_without_inventing_new_ones(): void
    {
        $this->candidate();
        $record = $this->fixture();
        unset($record['message']['issn-type'], $record['message']['publisher']);
        $record['message']['ISSN'] = ['1234-5679'];
        $item = $this->stage($record)->items->first();
        $this->assertSame('pending', $item->status);
        $this->assertCount(1, $item->assertions);
        $this->assertSame('issn', $item->normalized['identifiers'][0]['type']);
        $this->assertArrayNotHasKey('publisher', $item->normalized);
        $record['message']['ISSN'][] = '0378-5955';
        $extra = $this->stage($record, 'untyped')->items->first();
        $this->assertCount(1, $extra->normalized['identifiers']);
        $this->assertSame('0378-5955', $extra->payload['message']['ISSN'][1]);
        $this->assertDatabaseCount('journal_identifiers', 2);
    }

    public function test_same_title_different_issns_never_merges(): void
    {
        $first = $this->candidate();
        $other = $this->candidate(issn: '0378-5955', eissn: null);
        $record = $this->fixture();
        $record['message']['ISSN'] = ['0378-5955'];
        $record['message']['issn-type'] = [['value' => '0378-5955', 'type' => 'print']];
        $item = $this->stage($record, lookup: '0378-5955')->items->first();
        $this->assertSame($other->items->first()->journal_id, $item->journal_id);
        $this->assertNotSame($first->items->first()->journal_id, $item->journal_id);
        $this->assertDatabaseCount('journals', 2);
    }

    #[DataProvider('identifierConflicts')]
    public function test_identifier_conflicts_preserve_observations_without_reassignment(string $kind): void
    {
        $candidate = $this->candidate();
        $before = JournalIdentifier::orderBy('normalized_value')->get()->toArray();
        $record = $this->fixture();
        if ($kind === 'type') {
            $record['message']['issn-type'][0]['type'] = 'electronic';
            unset($record['message']['ISSN'][1], $record['message']['issn-type'][1]);
        } elseif ($kind === 'owner') {
            $this->candidate(issn: '0378-5955', eissn: null);
            $before = JournalIdentifier::orderBy('normalized_value')->get()->toArray();
            $record['message']['ISSN'] = ['1234-5679', '0378-5955'];
            $record['message']['issn-type'] = [['value' => '1234-5679', 'type' => 'print'], ['value' => '0378-5955', 'type' => 'electronic']];
        } else {
            $record['message']['ISSN'] = ['0378-5955'];
            $record['message']['issn-type'] = [['value' => '0378-5955', 'type' => 'print']];
        }
        $item = $this->stage($record)->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['crossref_identifier_conflict'], $item->errors);
        $this->assertSame(CanonicalJson::hash($record['message']), CanonicalJson::hash($item->payload['message']));
        $this->assertNotNull($item->source_record_id);
        $this->assertSame($before, JournalIdentifier::orderBy('normalized_value')->get()->toArray());
        $this->assertSame($candidate->items->first()->journal_id, $candidate->items->first()->fresh()->journal_id);
        $this->assertCount(0, $item->assertions);
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public static function identifierConflicts(): array
    {
        return [['type'], ['owner'], ['missing_lookup']];
    }

    #[DataProvider('malformedRecords')]
    public function test_malformed_safe_records_quarantine_and_unsafe_text_is_hash_only(string $kind): void
    {
        $this->candidate();
        $record = $this->fixture();
        match ($kind) {
            'title' => $record['message']['title'] = ['bad'],
            'checksum' => $record['message']['ISSN'][0] = '1234-5678',
            'nul' => $record['message']['title'] = "bad\0text",
            'nul_key' => $record['message']["bad\0key"] = 'text',
            'large' => $record['message']['extra'] = str_repeat('x', 60001),
            'types' => $record['message']['issn-type'] = 'bad',
        };
        $item = $this->stage($record)->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertCount(0, $item->assertions);
        if (in_array($kind, ['nul', 'nul_key', 'large'], true)) {
            $this->assertNull($item->payload);
            $this->assertNull($item->source_record_id);
        } else {
            $this->assertNotNull($item->source_record_id);
            $this->assertSame(CanonicalJson::hash($record['message']), CanonicalJson::hash($item->payload['message']));
        }
    }

    public static function malformedRecords(): array
    {
        return [['title'], ['checksum'], ['nul'], ['nul_key'], ['large'], ['types']];
    }

    public function test_dry_run_writes_nothing_and_does_not_follow_untrusted_links(): void
    {
        $this->candidate();
        $record = $this->fixture();
        $record['message']['URL'] = 'http://127.0.0.1/private?secret=synthetic#fragment';
        Http::fake(['api.crossref.org/*' => Http::response($record, 200)]);
        $before = [];
        foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public'") as $table) {
            $before[$table->tablename] = DB::table($table->tablename)->count();
        }
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'crossref', '--issn' => ['1234-5679'], '--key' => 'dry', '--limit' => '1', '--dry-run' => true])->assertSuccessful();
        foreach ($before as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === 'https://api.crossref.org/journals/1234-5679');
    }

    #[DataProvider('invalidCli')]
    public function test_cli_rejects_bad_or_unbounded_inputs_before_network(array $options): void
    {
        $this->candidate();
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'crossref', '--key' => 'check', ...$options])->assertFailed();
        Http::assertNothingSent();
        $this->assertDatabaseCount('ingestion_runs', 1);
    }

    public static function invalidCli(): array
    {
        return [[[]], [['--issn' => ['http://127.0.0.1']]], [['--issn' => ['0378-5955']]], [['--issn' => ['1234-5679'], '--limit' => '0']], [['--issn' => ['1234-5679', '2049-3630'], '--limit' => '1']], [['--issn' => ['1234-5679'], '--transport' => 'api']], [['--issn' => ['1234-5679'], '--file' => 'bad']], [['--issn' => ['1234-5679'], '--query' => 'title']]];
    }

    #[DataProvider('httpErrors')]
    public function test_transport_failures_are_bounded_and_sanitized(string $kind, string $error, int $calls): void
    {
        $sequence = Http::sequence();
        if ($kind === 'timeout') {
            Http::fake(['api.crossref.org/*' => Http::failedConnection('secret-token private text')]);
        } else {
            for ($i = 0; $i < $calls; $i++) {
                $sequence->push(match ($kind) {
                    'utf8' => "{\"bad\":\"\xFF\"}", 'json' => '{bad', 'shape' => ['status' => 'ok', 'message-type' => 'work'], 'large' => str_repeat('x', 1048577), default => 'secret-token private text'
                },
                    match ($kind) {
                        '429', 'retry' => 429, '5xx' => 503, 'redirect' => 302, default => 200
                    },
                    ['Content-Type' => $kind === 'html' ? 'text/html' : 'application/json', ...($kind === 'retry' ? ['Retry-After' => '60'] : [])]);
            }
            Http::fake(['api.crossref.org/*' => $sequence]);
        }
        try {
            app(CrossrefClient::class)->lookup(['1234-5679'], 1);
            $this->fail('Invalid transport must fail.');
        } catch (DomainException $exception) {
            $this->assertSame($error, $exception->getMessage());
        }
        Http::assertSentCount($calls);
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public static function httpErrors(): array
    {
        return [['utf8', 'crossref_invalid_json', 1], ['json', 'crossref_invalid_json', 1], ['shape', 'crossref_invalid_response', 1], ['html', 'crossref_not_json', 1], ['large', 'crossref_response_too_large', 1], ['429', 'crossref_rate_limited', 3], ['5xx', 'crossref_unavailable', 3], ['timeout', 'crossref_connection_failed', 3], ['retry', 'crossref_retry_deferred', 1], ['redirect', 'crossref_http_failed', 1]];
    }

    public function test_404_is_reported_without_invented_observations_and_transient_retries_recover(): void
    {
        Http::fake(['api.crossref.org/*' => Http::sequence()->push([], 404)->push([], 429, ['Retry-After' => '1'])->push([], 503)->push($this->fixture(), 200)]);
        $result = app(CrossrefClient::class)->lookup(['1234-5679', '2049-3630'], 2);
        $this->assertSame(['1234-5679'], $result['not_found']);
        $this->assertCount(1, $result['records']);
        Http::assertSentCount(4);
    }

    public function test_polite_identification_fixed_origin_tls_and_timeouts(): void
    {
        config(['journal_ingestion.crossref.mailto' => 'operator@example.test']);
        Http::fake(function ($request, $options) {
            $this->assertSame(20, $options['timeout']);
            $this->assertSame(5, $options['connect_timeout']);
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify'] ?? true);
            $this->assertSame('operator@example.test', $request['mailto']);
            $this->assertStringContainsString('mailto:operator@example.test', $request->header('User-Agent')[0]);

            return Http::response($this->fixture(), 200, ['Content-Type' => 'application/vnd.crossref-api-message+json', 'x-rate-limit-limit' => '1', 'x-rate-limit-interval' => '2s']);
        });
        $result = app(CrossrefClient::class)->lookup(['1234-5679', '2049-3630'], 2);
        $this->assertCount(2, $result['records']);
        Sleep::assertSleptTimes(1);
        config(['journal_ingestion.crossref.base_url' => 'https://localhost']);
        $this->expectException(DomainException::class);
        app(CrossrefClient::class)->lookup(['1234-5679'], 1);
    }

    public function test_unknown_candidate_cannot_bootstrap_a_catalog_and_sources_cannot_cross_bind(): void
    {
        $this->assertDatabaseCount('journals', 0);
        $item = $this->stage($this->fixture())->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertDatabaseCount('journals', 0);
        $source = JournalSource::create(['type' => 'doaj', 'name' => 'Other DOAJ']);
        $this->expectException(DomainException::class);
        app(IngestJournalRecords::class)->ingest(new CrossrefProvider, $source, 'wrong', []);
    }

    public function test_untyped_identifier_owned_elsewhere_still_reports_conflict(): void
    {
        $this->candidate();
        $this->candidate(issn: '0378-5955', eissn: null);
        $record = $this->fixture();
        $record['message']['ISSN'] = ['1234-5679', '0378-5955'];
        unset($record['message']['issn-type']);
        $item = $this->stage($record)->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['crossref_identifier_conflict'], $item->errors);
        $this->assertNotNull($item->source_record_id);
        $this->assertCount(0, $item->assertions);
        $this->assertDatabaseCount('journals', 2);
    }

    public function test_new_explicit_typed_identifier_enriches_existing_identity_pending_only(): void
    {
        $candidate = $this->candidate(eissn: null);
        $item = $this->stage($this->fixture())->items->first();
        $this->assertSame($candidate->items->first()->journal_id, $item->journal_id);
        $this->assertSame('pending', $item->status);
        $this->assertSame(1, $item->assertions()->where('field', 'identifier')->count());
        $this->assertSame(VerificationStatus::Pending, JournalIdentifier::where('type', 'eissn')->first()->assertion->verificationStatus());
        $this->assertDatabaseCount('journal_revisions', 0);
    }

    public function test_publisher_and_title_disagreement_preserves_both_sources_and_working_values(): void
    {
        $candidate = $this->candidate();
        $old = $candidate->items->first();
        $title = $old->journal->title;
        $claims = $old->assertions()->pluck('id')->all();
        $record = $this->fixture();
        $record['message']['publisher'] = 'Different bibliographic publisher';
        $record['message']['title'] = 'Different Crossref title';
        $item = $this->stage($record)->items->first();
        $this->assertSame('conflicting', $item->status);
        $this->assertSame($title, $old->journal->fresh()->title);
        $this->assertSame($claims, $old->assertions()->pluck('id')->all());
        $this->assertCount(2, $item->conflict_ids);
        foreach ($item->assertions as $claim) {
            $this->assertSame(VerificationStatus::Conflicting, $claim->verificationStatus());
        }
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public function test_provider_scoped_external_keys_never_share_observations(): void
    {
        $candidate = $this->candidate();
        $crossref = $this->stage($this->fixture());
        $doaj = $candidate->items->first()->sourceRecord;
        $other = $crossref->items->first()->sourceRecord;
        $collision = SourceRecord::create([
            'journal_source_id' => $doaj->journal_source_id, 'external_id' => $other->external_id,
            'revision_fingerprint' => $other->revision_fingerprint, 'source_url' => $doaj->source_url,
            'retrieved_at' => now(), 'content_hash' => $other->content_hash, 'status' => 'captured',
        ]);
        $this->assertNotSame($collision->id, $other->id);
        $this->assertNotSame($collision->journal_source_id, $other->journal_source_id);
        $this->assertDatabaseCount('source_records', 3);
    }

    public function test_404_cli_has_no_writes_and_wrong_source_fails_before_http(): void
    {
        $this->candidate();
        Http::fake(['api.crossref.org/*' => Http::response([], 404)]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'crossref', '--issn' => ['1234-5679'], '--key' => 'missing'])->assertSuccessful();
        $this->assertDatabaseCount('journal_sources', 1);
        $this->assertDatabaseCount('ingestion_runs', 1);
        Http::assertSentCount(1);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'crossref', '--issn' => ['1234-5679'], '--source' => JournalSource::first()->id, '--key' => 'wrong'])->assertFailed();
        Http::assertSentCount(1);
    }

    public function test_semantic_partial_failure_counters_and_source_history_are_preserved(): void
    {
        $this->candidate();
        $provider = new CrossrefProvider;
        $source = JournalSource::create(['type' => 'crossref', 'name' => 'Crossref']);
        $good = $this->fixture();
        $good['_lookup_issn'] = '1234-5679';
        $bad = $good;
        $bad['message']['title'] = [];
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, 'partial', $provider->envelopes([$good, $bad]));
        $run = app(ProcessIngestionRun::class)->process(IngestionRun::findOrFail($result['run_id']), $provider);
        $this->assertSame('completed', $run->status);
        $this->assertSame(['records_seen' => 2, 'records_staged' => 1, 'records_quarantined' => 1, 'records_unchanged' => 0, 'records_failed' => 0], $run->counters());
        $this->assertSame(['pending', 'quarantined'], $run->items->pluck('status')->all());
        $this->assertDatabaseCount('source_records', 3);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public function test_network_failure_after_success_never_persists_a_partial_acquisition(): void
    {
        $this->candidate();
        Http::fake(['api.crossref.org/*' => Http::sequence()->push($this->fixture(), 200)->push([], 503)->push([], 503)->push([], 503)]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'crossref', '--issn' => ['1234-5679', '2049-3630'], '--key' => 'partial-network'])->assertFailed();
        $this->assertDatabaseCount('ingestion_runs', 1);
        $this->assertDatabaseCount('source_records', 1);
        $this->assertDatabaseCount('journal_sources', 1);
        Http::assertSentCount(4);
    }

    public function test_publication_rollback_preserves_evidence_and_has_no_receipt(): void
    {
        $this->candidate();
        $item = $this->stage($this->fixture())->items->first();
        $title = $item->assertions()->where('field', 'title')->first();
        $publisher = $item->assertions()->where('field', 'publisher')->first();
        app(ReviewIngestionAssertion::class)->review($title, VerificationStatus::Verified, 'operator:test', 'synthetic_review', 'Checked.');
        $count = DB::table('journal_assertions')->count();
        try {
            app(PublishIngestionItem::class)->publish($item, [$title->id, $publisher->id]);
            $this->fail('Unreviewed publisher must reject publication.');
        } catch (DomainException) {
            $this->assertDatabaseCount('journal_revisions', 0);
            $this->assertDatabaseCount('catalog_revisions', 0);
            $this->assertSame($count, DB::table('journal_assertions')->count());
            $this->assertNull($item->fresh()->catalog_revision_id);
        }
    }

    public function test_documented_singleton_issn_type_object_is_supported(): void
    {
        $this->candidate(eissn: null);
        $record = $this->fixture();
        $record['message']['ISSN'] = ['1234-5679'];
        $record['message']['issn-type'] = ['value' => '1234-5679', 'type' => 'print'];
        $this->assertSame('pending', $this->stage($record)->items->first()->status);
    }

    public function test_official_json_accept_negotiation_does_not_request_unsupported_vendor_media_type(): void
    {
        Http::fake(function ($request) {
            if ($request->header('Accept') !== ['application/json']) {
                return Http::response('Not acceptable', 406);
            }

            return Http::response($this->fixture(), 200, ['Content-Type' => 'application/vnd.crossref-api-message+json']);
        });
        $lookup = app(CrossrefClient::class)->lookup(['1234-5679'], 1);
        $this->assertCount(1, $lookup['records']);
        Http::assertSentCount(1);
    }

    public function test_acquisition_batch_size_is_bounded_before_collecting_large_responses(): void
    {
        $record = $this->fixture();
        $record['message']['extra'] = str_repeat('x', 600000);
        Http::fake(['api.crossref.org/*' => Http::response($record, 200)]);
        try {
            app(CrossrefClient::class)->lookup(['1234-5679', '2049-3630'], 2);
            $this->fail('Aggregate response bytes must be bounded.');
        } catch (DomainException $exception) {
            $this->assertSame('crossref_batch_too_large', $exception->getMessage());
        }
        Http::assertSentCount(2);
        $this->assertDatabaseCount('source_records', 0);
    }
}
