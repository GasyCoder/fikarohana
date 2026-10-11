<?php

namespace Tests\Feature;

use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\IngestionRun;
use App\Models\JournalAssertion;
use App\Models\JournalLanguage;
use App\Models\JournalSource;
use App\Models\Language;
use App\Models\SourceRecord;
use App\Services\JournalIngestion\CanonicalJson;
use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\ProcessIngestionRun;
use App\Services\JournalIngestion\Providers\Doaj\DoajClient;
use App\Services\JournalIngestion\Providers\Doaj\DoajProvider;
use App\Services\JournalIngestion\PublishIngestionItem;
use App\Services\JournalIngestion\ReviewIngestionAssertion;
use App\Services\JournalIngestion\SyntheticProvider;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Sleep;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DoajProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
    }

    private function record(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journal.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    private function page(array $records, int $size = 1, int $page = 1, ?int $total = null): array
    {
        return ['total' => $total ?? count($records), 'page' => $page, 'pageSize' => $size, 'results' => $records,
            'next' => 'http://127.0.0.1/never-follow'];
    }

    private function stage(array $records, string $key = 'doaj-test', bool $process = true): IngestionRun
    {
        $source = JournalSource::firstOrCreate(['type' => 'doaj', 'name' => 'DOAJ'], ['base_url' => 'https://doaj.org']);
        $provider = new DoajProvider;
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, $key, $provider->envelopes($records));
        $run = IngestionRun::findOrFail($result['run_id']);
        if ($process) {
            app(ProcessIngestionRun::class)->process($run, $provider);
        }

        return $run->fresh();
    }

    public function test_valid_mapping_retains_raw_provenance_and_only_pending_field_claims(): void
    {
        $record = $this->record();
        $run = $this->stage([$record], process: false);
        $item = $run->items()->first();
        $this->assertSame(CanonicalJson::hash($record), CanonicalJson::hash(array_diff_key($item->payload, ['_acquisition' => true])));
        $this->assertDatabaseCount('journal_assertions', 0);
        app(ProcessIngestionRun::class)->process($run, new DoajProvider);
        $item = $item->fresh();
        $this->assertSame('pending', $item->status);
        $this->assertSame('doaj-v4-csv-v3', $item->mapping_version);
        $this->assertSame('https://doaj.org/api/v4/journals/'.$record['id'], $item->sourceRecord->source_url);
        $this->assertSame($record['id'], $item->sourceRecord->external_id);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $item->sourceRecord->content_hash);
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertDatabaseCount('journal_languages', 2);
        $this->assertDatabaseCount('journal_subjects', 1);
        $this->assertDatabaseHas('publishers', ['name' => 'Synthetic Academic Publisher', 'country_code' => 'MG']);
        $this->assertDatabaseHas('subjects', ['name' => 'DOAJ LCC: Q — Science']);
        $this->assertDatabaseHas('journal_fees', ['amount' => '1200.0000', 'currency' => 'USD', 'basis' => 'doaj_reported_maximum']);
        $this->assertDatabaseCount('journal_indexations', 0);
        $this->assertDatabaseCount('journal_templates', 0);
        $this->assertDatabaseCount('assertion_reviews', 0);
        $this->assertSame(0, $item->assertions()->where('field', 'country')->count());
        $this->assertSame(1, $item->assertions()->where('field', 'publisher')->count());
        $title = $item->assertions()->where('field', 'title')->firstOrFail();
        $this->assertSame('bibjson.title', $title->source_locator);
        $this->assertSame($record['bibjson']['title'], $title->original_wording);
        $this->assertSame('bibjson.publisher.name', $item->assertions()->where('field', 'publisher')->firstOrFail()->source_locator);
        foreach ($item->assertions as $assertion) {
            $this->assertSame($item->source_record_id, $assertion->source_record_id);
            $this->assertSame(VerificationStatus::Pending, $assertion->verificationStatus());
        }
        $this->assertDatabaseCount('journal_revisions', 0);
        $this->assertDatabaseCount('catalog_revisions', 0);
        Http::assertNothingSent();
    }

    public static function identifiers(): array
    {
        return ['print only' => ['pissn', 1], 'electronic only' => ['eissn', 1], 'both' => [null, 2]];
    }

    #[DataProvider('identifiers')]
    public function test_identifier_variants(?string $only, int $count): void
    {
        $record = $this->record();
        if ($only !== null) {
            unset($record['bibjson'][$only === 'pissn' ? 'eissn' : 'pissn']);
        }
        $run = $this->stage([$record]);
        $this->assertSame('completed', $run->status);
        $this->assertDatabaseCount('journal_identifiers', $count);
    }

    public static function apcs(): array
    {
        return [
            'missing' => [null, 'unknown', null, null],
            'no APC' => [['has_apc' => false], 'unknown', null, null],
            'unknown amount' => [['has_apc' => true], 'unknown', null, null],
            'explicit zero' => [['has_apc' => true, 'max' => [['price' => 0, 'currency' => 'EUR']]], 'known', '0.0000', 'EUR'],
            'paid maximum' => [['has_apc' => true, 'max' => [['price' => 150, 'currency' => 'EUR']]], 'known', '150.0000', 'EUR'],
            'multiple currencies' => [['has_apc' => true, 'max' => [['price' => 100, 'currency' => 'USD'], ['price' => 90, 'currency' => 'EUR']]], 'unknown', null, null],
        ];
    }

    #[DataProvider('apcs')]
    public function test_apc_semantics(?array $apc, string $availability, ?string $amount, ?string $currency): void
    {
        $record = $this->record();
        unset($record['bibjson']['apc']);
        if ($apc !== null) {
            $record['bibjson']['apc'] = $apc;
        }
        $run = $this->stage([$record]);
        $claim = $run->items()->first()->assertions()->where('field', 'fee')->firstOrFail();
        $this->assertSame($availability, $claim->availability->value);
        $this->assertSame($amount, $claim->fee->amount);
        $this->assertSame($currency, $claim->fee->currency);
        $this->assertSame(($apc['has_apc'] ?? null) === false ? 'doaj_reported_no_apc' : 'doaj_reported_maximum', $claim->fee->basis);
        $this->assertSame('api', $run->items()->first()->payload['_acquisition']['transport']);
        $this->assertSame(CanonicalJson::hash($record), CanonicalJson::hash(array_diff_key($run->items()->first()->payload, ['_acquisition' => true])));
    }

    public function test_missing_optional_fields_do_not_invent_descriptive_claims(): void
    {
        $record = ['id' => str_repeat('b', 32), 'bibjson' => ['title' => 'Minimal synthetic journal', 'eissn' => '2049-3630']];
        $run = $this->stage([$record]);
        $this->assertSame('completed', $run->status);
        $this->assertSame(['fee', 'identifier', 'title'], $run->items()->first()->assertions()->orderBy('field')->pluck('field')->map(fn ($field) => $field->value)->all());
        $this->assertDatabaseCount('journal_languages', 0);
        $this->assertDatabaseCount('journal_subjects', 0);
        $this->assertDatabaseCount('publishers', 0);
    }

    public static function malformed(): array
    {
        return ['missing title' => ['title'], 'missing identifiers' => ['identifiers'], 'old schema' => ['old'],
            'wrong language' => ['language'], 'APC string flag' => ['flag'], 'APC decimal' => ['decimal'], 'invalid subject' => ['subject'],
            'withdrawn' => ['withdrawn'], 'unsafe URL' => ['url'], 'invalid ID' => ['id']];
    }

    #[DataProvider('malformed')]
    public function test_malformed_record_does_not_erase_valid_neighbor(string $case): void
    {
        $bad = $this->record();
        $bad['id'] = str_repeat('b', 32);
        match ($case) {
            'title' => $bad['bibjson']['title'] = null,
            'identifiers' => $bad['bibjson'] = ['title' => 'No identifiers'],
            'old' => $bad['bibjson'] = ['title' => 'Old schema', 'identifier' => [['type' => 'pissn', 'id' => '1234-5679']]],
            'language' => $bad['bibjson']['language'] = ['French'],
            'flag' => $bad['bibjson']['apc']['has_apc'] = 'false',
            'decimal' => $bad['bibjson']['apc']['max'][0]['price'] = 12.5,
            'subject' => $bad['bibjson']['subject'] = [['term' => 'No scheme']],
            'withdrawn' => $bad['admin']['in_doaj'] = false,
            'url' => $bad['bibjson']['ref']['journal'] = 'https://user:secret@example.test',
            'id' => $bad['id'] = '../unsafe',
        };
        $run = $this->stage([$this->record(), $bad]);
        if ($case === 'url') {
            $this->assertSame(['pending', 'pending'], $run->items()->get()->pluck('status')->all());
            $this->assertArrayNotHasKey('website', $run->items()->get()[1]->normalized);
            $this->assertSame($bad['bibjson']['ref']['journal'], $run->items()->get()[1]->payload['bibjson']['ref']['journal']);

            return;
        }
        $this->assertSame(['pending', 'quarantined'], $run->items()->get()->pluck('status')->all());
        $this->assertSame('completed', $run->status);
        $this->assertSame(1, $run->counters()['records_quarantined']);
        $this->assertDatabaseCount('journals', 1);
    }

    public function test_nul_external_input_is_quarantined_between_valid_records_without_retention(): void
    {
        $bad = $this->record();
        $bad['bibjson']['extra'] = ['nested' => "unsafe-fragment\0never-retain"];
        $third = ['id' => str_repeat('c', 32), 'bibjson' => ['title' => 'Other journal', 'pissn' => '0378-5955']];
        $run = $this->stage([$this->record(), $bad, $third]);
        $items = $run->items()->get();
        $this->assertSame(['pending', 'quarantined', 'pending'], $items->pluck('status')->all());
        $this->assertNull($items[1]->payload);
        $this->assertNull($items[1]->source_record_id);
        $this->assertSame(['postgres_incompatible_text'], $items[1]->errors);
        $this->assertStringNotContainsString('unsafe-fragment', json_encode($items[1]->getAttributes()));
        $this->assertDatabaseCount('journals', 2);
        $this->assertDatabaseCount('source_records', 2);
    }

    public function test_repeat_import_and_changed_payload_are_auditable_even_when_retrieval_time_changes(): void
    {
        $record = $this->record();
        $one = $this->stage([$record]);
        $count = JournalAssertion::count();
        $firstRetrieved = SourceRecord::first()->retrieved_at;
        $this->travel(1)->minutes();
        $replay = $this->stage([$record]);
        $this->assertSame($one->id, $replay->id);
        $this->stage([$record], 'new-run');
        $this->assertDatabaseCount('source_records', 1);
        $this->assertDatabaseCount('journal_assertions', $count);
        $this->assertTrue($firstRetrieved->equalTo(SourceRecord::first()->retrieved_at));
        $record['bibjson']['title'] = 'Changed title';
        $changed = $this->stage([$record], 'changed');
        $this->assertDatabaseCount('source_records', 2);
        $this->assertSame('conflicting', $changed->items()->first()->status);
        $this->assertDatabaseCount('journals', 1);
        $this->assertGreaterThan($count, JournalAssertion::count());
    }

    public function test_provider_scoping_identifier_conflicts_and_no_title_only_merge(): void
    {
        $record = $this->record();
        $this->stage([$record]);
        $other = ['id' => str_repeat('b', 32), 'bibjson' => ['title' => $record['bibjson']['title'], 'pissn' => '0378-5955']];
        $this->stage([$other], 'same-title');
        $this->assertDatabaseCount('journals', 2);
        $collision = $record;
        $collision['id'] = str_repeat('c', 32);
        $collision['bibjson']['eissn'] = '0378-5955';
        $run = $this->stage([$collision], 'collision');
        $this->assertSame('quarantined', $run->items()->first()->status);
        $this->assertSame(['identifier_owner_conflict'], $run->items()->first()->errors);
        $source = JournalSource::factory()->create(['type' => 'other']);
        $synthetic = ['external_id' => $record['id'], 'source_url' => 'https://example.test/source', 'retrieved_at' => now()->format('Y-m-d\TH:i:s\Z'),
            'payload' => ['title' => 'Other provider', 'identifiers' => [['type' => 'issn', 'value' => '1234-5679']]]];
        app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $source, 'other-provider', [$synthetic]);
        $this->assertDatabaseCount('source_records', 4);
    }

    public function test_review_publication_boundary_uses_existing_revisions(): void
    {
        $run = $this->stage([$this->record()]);
        $item = $run->items()->first();
        $title = $item->assertions()->where('field', JournalField::Title)->firstOrFail();
        try {
            app(PublishIngestionItem::class)->publish($item, [$title->id]);
            $this->fail('An imported title must not be auto-verified.');
        } catch (DomainException) {
            $this->assertDatabaseCount('catalog_revisions', 0);
        }
        app(ReviewIngestionAssertion::class)->review($title, VerificationStatus::Verified, 'operator-test', 'manual_fixture', 'Synthetic schema fixture review.');
        $revision = app(PublishIngestionItem::class)->publish($item, [$title->id]);
        $this->assertSame($revision->id, app(PublishIngestionItem::class)->publish($item->fresh(), [$title->id])->id);
        $this->assertDatabaseCount('journal_revisions', 1);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_pagination_constructs_fixed_origin_requests_and_obeys_limit(): void
    {
        config(['journal_ingestion.doaj.page_size' => 2]);
        $a = $this->record();
        $b = $a;
        $b['id'] = str_repeat('b', 32);
        $c = $a;
        $c['id'] = str_repeat('c', 32);
        Http::fakeSequence()->push($this->page([$a, $b], 2, 1, 4))->push($this->page([$c, $a], 2, 2, 4));
        $records = app(DoajClient::class)->search('issn:1234-5679', 4);
        $this->assertCount(4, $records);
        Http::assertSentCount(2);
        foreach (Http::recorded() as [$request]) {
            $this->assertStringStartsWith('https://doaj.org/api/v4/search/journals/issn%3A1234-5679?', $request->url());
            $this->assertSame('2', (string) $request['pageSize']);
            $this->assertTrue($request->hasHeader('User-Agent', 'Fikarohana/0.1 (controlled journal ingestion)'));
        }
        $this->assertSame('2', (string) Http::recorded()[1][0]['page']);
    }

    public function test_cli_doaj_is_explicit_and_dry_run_performs_no_database_writes(): void
    {
        Http::fakeSequence()->push($this->page([$this->record()]))->push($this->page([$this->record()]));
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--key' => 'pilot', '--limit' => '1', '--dry-run' => true])->assertExitCode(0);
        foreach (['journal_sources', 'ingestion_source_providers', 'ingestion_runs', 'ingestion_items', 'source_records', 'journal_assertions', 'journal_revisions', 'catalog_revisions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--key' => 'pilot', '--limit' => '1'])->assertExitCode(0);
        $this->assertDatabaseCount('journal_sources', 1);
        $this->assertDatabaseCount('ingestion_runs', 1);
        $this->assertDatabaseCount('journals', 0);
        Http::assertSentCount(2);
        Http::assertSent(fn ($request) => $request['pageSize'] === 1);
    }

    public function test_invalid_cli_limits_fail_before_network_or_database_writes(): void
    {
        foreach (['0', '-1', '1.5', 'word', '101'] as $limit) {
            $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--key' => 'pilot', '--limit' => $limit])->assertExitCode(2);
        }
        $this->assertDatabaseCount('journal_sources', 0);
        Http::assertNothingSent();
    }

    public function test_default_limit_is_100_and_empty_results_create_no_run(): void
    {
        config(['journal_ingestion.doaj.page_size' => 100]);
        Http::fake(['*' => Http::response($this->page([], 100))]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--key' => 'empty'])->assertExitCode(0);
        Http::assertSent(fn ($request) => $request['pageSize'] === 100);
        $this->assertDatabaseCount('journal_sources', 0);
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public static function transient(): array
    {
        return ['429' => [429], '500' => [500], '502' => [502], '503' => [503], '504' => [504]];
    }

    #[DataProvider('transient')]
    public function test_transient_http_failure_retries_boundedly(int $status): void
    {
        Http::fakeSequence()->push(['private' => 'never-log'], $status)->push($this->page([$this->record()]));
        $this->assertCount(1, app(DoajClient::class)->search('science', 1));
        Http::assertSentCount(2);
    }

    public function test_timeout_retry_exhaustion_returns_sanitized_error(): void
    {
        Http::fake(['*' => Http::failedConnection('secret-token-in-error')]);
        try {
            app(DoajClient::class)->search('science', 1);
            $this->fail('Timeouts cannot succeed.');
        } catch (DomainException $exception) {
            $this->assertSame('doaj_connection_failed', $exception->getMessage());
        }
        Http::assertSentCount(3);
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public static function failures(): array
    {
        return ['429 exhausted' => [429, 'doaj_rate_limited'], '503 exhausted' => [503, 'doaj_unavailable'],
            '400 no retry' => [400, 'doaj_http_failed'], '401 no retry' => [401, 'doaj_http_failed'],
            '403 no retry' => [403, 'doaj_http_failed'], 'redirect denied' => [302, 'doaj_http_failed']];
    }

    #[DataProvider('failures')]
    public function test_http_failures_have_fixed_codes_and_no_response_body(int $status, string $code): void
    {
        Http::fake(['*' => Http::response('secret-unexpected-body', $status, ['Location' => 'http://localhost/private'])]);
        try {
            app(DoajClient::class)->search('science', 1);
            $this->fail('HTTP failure must be rejected.');
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
        Http::assertSentCount(in_array($status, [429, 503], true) ? 3 : 1);
    }

    public function test_retry_after_long_delay_defers_instead_of_retrying_early(): void
    {
        Http::fake(['*' => Http::response([], 429, ['Retry-After' => '120'])]);
        try {
            app(DoajClient::class)->search('science', 1);
            $this->fail('Long Retry-After cannot be ignored.');
        } catch (DomainException $exception) {
            $this->assertSame('doaj_retry_deferred', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public static function invalidResponses(): array
    {
        return ['invalid JSON' => ['{secret', 'application/json', 'doaj_invalid_json'],
            'wrong shape' => ['{"results":[]}', 'application/json', 'doaj_invalid_page'],
            'object results' => ['{"total":0,"page":1,"pageSize":1,"results":{}}', 'application/json', 'doaj_invalid_page'],
            'wrong MIME' => ['{}', 'text/html', 'doaj_not_json'],
            'oversized' => [null, 'application/json', 'doaj_response_too_large']];
    }

    #[DataProvider('invalidResponses')]
    public function test_invalid_response_fails_safely(?string $body, string $mime, string $code): void
    {
        Http::fake(['*' => Http::response($body ?? str_repeat('x', 1048577), 200, ['Content-Type' => $mime])]);
        try {
            app(DoajClient::class)->search('science', 1);
            $this->fail('Malformed responses must fail.');
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
        Http::assertSentCount(1);
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public static function unsafeBases(): array
    {
        return [['http://doaj.org/api/v4'], ['https://doaj.org.evil.test/api/v4'], ['https://127.0.0.1/api/v4'], ['https://doaj.org/api/v4/../private']];
    }

    #[DataProvider('unsafeBases')]
    public function test_configuration_cannot_turn_client_into_arbitrary_fetcher(string $base): void
    {
        config(['journal_ingestion.doaj.base_url' => $base]);
        $this->expectException(DomainException::class);
        try {
            app(DoajClient::class)->search('science', 1);
        } finally {
            Http::assertNothingSent();
        }
    }

    public function test_provider_failure_cli_returns_nonzero_without_persisting_and_outside_db_transaction(): void
    {
        Http::fake(function ($request) {
            $this->assertSame(1, DB::transactionLevel());

            return Http::response('credential-in-unexpected-response', 403);
        });
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--limit' => '1', '--key' => 'failure'])
            ->expectsOutput('Ingestion operation failed; inspect staged status/error codes and command arguments.')->assertExitCode(1);
        $this->assertDatabaseCount('journal_sources', 0);
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public function test_publisher_and_language_conflicts_are_visible_in_preview_and_preserved_after_processing(): void
    {
        $record = $this->record();
        $run = $this->stage([$record]);
        $item = $run->items()->first();
        $language = Language::where('code', 'fr')->firstOrFail();
        $unsupported = JournalAssertion::create(['journal_id' => $item->journal_id, 'source_record_id' => $item->source_record_id, 'field' => 'language']);
        JournalLanguage::create(['journal_id' => $item->journal_id, 'assertion_id' => $unsupported->id, 'language_id' => $language->id, 'supported' => false]);
        $oldPublisher = $item->assertions()->where('field', 'publisher')->firstOrFail();
        $record['bibjson']['publisher']['name'] = 'Different Synthetic Publisher';
        $provider = new DoajProvider;
        $preview = app(IngestJournalRecords::class)->ingest($provider, $run->source, 'preview', $provider->envelopes([$record]), true);
        $this->assertSame('conflicting', $preview['items'][0]['status']);
        $this->assertContains($oldPublisher->id, $preview['items'][0]['conflict_ids']);
        $this->assertContains($unsupported->id, $preview['items'][0]['conflict_ids']);
        $changed = $this->stage([$record], 'conflict');
        $this->assertSame('conflicting', $changed->items()->first()->status);
        $this->assertSame(VerificationStatus::Pending, $oldPublisher->fresh()->verificationStatus());
        $this->assertSame(VerificationStatus::Pending, $unsupported->fresh()->verificationStatus());
        $this->assertDatabaseCount('source_records', 2);
    }

    public function test_cli_three_record_batch_preserves_neighbors_of_a_nul_record(): void
    {
        $bad = $this->record();
        $bad['bibjson']['title'] = "unsafe\0fragment";
        $third = ['id' => str_repeat('b', 32), 'bibjson' => ['title' => 'Another journal', 'eissn' => '0378-5955']];
        Http::fake(['*' => Http::response($this->page([$this->record(), $bad, $third], 3))]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--limit' => '3', '--key' => 'mixed'])->assertExitCode(0);
        $run = IngestionRun::firstOrFail();
        $this->assertSame(['staged', 'quarantined', 'staged'], $run->items()->get()->pluck('status')->all());
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertExitCode(0);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertDatabaseCount('journals', 2);
        $this->assertDatabaseCount('source_records', 2);
        $this->assertDatabaseCount('catalog_revisions', 0);
        Http::assertSentCount(1);
    }

    public function test_prime_limit_never_fetches_more_records_than_requested(): void
    {
        config(['journal_ingestion.doaj.page_size' => 2]);
        Http::fake(function ($request) {
            $this->assertSame(1, $request['pageSize']);

            return Http::response($this->page([$this->record()], 1, $request['page'], 3));
        });
        $this->assertCount(3, app(DoajClient::class)->search('science', 3));
        Http::assertSentCount(3);
    }

    public function test_maximum_limit_fetches_at_most_100_records(): void
    {
        config(['journal_ingestion.doaj.page_size' => 100]);
        Http::fake(['*' => Http::response($this->page(array_fill(0, 100, $this->record()), 100))]);
        $this->assertCount(100, app(DoajClient::class)->search('science', 100));
        Http::assertSentCount(1);
    }
}
