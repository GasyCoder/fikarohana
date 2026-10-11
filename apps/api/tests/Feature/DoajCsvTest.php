<?php

namespace Tests\Feature;

use App\Models\IngestionRun;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\JournalIngestion\CanonicalJson;
use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\ProcessIngestionRun;
use App\Services\JournalIngestion\Providers\Doaj\DoajCsvClient;
use App\Services\JournalIngestion\Providers\Doaj\DoajCsvParser;
use App\Services\JournalIngestion\Providers\Doaj\DoajProvider;
use App\Services\JournalIngestion\SyntheticProvider;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class DoajCsvTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function csv(): string
    {
        return file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journals.csv'));
    }

    private function row(): array
    {
        return $this->parse($this->csv(), 1)[0]['_csv_row'];
    }

    private function encode(array $rows): string
    {
        $stream = fopen('php://temp', 'w+');
        fputcsv($stream, array_keys($rows[0]), ',', '"', '');
        foreach ($rows as $row) {
            fputcsv($stream, array_values($row), ',', '"', '');
        }
        rewind($stream);
        $csv = stream_get_contents($stream);
        fclose($stream);

        return $csv;
    }

    private function parse(string $csv, int $limit = 100): array
    {
        $stream = fopen('php://temp', 'w+');
        fwrite($stream, $csv);
        rewind($stream);
        try {
            return app(DoajCsvParser::class)->parse($stream, $limit);
        } finally {
            fclose($stream);
        }
    }

    private function stage(array $records, string $key = 'csv-test', bool $process = true): IngestionRun
    {
        $source = JournalSource::firstOrCreate(['type' => 'doaj', 'name' => 'DOAJ'], ['base_url' => 'https://doaj.org']);
        $provider = new DoajProvider;
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, $key, $provider->envelopes($records, 'csv'));
        $run = IngestionRun::findOrFail($result['run_id']);
        if ($process) {
            app(ProcessIngestionRun::class)->process($run, $provider);
        }

        return $run->fresh();
    }

    public function test_csv_api_parity_provenance_and_pending_review_boundary(): void
    {
        $records = $this->parse($this->csv(), 1);
        $provider = new DoajProvider;
        $csv = $provider->envelopes($records, 'csv')[0];
        $api = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journal.json')), true);
        $this->assertSame($provider->normalize($api), $provider->normalize($csv['payload']));
        $run = $this->stage($records, process: false);
        $item = $run->items()->first();
        $this->assertSame(CanonicalJson::hash($csv['payload']), CanonicalJson::hash($item->payload));
        $this->assertSame('csv', $item->payload['_acquisition']['transport']);
        $this->assertSame('doaj', $run->provider);
        $this->assertSame('doaj-v4-csv-v3', $item->mapping_version);
        $this->assertSame(DoajCsvClient::SOURCE, $item->sourceRecord->source_url);
        $this->assertSame(str_repeat('a', 32), $item->sourceRecord->external_id);
        $this->assertNotNull($item->sourceRecord->retrieved_at);
        $this->assertMatchesRegularExpression('/^[a-f0-9]{64}$/D', $item->payload['_csv_fingerprint']);
        $this->assertDatabaseCount('journal_assertions', 0);
        app(ProcessIngestionRun::class)->process($run, $provider);
        $item = $item->fresh();
        $this->assertSame('pending', $item->status);
        $this->assertSame('_csv_row.Journal title', $item->assertions()->where('field', 'title')->first()->source_locator);
        $this->assertDatabaseHas('publishers', ['country_code' => 'MG']);
        $this->assertDatabaseCount('journal_languages', 2);
        $this->assertDatabaseCount('journal_subjects', 1);
        $this->assertDatabaseCount('assertion_reviews', 0);
        $this->assertDatabaseCount('journal_revisions', 0);
        $this->assertDatabaseCount('catalog_revisions', 0);
        Http::assertNothingSent();
    }

    public static function variants(): array
    {
        return ['print' => ['print'], 'electronic' => ['electronic'], 'both' => ['both'], 'positive APC' => ['positive'],
            'no APC' => ['no'], 'unknown APC' => ['unknown'], 'multiple currencies' => ['multiple'],
            'waiver' => ['waiver'], 'missing optional' => ['optional'], 'no DOAJ ID' => ['no-id'],
            'formula is text' => ['formula'], 'hierarchical subject' => ['subject']];
    }

    #[DataProvider('variants')]
    public function test_variants_keep_unknown_absence_waiver_and_identity_distinct(string $case): void
    {
        $row = $this->row();
        match ($case) {
            'print' => $row['Journal EISSN (online version)'] = '',
            'electronic' => $row['Journal ISSN (print version)'] = '',
            'no' => [$row['APC'], $row['APC amount']] = ['No', ''],
            'unknown' => [$row['APC'], $row['APC amount']] = ['', ''],
            'multiple' => $row['APC amount'] = '1200 USD; 1100 EUR',
            'waiver' => $row['Journal waiver policy (for developing country authors etc)'] = 'Yes',
            'optional' => $row = ['Journal title' => $row['Journal title'], 'Journal ISSN (print version)' => '1234-5679'],
            'no-id' => $row['URL in DOAJ'] = '',
            'formula' => $row['Journal title'] = '=HYPERLINK("https://evil.example.test")',
            'subject' => [$row['LCC Codes'], $row['Subjects']] = ['K|KZ2-6785', 'Law: Law of nations'],
            default => null,
        };
        $run = $this->stage($this->parse($this->encode([$row])));
        $item = $run->items()->first();
        $this->assertSame('pending', $item->status);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('journal_identifiers', in_array($case, ['print', 'electronic', 'optional'], true) ? 1 : 2);
        if ($case === 'no') {
            $this->assertSame('unknown', $item->normalized['fees'][0]['availability']);
            $this->assertSame('doaj_reported_no_apc', $item->normalized['fees'][0]['basis']);
            $this->assertNull($item->normalized['fees'][0]['amount']);
        } elseif (in_array($case, ['unknown', 'optional', 'multiple'], true)) {
            $this->assertSame('unknown', $item->normalized['fees'][0]['availability']);
            $this->assertSame('doaj_reported_maximum', $item->normalized['fees'][0]['basis']);
        } else {
            $this->assertSame('known', $item->normalized['fees'][0]['availability']);
        }
        if ($case === 'subject') {
            $this->assertDatabaseHas('subjects', ['name' => 'DOAJ LCC: KZ2-6785 — Law of nations']);
        }
        Http::assertNothingSent();
    }

    public static function badRows(): array
    {
        return ['invalid UTF8' => ['utf8'], 'NUL' => ['nul'], 'missing cell' => ['short'],
            'unclosed quote' => ['quote'], 'unknown language' => ['language'], 'bad APC' => ['apc'],
            'contradictory APC' => ['conflict'], 'unsafe URL' => ['url']];
    }

    #[DataProvider('badRows')]
    public function test_malformed_rows_quarantine_safely_and_preserve_neighbors(string $case): void
    {
        $row = $this->row();
        match ($case) {
            'utf8' => $row['Journal title'] = "invalid\xFFtext",
            'nul' => $row['Journal title'] = "invalid\0text",
            'language' => $row['Languages in which the journal accepts manuscripts'] = 'Unknown Language',
            'apc' => $row['APC amount'] = '100',
            'conflict' => $row['APC'] = 'No',
            'url' => $row['Journal URL'] = 'https://user:secret@example.test',
            default => null,
        };
        $csv = $this->encode([$this->row(), $row, $this->row()]);
        if ($case === 'short') {
            $lines = explode("\n", $csv);
            $lines[2] = 'missing,cells';
            $csv = implode("\n", $lines);
        } elseif ($case === 'quote') {
            // An unterminated record consumes the remaining bytes; the earlier row survives.
            $csv = $this->encode([$this->row()]).'"unterminated';
        }
        $run = $this->stage($this->parse($csv));
        $this->assertSame('completed', $run->status);
        if ($case === 'url') {
            $this->assertSame(0, $run->counters()['records_quarantined']);
            $this->assertSame(2, $run->items()->get()->unique('id')->count());
            Http::assertNothingSent();

            return;
        }
        $this->assertSame(1, $run->counters()['records_quarantined']);
        $this->assertDatabaseCount('journals', 1);
        $quarantined = $run->items()->where('status', 'quarantined')->firstOrFail();
        if (in_array($case, ['utf8', 'nul'], true)) {
            $this->assertNull($quarantined->payload);
            $this->assertSame(['postgres_incompatible_text'], $quarantined->errors);
        }
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public static function metadataUrls(): array
    {
        return ['query' => ['https://journal.example.test/path?lang=fr', true],
            'fragment' => ['https://journal.example.test/path#instructions', true],
            'both' => ['https://journal.example.test/path?lang=fr#instructions', true],
            'percent encoding and path parameters' => ['https://journal.example.test/a%20b;edition=2?q=a%2Fb#part%201', true],
            'javascript' => ['javascript:alert(1)', false], 'file' => ['file:///etc/passwd', false],
            'data' => ['data:text/html,hello', false], 'malformed' => ['https://', false],
            'credentials' => ['https://user:secret@journal.example.test/path', false],
            'control' => ["https://journal.example.test/path\nextra", false]];
    }

    #[DataProvider('metadataUrls')]
    public function test_metadata_urls_are_preserved_but_never_authorize_fetching(string $url, bool $mapped): void
    {
        $row = $this->row();
        $row['Journal URL'] = $url;
        // Unmapped links also remain evidence rather than invalidating identity/title.
        $row['APC information URL'] = 'https://journal.example.test/fees?lang=fr#apc';
        $records = $this->parse($this->encode([$row]));
        $run = $this->stage($records);
        $item = $run->items()->firstOrFail();
        $this->assertSame('pending', $item->status);
        $this->assertSame($url, $item->payload['_csv_row']['Journal URL']);
        $this->assertSame(DoajCsvClient::SOURCE, $item->sourceRecord->source_url);
        if ($mapped) {
            $this->assertSame($url, $item->normalized['website']);
            $this->assertSame($url, $item->assertions()->where('field', 'website')->firstOrFail()->text_value);
        } else {
            $this->assertArrayNotHasKey('website', $item->normalized);
            $this->assertSame(0, $item->assertions()->where('field', 'website')->count());
        }
        // Exercise API mapping through the same validator/materializer as well.
        $api = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journal.json')), true);
        $api['bibjson']['ref']['journal'] = $url;
        $api['bibjson']['ref']['author_instructions'] = 'https://journal.example.test/guide?format=full#abstract';
        $provider = new DoajProvider;
        $result = app(IngestJournalRecords::class)->ingest($provider, $run->source, 'api-url', $provider->envelopes([$api]));
        $apiRun = IngestionRun::findOrFail($result['run_id']);
        app(ProcessIngestionRun::class)->process($apiRun, $provider);
        $apiItem = $apiRun->items()->firstOrFail();
        $this->assertSame('pending', $apiItem->status);
        $this->assertSame($url, $apiItem->payload['bibjson']['ref']['journal']);
        $this->assertSame($mapped, array_key_exists('website', $apiItem->normalized));
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('catalog_revisions', 0);
        Http::assertNothingSent();
    }

    public function test_http_client_only_requests_official_csv_even_with_arbitrary_metadata_urls(): void
    {
        $row = $this->row();
        $row['Journal URL'] = 'https://journal.example.test/article?id=1#guide';
        Http::fake([DoajCsvClient::SOURCE => Http::response($this->encode([$row]), 200, ['Content-Type' => 'text/csv'])]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--transport' => 'csv', '--key' => 'url-boundary', '--limit' => '1', '--dry-run' => true])
            ->expectsOutputToContain('"status":"valid"')->assertExitCode(0);
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->url() === DoajCsvClient::SOURCE);
        $this->assertDatabaseCount('source_records', 0);
    }

    public function test_duplicate_changed_rows_and_api_identity_preserve_history(): void
    {
        $records = $this->parse($this->csv());
        $run = $this->stage($records);
        $count = DB::table('journal_assertions')->count();
        $retrieved = SourceRecord::first()->retrieved_at;
        $this->travel(1)->minutes();
        $this->assertSame($run->id, $this->stage($records)->id);
        $this->stage($records, 'again');
        $this->assertDatabaseCount('source_records', 1);
        $this->assertDatabaseCount('journal_assertions', $count);
        $this->assertTrue($retrieved->equalTo(SourceRecord::first()->retrieved_at));
        $row = $this->row();
        $row['Journal title'] = 'Changed synthetic title';
        $changed = $this->stage($this->parse($this->encode([$row])), 'changed');
        $this->assertSame('conflicting', $changed->items()->first()->status);
        $api = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journal.json')), true);
        $provider = new DoajProvider;
        $result = app(IngestJournalRecords::class)->ingest($provider, $run->source, 'api', $provider->envelopes([$api]));
        app(ProcessIngestionRun::class)->process(IngestionRun::findOrFail($result['run_id']), $provider);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('source_records', 3);
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertSame('api', IngestionRun::find($result['run_id'])->items()->first()->payload['_acquisition']['transport']);
        $this->assertNotNull($run->items()->first()->payload['_csv_row']);
    }

    public function test_explicit_no_apc_conflicts_with_positive_amount_in_both_directions(): void
    {
        $row = $this->row();
        $paid = $this->stage($this->parse($this->encode([$row])));
        $row['APC'] = 'No';
        $row['APC amount'] = '';
        $free = $this->stage($this->parse($this->encode([$row])), 'no-apc');
        $this->assertSame('conflicting', $free->items()->first()->status);
        $this->assertContains($paid->items()->first()->assertions()->where('field', 'fee')->first()->id, $free->items()->first()->conflict_ids);
        $row['APC'] = 'Yes';
        $row['APC amount'] = '1300 USD';
        $changed = $this->stage($this->parse($this->encode([$row])), 'paid-again');
        $this->assertContains($free->items()->first()->assertions()->where('field', 'fee')->first()->id, $changed->items()->first()->conflict_ids);
        $this->assertDatabaseCount('source_records', 3);
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public function test_csv_source_namespace_does_not_collide_with_other_provider(): void
    {
        $run = $this->stage($this->parse($this->csv(), 1));
        $source = JournalSource::factory()->create(['type' => 'other']);
        $envelope = ['external_id' => str_repeat('a', 32), 'source_url' => 'https://example.test/source', 'retrieved_at' => now()->format('Y-m-d\TH:i:s\Z'),
            'payload' => ['title' => 'Other provider', 'identifiers' => [['type' => 'issn', 'value' => '1234-5679']]]];
        app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $source, 'other', [$envelope]);
        $this->assertDatabaseCount('source_records', 2);
        $this->assertSame('doaj', $run->provider);
    }

    public function test_download_and_parser_limits_are_validated_before_io(): void
    {
        foreach ([0, 101] as $limit) {
            try {
                app(DoajCsvClient::class)->records($limit);
                $this->fail('Unbounded limit cannot succeed.');
            } catch (DomainException $exception) {
                $this->assertSame('doaj_csv_invalid_limit', $exception->getMessage());
            }
        }
        config(['journal_ingestion.doaj_csv.timeout' => 121]);
        try {
            app(DoajCsvClient::class)->records(1);
            $this->fail('Unbounded timeout cannot succeed.');
        } catch (DomainException $exception) {
            $this->assertSame('doaj_csv_invalid_configuration', $exception->getMessage());
        }
        Http::assertNothingSent();
    }

    public function test_csv_download_occurs_outside_additional_database_transactions(): void
    {
        Http::fake(function () {
            $this->assertSame(1, DB::transactionLevel());

            return Http::response($this->csv(), 200, ['Content-Type' => 'text/csv']);
        });
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--transport' => 'csv', '--key' => 'stage', '--limit' => '1'])->assertExitCode(0);
        $this->assertDatabaseCount('source_records', 1);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_client_download_redirect_limit_and_cli_zero_write_dry_run(): void
    {
        Http::fakeSequence()->push('', 307, ['Location' => 'https://doaj-live-journal-csv.s3.amazonaws.com/doaj_journalcsv_20260910_2320_utf8.csv?X-Amz-Signature=synthetic'])
            ->push($this->csv(), 200, ['Content-Type' => 'binary/octet-stream']);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--transport' => 'csv', '--key' => 'csv-pilot', '--limit' => '1', '--dry-run' => true])
            ->expectsOutputToContain('"status":"valid"')->assertExitCode(0);
        foreach (['journal_sources', 'source_records', 'ingestion_runs', 'ingestion_items', 'journals', 'journal_assertions', 'catalog_revisions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Http::assertSentCount(2);
        $this->assertSame(DoajCsvClient::SOURCE, Http::recorded()[0][0]->url());
        $this->assertCount(1, $this->parse($this->csv(), 1));
    }

    public static function unsafeRedirects(): array
    {
        return [['http://127.0.0.1/private'], ['https://doaj-live-journal-csv.s3.amazonaws.com.evil.test/doaj_journalcsv_20260910_2320_utf8.csv'],
            ['https://doaj-live-journal-csv.s3.amazonaws.com/private.csv'], ['https://user:secret@doaj-live-journal-csv.s3.amazonaws.com/doaj_journalcsv_20260910_2320_utf8.csv']];
    }

    #[DataProvider('unsafeRedirects')]
    public function test_arbitrary_redirects_are_rejected(string $url): void
    {
        Http::fake(['*' => Http::response('', 307, ['Location' => $url])]);
        try {
            app(DoajCsvClient::class)->records(1);
            $this->fail('Unsafe destination cannot be requested.');
        } catch (DomainException $exception) {
            $this->assertSame('doaj_csv_unsafe_redirect', $exception->getMessage());
        }
        Http::assertSentCount(1);
    }

    public static function badDownloads(): array
    {
        return ['403' => ['secret', 403, 'text/csv', 'doaj_csv_http_failed'],
            'HTML' => ['<html>', 200, 'text/html', 'doaj_csv_invalid_format'],
            'too large' => [null, 200, 'text/csv', 'doaj_csv_download_too_large'],
            'bad header' => ["Title,wrong\nx,y\n", 200, 'text/csv', 'doaj_csv_invalid_header'],
            'malformed quoted header' => ["\"Journal title\"x,Journal ISSN (print version)\na,b\n", 200, 'text/csv', 'doaj_csv_invalid_header'],
            'invalid header UTF8' => ["Journal title,Journal ISSN (print version),invalid\xFF\na,b,c\n", 200, 'text/csv', 'postgres_incompatible_text'],
            'duplicate header' => ["Journal title,Journal title,Journal ISSN (print version)\na,b,c\n", 200, 'text/csv', 'doaj_csv_invalid_header']];
    }

    #[DataProvider('badDownloads')]
    public function test_downloads_fail_with_sanitized_codes(?string $body, int $status, string $mime, string $code): void
    {
        config(['journal_ingestion.doaj_csv.max_download_bytes' => 1024]);
        Http::fake(['*' => Http::response($body ?? str_repeat('x', 1025), $status, ['Content-Type' => $mime])]);
        try {
            app(DoajCsvClient::class)->records(1);
            $this->fail('Unsafe CSV cannot succeed.');
        } catch (DomainException $exception) {
            $this->assertSame($code, $exception->getMessage());
        }
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public function test_connection_failure_is_sanitized_and_does_not_create_source(): void
    {
        Http::fake(['*' => Http::failedConnection('private-signed-url')]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'doaj', '--transport' => 'csv', '--key' => 'failed', '--limit' => '1'])
            ->expectsOutput('Ingestion operation failed; inspect staged status/error codes and command arguments.')->assertExitCode(1);
        $this->assertDatabaseCount('journal_sources', 0);
        Http::assertSentCount(1);
    }

    public function test_invalid_transport_limit_or_csv_query_never_requests_network(): void
    {
        foreach ([['--transport' => 'other'], ['--limit' => '101'], ['--query' => 'issn:1234-5679']] as $extra) {
            $this->artisan('journals:ingestion', array_replace(['action' => 'stage', '--provider' => 'doaj', '--transport' => 'csv', '--key' => 'invalid'], $extra))->assertExitCode(isset($extra['--limit']) ? 2 : 1);
        }
        Http::assertNothingSent();
    }
}
