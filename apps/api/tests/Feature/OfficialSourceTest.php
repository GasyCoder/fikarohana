<?php

namespace Tests\Feature;

use App\Models\Journal;
use App\Models\JournalAssertion;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\OfficialSources\OfficialSourceClient;
use App\Services\OfficialSources\OfficialSources;
use App\Services\OfficialSources\OfficialUrl;
use App\Services\OfficialSources\PublicDns;
use DomainException;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\RequestException;
use GuzzleHttp\Handler\CurlFactory;
use GuzzleHttp\Handler\CurlHandler;
use GuzzleHttp\Handler\EasyHandle;
use GuzzleHttp\Handler\ProxyEnv;
use GuzzleHttp\Psr7\Response;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Client\Factory;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use Illuminate\Support\Sleep;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OfficialSourceTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://journal.example.org/guidelines';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        $this->mock(PublicDns::class, function ($mock): void {
            $mock->shouldReceive('resolve')->andReturn(['93.184.215.14']);
            $mock->shouldReceive('validate')->andReturnUsing(fn ($ips) => (new PublicDns)->validate($ips));
        });
    }

    private function journal(): Journal
    {
        return Journal::create(['title' => 'Synthetic journal']);
    }

    private function source(bool $approved = true): JournalSource
    {
        $source = app(OfficialSources::class)->register($this->journal(), 'official_journal', 'guidelines', self::URL);

        return $approved ? app(OfficialSources::class)->approve($source, 'reviewer-001', 'Synthetic ownership, access, robots and internal retention review.') : $source;
    }

    private function body(): string
    {
        return file_get_contents(base_path('tests/Fixtures/official-sources/guidelines.html'));
    }

    private function resetHttp(): void
    {
        // Laravel accumulates fake callbacks; each independent scenario needs a fresh factory.
        Http::swap(new Factory);
        Http::preventStrayRequests();
    }

    private function response(string $body = '', array $headers = []): void
    {
        $this->resetHttp();
        Http::fake([self::URL => Http::response($body === '' ? $this->body() : $body, 200,
            $headers + ['Content-Type' => 'text/html; charset=utf-8'])]);
    }

    private function counts(): array
    {
        $counts = [];
        foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
            $counts[$table->tablename] = DB::table($table->tablename)->count();
        }

        return $counts;
    }

    private function fails(callable $operation, string $code): void
    {
        try {
            $operation();
            $this->fail('Operation must fail safely.');
        } catch (DomainException $e) {
            $this->assertSame($code, $e->getMessage());
        }
    }

    #[DataProvider('invalidUrls')]
    public function test_untrusted_urls_cannot_register(string $url): void
    {
        $before = $this->counts();
        try {
            app(OfficialUrl::class)->normalize($url);
            $this->fail('Unsafe URL accepted.');
        } catch (DomainException $e) {
            $this->assertMatchesRegularExpression('/^official_invalid_(url|host)$/', $e->getMessage());
        }
        $this->assertSame($before, $this->counts());
        Http::assertNothingSent();
    }

    public static function invalidUrls(): array
    {
        return array_map(fn ($url) => [$url], ['http://journal.example.org/', 'https://localhost/',
            'https://127.0.0.1/', 'https://[::1]/', 'https://10.0.0.1/', 'https://172.16.0.1/',
            'https://192.168.1.1/', 'https://169.254.169.254/', 'https://169.254.1.2/', 'file:///etc/passwd',
            'data:text/plain,test', 'javascript:alert(1)', 'https://user:password@journal.example.org/',
            'https://journal.example.org:8443/', 'https://journal.example.org/?token=synthetic',
            'https://journal.example.org/#fragment', 'https://2130706433/', 'https://0x7f000001/',
            'https://journal.local/', 'https://journal.internal/', 'https://journal.example.org./',
            'https://journal.example.org\@localhost/', "https://journal.example.org/\n", 'https://[::ffff:127.0.0.1]/']);
    }

    #[DataProvider('prohibitedIps')]
    public function test_dns_answers_reject_nonpublic_ranges(string $ip): void
    {
        $this->fails(fn () => (new PublicDns)->validate(['93.184.215.14', $ip]), 'official_prohibited_address');
    }

    public static function prohibitedIps(): array
    {
        return array_map(fn ($ip) => [$ip], ['127.0.0.1', '10.0.0.1', '172.16.0.1', '192.168.1.1', '169.254.169.254',
            '100.100.100.200', '0.0.0.0', '192.0.0.1', '198.18.0.1', '224.0.0.1', '255.255.255.255',
            '::1', '::', 'fc00::1', 'fe80::1', 'ff02::1', '::ffff:127.0.0.1', '::ffff:8.8.8.8', '2002:7f00:1::',
            '2001:db8::1', '2001::1', '3fff::1', '64:ff9b::a00:1']);
    }

    public function test_native_resolver_uses_bounded_shell_free_process_and_validates_answers(): void
    {
        Process::fake([
            '*' => Process::result(output: '["8.8.8.8","2606:4700:4700::1111"]'),
        ]);
        $this->assertSame(['8.8.8.8', '2606:4700:4700::1111'], (new PublicDns)->resolve('journal.example.org'));
        Process::assertRan(function ($process) {
            $this->assertSame(5, $process->timeout);
            $this->assertIsArray($process->command);
            $this->assertSame('journal.example.org', $process->command[3]);
            $this->assertSame(PHP_BINARY, $process->command[0]);

            return true;
        });
    }

    public function test_native_resolver_failure_output_is_sanitized(): void
    {
        Process::fake([
            '*' => Process::result(output: 'sensitive invalid output', errorOutput: 'synthetic secret', exitCode: 1),
        ]);
        $this->fails(fn () => (new PublicDns)->resolve('journal.example.org'), 'official_dns_failed');
    }

    public function test_public_ipv4_and_ipv6_and_empty_dns_policy(): void
    {
        $dns = new PublicDns;
        $this->assertSame(['8.8.8.8', '2606:4700:4700::1111'], $dns->validate(['8.8.8.8', '2606:4700:4700::1111', '8.8.8.8']));
        $this->fails(fn () => $dns->validate([]), 'official_dns_failed');
        $this->fails(fn () => $dns->validate(array_fill(0, 33, '8.8.8.8')), 'official_dns_failed');
    }

    public function test_registration_is_idempotent_and_does_not_approve_or_fetch(): void
    {
        $journal = $this->journal();
        $service = app(OfficialSources::class);
        $first = $service->register($journal, 'official_publisher', 'fees', 'HTTPS://JOURNAL.EXAMPLE.ORG/fees');
        $second = $service->register($journal, 'official_publisher', 'fees', 'https://journal.example.org/fees');
        $this->assertSame($first->id, $second->id);
        $this->assertSame('candidate', $first->approval_status);
        $this->assertSame($journal->id, $first->official_journal_id);
        $this->assertSame('https://journal.example.org', $first->official_origin);
        $this->assertNotNull($first->first_observed_at);
        $this->assertNull($first->approved_at);
        $this->assertDatabaseCount('journal_sources', 1);
        $this->assertDatabaseCount('source_records', 0);
        $this->fails(fn () => $service->register($journal, 'official_journal', 'fees', $first->official_url), 'official_registration_conflict');
        Http::assertNothingSent();
    }

    public function test_approval_is_explicit_and_unapproved_source_cannot_fetch(): void
    {
        $source = $this->source(false);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_not_approved');
        $this->assertNull($source->fresh()->last_checked_at);
        $approved = app(OfficialSources::class)->approve($source, 'reviewer-001', 'Synthetic review reference');
        $this->assertSame('approved', $approved->approval_status);
        $this->assertSame('reviewer-001', $approved->approved_by);
        $this->assertNotNull($approved->approved_at);
        app(OfficialSources::class)->deactivate($approved);
        $this->fails(fn () => app(OfficialSources::class)->fetch($approved), 'official_not_approved');
        Http::assertNothingSent();
    }

    public function test_private_dns_blocks_approval_and_later_rebinding_blocks_fetch(): void
    {
        $source = $this->source();
        $this->instance(PublicDns::class, new class extends PublicDns
        {
            public function resolve(string $host): array
            {
                return ['10.0.0.1'];
            }
        });
        $this->app->forgetInstance(OfficialSources::class);
        $this->app->forgetInstance(OfficialSourceClient::class);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_prohibited_address');
        $this->assertSame('official_prohibited_address', $source->fresh()->last_outcome);
        $candidate = app(OfficialSources::class)->register($this->journal(), 'official_journal', 'website', self::URL);
        $this->fails(fn () => app(OfficialSources::class)->approve($candidate, 'reviewer', 'Synthetic review'), 'official_prohibited_address');
        $this->assertSame('candidate', $candidate->fresh()->approval_status);
        Http::assertNothingSent();
    }

    public function test_first_snapshot_is_internal_immutable_provenance_without_publication(): void
    {
        $source = $this->source();
        $level = DB::transactionLevel();
        $this->resetHttp();
        Http::fake(function ($request, $options) use ($level) {
            $this->assertSame($level, DB::transactionLevel());
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify']);
            $this->assertSame('', $options['proxy']);
            $this->assertFalse($options['cookies']);
            $this->assertFalse($options['decode_content']);
            $this->assertSame(['journal.example.org:443:93.184.215.14'], $options['curl'][CURLOPT_RESOLVE]);
            $this->assertTrue($options['curl'][CURLOPT_FRESH_CONNECT]);
            $this->assertTrue($options['curl'][CURLOPT_FORBID_REUSE]);
            $this->assertSame('GET', $request->method());
            $this->assertFalse($request->hasHeader('Authorization'));
            $this->assertFalse($request->hasHeader('Cookie'));

            return Http::response($this->body(), 200, ['Content-Type' => 'text/html', 'Set-Cookie' => 'secret=synthetic-cookie', 'X-Tracking' => 'synthetic-tracker']);
        });
        $result = app(OfficialSources::class)->fetch($source);
        $record = SourceRecord::findOrFail($result['source_record_id']);
        $this->assertSame($source->id, $record->journal_source_id);
        $this->assertSame(self::URL, $record->source_url);
        $this->assertSame(hash('sha256', $this->body()), $record->content_hash);
        $this->assertSame($this->body(), $record->raw_content);
        $this->assertSame('text/html', $record->snapshot_metadata['content_type']);
        $this->assertSame(strlen($this->body()), $record->snapshot_metadata['bytes']);
        $this->assertStringNotContainsString('synthetic-cookie', json_encode($record->snapshot_metadata));
        $this->assertStringNotContainsString('synthetic-tracker', json_encode($record->snapshot_metadata));
        $this->assertArrayNotHasKey('raw_content', $record->toArray());
        $this->assertArrayNotHasKey('body', $result);
        foreach (['journal_assertions', 'assertion_reviews', 'journal_revisions', 'catalog_revisions', 'ingestion_runs'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        Http::assertSentCount(1); // Script, image and template links remain inert.
        Sleep::assertSequence([Sleep::for(30)->seconds()]);
        try {
            DB::transaction(fn () => DB::table('source_records')->where('id', $record->id)->update(['raw_content' => 'changed']));
            $this->fail('Snapshot must be immutable.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $this->assertSame($this->body(), $record->fresh()->raw_content);
    }

    public function test_identical_changed_and_reverted_content_preserve_original_evidence(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fakeSequence()->push($this->body(), 200, ['Content-Type' => 'text/html'])
            ->push($this->body(), 200, ['Content-Type' => 'text/html'])
            ->push('<html><body>Changed synthetic evidence</body></html>', 200, ['Content-Type' => 'text/html'])
            ->push($this->body(), 200, ['Content-Type' => 'text/html']);
        $service = app(OfficialSources::class);
        $first = $service->fetch($source);
        $retrieved = SourceRecord::find($first['source_record_id'])->retrieved_at;
        $this->travel(1)->hours();
        $same = $service->fetch($source);
        $this->assertSame('unchanged', $same['change']);
        $this->assertSame($first['source_record_id'], $same['source_record_id']);
        $this->assertTrue(SourceRecord::find($first['source_record_id'])->retrieved_at->equalTo($retrieved));
        $this->assertDatabaseCount('source_records', 1);
        $changed = $service->fetch($source);
        $this->assertNotSame($first['source_record_id'], $changed['source_record_id']);
        $this->assertSame('changed', $changed['change']);
        $reverted = $service->fetch($source);
        $this->assertSame($first['source_record_id'], $reverted['source_record_id']);
        $this->assertSame('changed', $reverted['change']);
        $this->assertDatabaseCount('source_records', 2);
        Http::assertSentCount(4);
    }

    public function test_conditionals_and_304_preserve_snapshot_and_first_retrieval(): void
    {
        $source = $this->source();
        $this->response(headers: ['ETag' => '"synthetic-v1"', 'Last-Modified' => 'Mon, 12 Oct 2026 08:00:00 GMT']);
        $service = app(OfficialSources::class);
        $first = $service->fetch($source);
        $retrieval = SourceRecord::find($first['source_record_id'])->retrieved_at;
        $this->travel(1)->hours();
        $this->resetHttp();
        Http::fake(function ($request) {
            $this->assertSame(['"synthetic-v1"'], $request->header('If-None-Match'));
            $this->assertSame(['Mon, 12 Oct 2026 08:00:00 GMT'], $request->header('If-Modified-Since'));

            return Http::response('', 304);
        });
        $result = $service->fetch($source);
        $this->assertSame('not_modified', $result['outcome']);
        $this->assertSame($first['source_record_id'], $result['source_record_id']);
        $this->assertDatabaseCount('source_records', 1);
        $this->assertTrue(SourceRecord::find($first['source_record_id'])->retrieved_at->equalTo($retrieval));
        $this->assertTrue($source->fresh()->last_checked_at->greaterThan($retrieval));
    }

    public function test_unexpected_304_is_not_evidence(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fakeSequence()->push('', 304);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_unexpected_304');
        $this->assertDatabaseCount('source_records', 0);
    }

    public function test_dry_run_registration_approval_fetch_and_failures_write_nothing(): void
    {
        $source = $this->source();
        $before = $this->counts();
        $original = $source->fresh()->getAttributes();
        $service = app(OfficialSources::class);
        $service->register($this->journal(), 'official_journal', 'fees', 'https://journal.example.org/fees', true);
        // The helper-created journal is intentional; compare after it exists.
        $before['journals']++;
        $service->approve($source, 'reviewer', 'Synthetic review', true);
        $this->response();
        $result = $service->fetch($source, true);
        $this->assertTrue($result['dry_run']);
        $this->assertArrayNotHasKey('source_record_id', $result);
        $this->resetHttp();
        Http::fakeSequence()->push('Sensitive error', 403);
        $this->fails(fn () => $service->fetch($source, true), 'official_forbidden');
        $this->assertSame($before, $this->counts());
        $this->assertSame($original, $source->fresh()->getAttributes());
    }

    #[DataProvider('redirectFailures')]
    public function test_redirect_destinations_are_never_implicitly_approved(string $target): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake([self::URL => Http::response('', 302, ['Location' => $target])]);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_redirect_disallowed');
        Http::assertSentCount(1);
        $this->assertDatabaseCount('source_records', 0);
    }

    public static function redirectFailures(): array
    {
        return [['https://127.0.0.1/'], ['https://localhost/'], ['https://10.0.0.1/'], ['https://[::1]/'],
            ['https://169.254.169.254/'], ['https://other.example.org/page'], ['http://journal.example.org/'],
            ['https://journal.example.org/?secret=synthetic'], ['file:///etc/passwd']];
    }

    public function test_same_origin_redirect_is_bounded_revalidated_and_retained(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake([self::URL => Http::response('', 302, ['Location' => '/final']),
            'https://journal.example.org/final' => Http::response($this->body(), 200, ['Content-Type' => 'text/html'])]);
        $result = app(OfficialSources::class)->fetch($source);
        $record = SourceRecord::find($result['source_record_id']);
        $this->assertSame(self::URL, $record->source_url);
        $this->assertSame('https://journal.example.org/final', $record->snapshot_metadata['final_url']);
        Http::assertSentCount(2);
    }

    public function test_dns_is_revalidated_at_each_redirect(): void
    {
        $source = $this->source();
        $this->instance(PublicDns::class, new class extends PublicDns
        {
            private int $calls = 0;

            public function resolve(string $host): array
            {
                return ++$this->calls === 1 ? ['8.8.8.8'] : ['10.0.0.1'];
            }
        });
        $this->resetHttp();
        Http::fake([self::URL => Http::response('', 302, ['Location' => '/final'])]);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_prohibited_address');
        Http::assertSentCount(1);
    }

    public function test_redirect_loop_and_hop_limit(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake([self::URL => Http::response('', 302, ['Location' => '/guidelines'])]);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_redirect_loop');
        $this->resetHttp();
        Http::fakeSequence()->push('', 302, ['Location' => '/one'])->push('', 302, ['Location' => '/two'])->push('', 302, ['Location' => '/three']);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_too_many_redirects');
    }

    #[DataProvider('httpFailures')]
    public function test_http_failure_diagnostics_preserve_prior_evidence(int $status, string $code): void
    {
        $source = $this->source();
        $this->response();
        $service = app(OfficialSources::class);
        $service->fetch($source);
        $before = SourceRecord::first()->getAttributes();
        $this->resetHttp();
        Http::fakeSequence()->push('Sensitive giant error body with synthetic credentials', $status);
        $this->fails(fn () => $service->fetch($source), $code);
        $this->assertSame($code, $source->fresh()->last_outcome);
        $this->assertSame($before, SourceRecord::first()->getAttributes());
        $this->assertDatabaseCount('source_records', 1);
    }

    public static function httpFailures(): array
    {
        return [[403, 'official_forbidden'], [404, 'official_not_found'], [429, 'official_rate_limited'],
            [500, 'official_unavailable'], [502, 'official_unavailable'], [503, 'official_unavailable'],
            [504, 'official_unavailable'], [401, 'official_http_failed']];
    }

    #[DataProvider('contentFailures')]
    public function test_invalid_content_is_not_retained(string $body, array $headers, string $code): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fakeSequence()->push($body, 200, $headers);
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), $code);
        $this->assertSame($code, $source->fresh()->last_outcome);
        $this->assertDatabaseCount('source_records', 0);
    }

    public static function contentFailures(): array
    {
        return [['garbage', ['Content-Type' => 'text/html'], 'official_malformed_html'],
            ["bad\xff", ['Content-Type' => 'text/plain'], 'postgres_incompatible_text'],
            ["bad\0", ['Content-Type' => 'text/plain'], 'postgres_incompatible_text'],
            ['%PDF synthetic', ['Content-Type' => 'application/pdf'], 'official_unsupported_mime'],
            ['<html></html>', ['Content-Type' => 'text/html', 'Content-Encoding' => 'gzip'], 'official_unsupported_encoding'],
            ['text', ['Content-Type' => 'text/plain; charset=iso-8859-1'], 'official_unsupported_charset'],
            ['', ['Content-Type' => 'text/plain'], 'official_empty_content'],
            [str_repeat('x', 262145), ['Content-Type' => 'text/plain'], 'official_response_too_large'],
            ['small', ['Content-Type' => 'text/plain', 'Content-Length' => '262145'], 'official_response_too_large']];
    }

    public function test_transport_failure_is_sanitized_and_manual_retry_succeeds(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake(fn () => throw new ConnectionException('TLS/DNS/timeout details and synthetic secret'));
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_transport_failed');
        $this->assertSame('official_transport_failed', $source->fresh()->last_outcome);
        $this->response('Plain synthetic content', ['Content-Type' => 'text/plain']);
        $result = app(OfficialSources::class)->fetch($source);
        $this->assertSame('text/plain', $result['content_type']);
        $this->assertDatabaseCount('source_records', 1);
    }

    public function test_real_database_guards_protect_identity_approval_and_journal_attribution(): void
    {
        $source = $this->source();
        $this->response();
        $record = SourceRecord::find(app(OfficialSources::class)->fetch($source)['source_record_id']);
        $other = $this->journal();
        foreach ([['official_journal_id' => $other->id], ['official_url' => 'https://journal.example.org/other'],
            ['approved_by' => 'someone-else'], ['approval_status' => 'candidate']] as $change) {
            try {
                DB::transaction(fn () => DB::table('journal_sources')->where('id', $source->id)->update($change));
                $this->fail('Registry mutation accepted.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        try {
            DB::transaction(fn () => JournalAssertion::create(['journal_id' => $other->id, 'source_record_id' => $record->id,
                'field' => 'title', 'text_value' => 'Wrong journal']));
            $this->fail('Cross-journal attribution accepted.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        try {
            DB::transaction(fn () => $source->delete());
            $this->fail('Evidence source deletion accepted.');
        } catch (QueryException $e) {
            $this->assertSame('23503', $e->errorInfo[0]);
        }
        $this->assertDatabaseCount('source_records', 1);
    }

    public function test_snapshot_and_operational_metadata_roll_back_atomically(): void
    {
        $source = $this->source();
        $this->response();
        DB::unprepared("CREATE FUNCTION test_official_failure() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.last_source_record_id IS NOT NULL THEN RAISE EXCEPTION 'Synthetic failure' USING ERRCODE='23514'; END IF; RETURN NEW; END; $$; CREATE TRIGGER test_official_failure BEFORE UPDATE ON journal_sources FOR EACH ROW EXECUTE FUNCTION test_official_failure();");
        try {
            app(OfficialSources::class)->fetch($source);
            $this->fail('Injected PostgreSQL failure did not occur.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        } finally {
            DB::unprepared('DROP TRIGGER test_official_failure ON journal_sources; DROP FUNCTION test_official_failure();');
        }
        $this->assertDatabaseCount('source_records', 0);
        $this->assertNull($source->fresh()->last_source_record_id);
        app(OfficialSources::class)->fetch($source);
        $this->assertDatabaseCount('source_records', 1);
    }

    public function test_empty_rollback_reapplies_and_populated_rollback_fails_closed(): void
    {
        $migration = require database_path('migrations/2026_10_12_100000_add_official_source_foundation.php');
        DB::transaction(function () use ($migration): void {
            $migration->down();
            $migration->up();
        });
        $source = $this->source();
        try {
            DB::transaction(fn () => $migration->down());
            $this->fail('Populated registry rollback accepted.');
        } catch (LogicException $e) {
            $this->assertStringContainsString('history must be preserved', $e->getMessage());
        }
        $this->assertSame($source->id, $source->fresh()->id);
    }

    public function test_dry_run_candidate_approval_and_registration_have_zero_writes(): void
    {
        $source = $this->source(false);
        $journal = $this->journal();
        $before = $this->counts();
        $service = app(OfficialSources::class);
        $candidate = $service->register($journal, 'official_publisher', 'template', 'https://journal.example.org/template', true);
        $this->assertFalse($candidate->exists);
        $service->approve($source, 'reviewer', 'Synthetic ownership/robots/rights review', true);
        $this->assertSame('candidate', $source->fresh()->approval_status);
        $this->assertSame($before, $this->counts());
        Http::assertNothingSent();
    }

    public function test_low_level_header_and_progress_guards_abort_before_persistence(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake(function ($request, $options) {
            ($options['on_headers'])(new Response(200, ['Content-Length' => '999999999']));

            return Http::response('Must not be received');
        });
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_response_too_large');
        $this->resetHttp();
        Http::fake(function ($request, $options) {
            ($options['progress'])(0, OfficialSourceClient::MAX_BYTES + 1);

            return Http::response('Must not be received');
        });
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_response_too_large');
        $this->assertDatabaseCount('source_records', 0);
    }

    public function test_dns_failure_is_safe_and_dry_run_preserves_operational_state(): void
    {
        $source = $this->source();
        $this->instance(PublicDns::class, new class extends PublicDns
        {
            public function resolve(string $host): array
            {
                throw new DomainException('official_dns_failed');
            }
        });
        $original = $source->fresh()->getAttributes();
        $this->fails(fn () => app(OfficialSources::class)->fetch($source, true), 'official_dns_failed');
        $this->assertSame($original, $source->fresh()->getAttributes());
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_dns_failed');
        $this->assertSame('official_dns_failed', $source->fresh()->last_outcome);
        Http::assertNothingSent();
    }

    public function test_deactivation_during_acquisition_prevents_persistence(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake(function () use ($source) {
            app(OfficialSources::class)->deactivate($source);

            return Http::response($this->body(), 200, ['Content-Type' => 'text/html']);
        });
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_approval_changed');
        $this->assertDatabaseCount('source_records', 0);
        $this->assertSame('inactive', $source->fresh()->approval_status);
    }

    public function test_snapshot_metadata_cannot_point_to_another_source(): void
    {
        $source = $this->source();
        $this->response();
        $recordId = app(OfficialSources::class)->fetch($source)['source_record_id'];
        $other = app(OfficialSources::class)->register($this->journal(), 'official_journal', 'website', 'https://journal.example.org/');
        try {
            DB::transaction(fn () => DB::table('journal_sources')->where('id', $other->id)->update(['last_source_record_id' => $recordId]));
            $this->fail('Foreign snapshot assignment accepted.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
        $this->fails(fn () => app(OfficialSourceClient::class)->fetch($source, new SourceRecord(['journal_source_id' => $other->id])), 'official_snapshot_mismatch');
    }

    public function test_untrusted_validators_are_not_retained_or_reflected(): void
    {
        $source = $this->source();
        $this->response(headers: ['ETag' => 'synthetic-é', 'Last-Modified' => str_repeat('x', 513)]);
        app(OfficialSources::class)->fetch($source);
        $this->assertNull($source->fresh()->last_etag);
        $this->assertNull($source->fresh()->last_modified);
        $this->assertNull(SourceRecord::first()->snapshot_metadata['etag']);
        $this->assertNull(SourceRecord::first()->snapshot_metadata['last_modified']);
    }

    public function test_redirect_conditionals_are_scoped_to_the_final_resource(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake([self::URL => Http::response('', 302, ['Location' => '/final']),
            'https://journal.example.org/final' => Http::response($this->body(), 200, ['Content-Type' => 'text/html', 'ETag' => '"synthetic-v1"'])]);
        app(OfficialSources::class)->fetch($source);
        $this->resetHttp();
        Http::fake(function ($request) {
            if ($request->url() === self::URL) {
                $this->assertFalse($request->hasHeader('If-None-Match'));

                return Http::response('', 302, ['Location' => '/final']);
            }
            $this->assertSame(['"synthetic-v1"'], $request->header('If-None-Match'));

            return Http::response('', 304);
        });
        $this->assertSame('not_modified', app(OfficialSources::class)->fetch($source)['outcome']);
        $this->assertDatabaseCount('source_records', 1);
        Http::assertSentCount(2);
    }

    public function test_second_postgresql_session_serializes_fetch_and_lock_is_released(): void
    {
        $source = $this->source();
        $key = 'official-source:'.$source->id;
        config(['database.connections.official_lock_test' => config('database.connections.pgsql')]);
        $other = DB::connection('official_lock_test');
        try {
            $this->assertTrue($other->selectOne('SELECT pg_try_advisory_lock(hashtextextended(?,0)) AS acquired', [$key])->acquired);
            $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_source_busy');
            Http::assertNothingSent();
            $this->assertDatabaseCount('source_records', 0);
            $this->assertTrue($other->selectOne('SELECT pg_advisory_unlock(hashtextextended(?,0)) AS released', [$key])->released);
            $this->response();
            app(OfficialSources::class)->fetch($source);
            $this->assertTrue($other->selectOne('SELECT pg_try_advisory_lock(hashtextextended(?,0)) AS acquired', [$key])->acquired);
            $this->assertDatabaseCount('source_records', 1);
        } finally {
            $other->select('SELECT pg_advisory_unlock_all()');
            DB::purge('official_lock_test');
        }
    }

    public function test_native_curl_factory_accepts_actual_request_options_without_network(): void
    {
        $source = $this->source();
        $this->resetHttp();
        Http::fake(function ($request, $options) {
            // Exercise actual native option validation/handle creation; never execute the handle.
            $factory = new CurlFactory(0);
            $handle = $factory->create($request->toPsrRequest(), $options);
            $this->assertInstanceOf(EasyHandle::class, $handle);
            $factory->release($handle);

            return Http::response($this->body(), 200, ['Content-Type' => 'text/html']);
        });
        $this->assertSame('captured', app(OfficialSources::class)->fetch($source, true)['outcome']);
        $this->assertDatabaseCount('source_records', 0);
    }

    public function test_approval_fields_cannot_be_mass_assigned_but_controlled_approval_still_works(): void
    {
        $source = $this->source(false);
        $values = ['approval_status' => 'approved', 'approved_by' => 'bypass',
            'approval_basis' => 'Bypassed workflow', 'approved_at' => now()];
        $source->fill($values);
        $this->assertSame('candidate', $source->approval_status);
        $this->assertNull($source->approved_at);
        $source->update($values);
        $this->assertSame('candidate', $source->fresh()->approval_status);
        $this->assertNull($source->fresh()->approved_by);
        $this->assertNull($source->fresh()->approval_basis);
        $this->assertNull($source->fresh()->approved_at);
        $created = new JournalSource($values);
        $this->assertNull($created->approval_status);
        $this->assertNull($created->approved_by);
        $approved = app(OfficialSources::class)->approve($source, 'reviewer-real', 'Controlled synthetic review');
        $this->assertSame('approved', $approved->fresh()->approval_status);
        $this->assertSame('reviewer-real', $approved->fresh()->approved_by);
        $this->assertSame('Controlled synthetic review', $approved->fresh()->approval_basis);
        $this->assertNotNull($approved->fresh()->approved_at);
        $approved->update(['approval_status' => 'inactive']);
        $this->assertSame('approved', $approved->fresh()->approval_status);
        app(OfficialSources::class)->deactivate($approved);
        $this->assertSame('inactive', $approved->fresh()->approval_status);
        Http::assertNothingSent();
    }

    public function test_registered_identity_and_ownership_cannot_be_generically_reassigned(): void
    {
        $source = $this->source();
        foreach (['official_url' => 'https://other.example.org/new', 'official_origin' => 'https://other.example.org',
            'official_journal_id' => $this->journal()->id] as $field => $value) {
            $this->fails(fn () => $source->fresh()->update([$field => $value]), 'official_identity_immutable');
        }
        $this->assertSame(self::URL, $source->fresh()->official_url);
        $this->assertSame('approved', $source->fresh()->approval_status);
        $new = app(OfficialSources::class)->register($source->fresh()->official_journal_id
            ? Journal::findOrFail($source->official_journal_id) : $this->journal(), 'official_journal', 'guidelines', 'https://other.example.org/new');
        $this->assertSame('candidate', $new->approval_status);
        $this->assertNotSame($source->id, $new->id);
    }

    #[DataProvider('revocationModes')]
    public function test_revocation_during_cadence_prevents_first_get(string $mode): void
    {
        $source = $this->source();
        Sleep::whenFakingSleep(function () use ($source, $mode): void {
            if ($mode === 'delete') {
                $source->delete();
            } elseif ($mode === 'service') {
                app(OfficialSources::class)->deactivate($source);
            } else {
                DB::table('journal_sources')->where('id', $source->id)->update(['approval_status' => 'inactive']);
            }
        });
        $this->response();
        // Use client directly: even a vanished row must fail without relying on persistence checks.
        $this->fails(fn () => app(OfficialSourceClient::class)->fetch($source), 'official_not_approved');
        Http::assertNothingSent();
        $this->assertDatabaseCount('source_records', 0);
    }

    public static function revocationModes(): array
    {
        return [['service'], ['database'], ['delete']];
    }

    #[DataProvider('redirectRevocations')]
    public function test_revocation_between_redirects_prevents_next_get(string $mode, int $after): void
    {
        $source = $this->source();
        $count = 0;
        $this->resetHttp();
        Http::fake(function () use ($source, $mode, $after, &$count) {
            $count++;
            if ($count === $after) {
                if ($mode === 'service') {
                    app(OfficialSources::class)->deactivate($source);
                } else {
                    DB::table('journal_sources')->where('id', $source->id)->update(['approval_status' => 'inactive']);
                }
            }

            return Http::response('', 302, ['Location' => '/hop-'.$count]);
        });
        $this->fails(fn () => app(OfficialSources::class)->fetch($source), 'official_not_approved');
        Http::assertSentCount($after);
        $this->assertSame('inactive', $source->fresh()->approval_status);
        $this->assertDatabaseCount('source_records', 0);
    }

    public static function redirectRevocations(): array
    {
        return [['service', 1], ['database', 1], ['service', 2], ['database', 2]];
    }

    public function test_crlf_stored_validators_are_rejected_and_never_become_headers(): void
    {
        $source = $this->source();
        $this->response(headers: ['ETag' => '"synthetic-v1"', 'Last-Modified' => 'Mon, 12 Oct 2026 08:00:00 GMT']);
        $result = app(OfficialSources::class)->fetch($source);
        foreach (['last_etag', 'last_modified'] as $field) {
            try {
                DB::transaction(fn () => DB::table('journal_sources')->where('id', $source->id)->update([$field => "synthetic\r\nInjected: secret"]));
                $this->fail('CRLF validator persisted.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        // Defense in depth: the exact reuse validator rejects CR/LF, even outside SQL storage.
        $validator = new \ReflectionMethod(OfficialSourceClient::class, 'validator');
        foreach (["\r", "\n", "\r\n", ''] as $input) {
            $this->assertNull($validator->invoke(app(OfficialSourceClient::class), $input));
        }
        $this->resetHttp();
        Http::fake(function ($request) {
            $this->assertSame(['"synthetic-v1"'], $request->header('If-None-Match'));
            $this->assertStringNotContainsString("\r", implode('', $request->header('If-Modified-Since')));
            $this->assertStringNotContainsString("\n", implode('', $request->header('If-Modified-Since')));

            return Http::response('', 304);
        });
        $this->assertSame($result['source_record_id'], app(OfficialSources::class)->fetch($source)['source_record_id']);
    }

    public function test_html_external_entity_loader_is_never_invoked(): void
    {
        $source = $this->source();
        $attempts = [];
        $previous = libxml_get_external_entity_loader();
        libxml_set_external_entity_loader(function ($public, $system, $context) use (&$attempts) {
            $attempts[] = $system;
            throw new LogicException('External entity loading is forbidden');
        });
        $body = '<!DOCTYPE html SYSTEM "https://untrusted.example.org/external.dtd" [<!ENTITY secret SYSTEM "file:///etc/passwd">]><html><body>&secret;<img src="https://untrusted.example.org/image"></body></html>';
        try {
            $this->response($body);
            $result = app(OfficialSources::class)->fetch($source);
            $this->assertSame($body, SourceRecord::findOrFail($result['source_record_id'])->raw_content);
            $this->assertSame([], $attempts);
            Http::assertSentCount(1);
        } finally {
            libxml_set_external_entity_loader($previous);
        }
    }

    public function test_environment_proxies_cannot_override_pinned_hostname_tls_options(): void
    {
        $variables = ['HTTP_PROXY', 'HTTPS_PROXY', 'ALL_PROXY', 'http_proxy', 'https_proxy', 'all_proxy', 'NO_PROXY'];
        $saved = [];
        foreach ($variables as $name) {
            $saved[$name] = getenv($name);
            putenv($name.'='.($name === 'NO_PROXY' ? '' : 'http://127.0.0.1:9'));
        }
        try {
            $source = $this->source();
            $this->resetHttp();
            Http::fake(function ($request, $options) {
                $this->assertSame(self::URL, $request->url());
                $this->assertSame('journal.example.org', $request->toPsrRequest()->getUri()->getHost());
                $this->assertTrue($options['verify']);
                $this->assertSame('', $options['proxy']);
                $selection = ProxyEnv::resolveProxySelection($request->toPsrRequest()->getUri(), $options['proxy']);
                $this->assertTrue($selection->shouldDisableProxy());
                $this->assertFalse($selection->hasProxy());
                $this->assertSame(['journal.example.org:443:93.184.215.14'], $options['curl'][CURLOPT_RESOLVE]);
                $factory = new CurlFactory(0);
                $handle = $factory->create($request->toPsrRequest(), $options);
                $factory->release($handle);

                return Http::response($this->body(), 200, ['Content-Type' => 'text/html']);
            });
            app(OfficialSources::class)->fetch($source, true);
            Http::assertSentCount(1);
        } finally {
            foreach ($saved as $name => $value) {
                putenv($value === false ? $name : $name.'='.$value);
            }
        }
    }

    #[DataProvider('nativeTransferModes')]
    public function test_native_transfer_aborts_oversized_or_compressed_body_without_internet(string $mode): void
    {
        // Capture the production client's exact transfer callbacks/options. The public client is
        // never allowed to target loopback: this separate low-level harness tests CurlHandler only.
        $source = $this->source();
        $captured = null;
        $this->resetHttp();
        Http::fake(function ($request, $options) use (&$captured) {
            $captured = $options;

            return Http::response($this->body(), 200, ['Content-Type' => 'text/html']);
        });
        app(OfficialSourceClient::class)->fetch($source);
        $server = new \Symfony\Component\Process\Process([PHP_BINARY, base_path('tests/Fixtures/official-sources/stream-server.php'), $mode]);
        $server->setTimeout(5);
        $sink = fopen('php://temp/maxmemory:262144', 'w+');
        try {
            $server->start();
            $server->waitUntil(fn ($type, $output) => preg_match('/^127\.0\.0\.1:[0-9]+\n$/D', $output) === 1);
            $address = trim($server->getOutput());
            $this->assertMatchesRegularExpression('/^127\.0\.0\.1:[0-9]+$/D', $address);
            // Test harness uses local HTTP to avoid custom certificates. verify remains true;
            // production HTTPS-only URL/DNS policy is unchanged and cannot select this destination.
            $options = $captured;
            $options['protocols'] = ['http'];
            unset($options['curl'][CURLOPT_RESOLVE], $options['handler']);
            $options['sink'] = $sink;
            $options['timeout'] = 3;
            $options['connect_timeout'] = 1;
            $options['http_errors'] = false;
            $this->assertTrue($options['verify']);
            $this->assertFalse($options['decode_content']);
            $this->assertSame('', $options['proxy']);
            try {
                (new Client(['handler' => new CurlHandler]))->get('http://'.$address.'/', $options);
                $this->fail('Actual native transfer must abort, before downloading the four-MiB body.');
            } catch (RequestException $exception) {
                $cause = $exception;
                while ($cause && ! $cause instanceof DomainException) {
                    $cause = $cause->getPrevious();
                }
                $this->assertInstanceOf(DomainException::class, $cause);
                $this->assertSame($mode === 'compressed' ? 'official_unsupported_encoding' : 'official_response_too_large', $cause->getMessage());
            }
            $bytes = fstat($sink)['size'];
            if ($mode === 'compressed') {
                $this->assertSame(0, $bytes); // Header guard rejects before body transfer.
            } else {
                $this->assertGreaterThanOrEqual(OfficialSourceClient::MAX_BYTES, $bytes);
                $this->assertLessThan(OfficialSourceClient::MAX_BYTES + 65536, $bytes);
            }
            $this->assertDatabaseCount('source_records', 0);
        } finally {
            $server->stop(0);
            fclose($sink);
        }
    }

    public static function nativeTransferModes(): array
    {
        return [['no_length'], ['chunked'], ['compressed']];
    }

    public function test_generic_snapshot_updates_cannot_mutate_immutable_provenance(): void
    {
        $source = $this->source();
        $this->response();
        $record = SourceRecord::findOrFail(app(OfficialSources::class)->fetch($source)['source_record_id']);
        $before = $record->getAttributes();
        foreach (['journal_source_id' => $this->source()->id, 'revision_fingerprint' => str_repeat('f', 64),
            'source_url' => 'https://other.example.org/page', 'retrieved_at' => now()->addHour(),
            'raw_content' => 'Replacement', 'snapshot_metadata' => ['final_url' => 'https://other.example.org/page']] as $field => $value) {
            try {
                DB::transaction(fn () => $record->fresh()->update([$field => $value]));
                $this->fail('Generic persisted provenance update must be rejected.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
            $this->assertSame($before, $record->fresh()->getAttributes());
        }
        $this->assertDatabaseCount('source_records', 1);
    }

    public function test_cli_is_source_scoped_safe_and_has_deterministic_exit_codes(): void
    {
        $source = $this->source(false);
        $this->artisan('journals:sources', ['action' => 'fetch', '--source' => $source->id])->expectsOutputToContain('official_not_approved')->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'approve', '--source' => $source->id, '--actor' => 'reviewer', '--basis' => 'Synthetic review'])->assertExitCode(0);
        $this->response();
        $this->artisan('journals:sources', ['action' => 'fetch', '--source' => $source->id, '--dry-run' => true])->assertExitCode(0);
        $this->assertDatabaseCount('source_records', 0);
        $this->artisan('journals:sources', ['action' => 'fetch', '--source' => $source->id, '--journal' => $this->journal()->id])->expectsOutputToContain('official_invalid_source')->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'fetch', '--source' => $source->id, '--url' => self::URL])->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'crawl', '--source' => $source->id])->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'status', '--source' => $source->id])->assertExitCode(0);
        Http::assertSentCount(1);
    }
}
