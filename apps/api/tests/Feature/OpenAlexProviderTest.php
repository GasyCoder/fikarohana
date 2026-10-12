<?php

namespace Tests\Feature;

use App\Enums\VerificationStatus;
use App\Models\IngestionRun;
use App\Models\JournalAssertion;
use App\Models\JournalIdentifier;
use App\Models\JournalSource;
use App\Models\Subject;
use App\Services\JournalIngestion\CanonicalJson;
use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\ProcessIngestionRun;
use App\Services\JournalIngestion\Providers\Crossref\CrossrefProvider;
use App\Services\JournalIngestion\Providers\Doaj\DoajProvider;
use App\Services\JournalIngestion\Providers\OpenAlex\OpenAlexClient;
use App\Services\JournalIngestion\Providers\OpenAlex\OpenAlexProvider;
use App\Services\JournalIngestion\Providers\OpenAlex\OpenAlexTaxonomy;
use App\Services\JournalIngestion\PublishIngestionItem;
use App\Services\JournalIngestion\ReviewIngestionAssertion;
use App\Services\JournalIngestion\SyntheticProvider;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Sleep;
use LogicException;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class OpenAlexProviderTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Sleep::fake();
        config(['journal_ingestion.openalex.api_key' => '']);
    }

    private function fixture(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/openalex/source.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    private function candidate(bool $process = true, string $issn = '1234-5679', ?string $eissn = '2049-3630'): IngestionRun
    {
        $data = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/doaj/journal.json')), true, 32, JSON_THROW_ON_ERROR);
        $data['id'] = md5($issn);
        $data['bibjson']['pissn'] = $issn;
        $data['bibjson']['eissn'] = $eissn;
        unset($data['bibjson']['publisher']['country']);
        $provider = new DoajProvider;
        $source = JournalSource::firstOrCreate(['type' => 'doaj', 'name' => 'DOAJ']);
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, 'candidate-'.$issn, $provider->envelopes([$data]));
        $run = IngestionRun::findOrFail($result['run_id']);
        if ($process) {
            app(ProcessIngestionRun::class)->process($run, $provider);
        }

        return $run->fresh();
    }

    private function stage(array $data, string $key = 'openalex', string $lookup = '1234-5679'): IngestionRun
    {
        $data['_lookup_issn'] = $lookup;
        $provider = new OpenAlexProvider;
        $source = JournalSource::firstOrCreate(['type' => 'openalex', 'name' => 'OpenAlex']);
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, $key, $provider->envelopes([$data]));
        $run = IngestionRun::findOrFail($result['run_id']);

        return app(ProcessIngestionRun::class)->process($run, $provider)->fresh();
    }

    #[DataProvider('lookups')]
    public function test_identifier_lookup_and_hierarchy_provenance(string $lookup): void
    {
        $candidate = $this->candidate();
        Http::fake(['api.openalex.org/*' => Http::response($this->fixture(), 200)]);
        $records = app(OpenAlexClient::class)->lookup([$lookup], 1)['records'];
        $item = $this->stage($records[0], lookup: $lookup)->items->first();
        $this->assertSame('pending', $item->status);
        $this->assertSame($candidate->items->first()->journal_id, $item->journal_id);
        $this->assertSame('S123456789', $item->sourceRecord->external_id);
        $this->assertSame('https://api.openalex.org/sources/S123456789', $item->sourceRecord->source_url);
        $this->assertSame('openalex-source-topics-v1', $item->mapping_version);
        $this->assertSame(CanonicalJson::hash($this->fixture()), CanonicalJson::hash(array_diff_key($item->payload, ['_lookup_issn' => true])));
        $this->assertDatabaseCount('journals', 1);
        $this->assertCount(6, $item->assertions);
        $this->assertSame(4, Subject::where('provider', 'openalex')->count());
        $topic = Subject::where('taxonomy_level', 'topic')->first();
        $this->assertSame('subfield', $topic->parent->taxonomy_level);
        $this->assertSame('field', $topic->parent->parent->taxonomy_level);
        $this->assertSame('domain', $topic->parent->parent->parent->taxonomy_level);
        $this->assertNull($topic->parent->parent->parent->parent_id);
        foreach ($item->assertions as $claim) {
            $this->assertSame(VerificationStatus::Pending, $claim->verificationStatus());
            $this->assertSame($item->source_record_id, $claim->source_record_id);
            $this->assertNotNull($claim->original_wording);
        }
        $this->assertDatabaseCount('catalog_revisions', 0);
        Http::assertSentCount(1);
    }

    public static function lookups(): array
    {
        return [['1234-5679'], ['2049-3630']];
    }

    public function test_multiple_topics_and_duplicate_ids_are_deduplicated_with_shared_ancestors(): void
    {
        $this->candidate();
        $data = $this->fixture();
        $data['topics'][] = $data['topics'][0];
        $second = $data['topics'][0];
        $second['id'] = 'https://openalex.org/T12346';
        $second['display_name'] = 'Second synthetic topic';
        $data['topics'][] = $second;
        $item = $this->stage($data)->items->first();
        $this->assertSame('pending', $item->status);
        $this->assertSame(5, Subject::where('provider', 'openalex')->count());
        $this->assertSame(5, $item->assertions()->where('field', 'subject')->count());
        $this->assertSame(1, Subject::where('taxonomy_level', 'domain')->count());
    }

    public function test_taxonomy_update_is_append_only_and_requires_conflict_resolution_before_publication(): void
    {
        $this->candidate();
        $first = $this->stage($this->fixture())->items->first();
        $oldTopic = Subject::where('taxonomy_level', 'topic')->first();
        $data = $this->fixture();
        $data['topics'][0]['display_name'] = 'Updated topic name';
        $second = $this->stage($data, 'changed')->items->first();
        $this->assertSame('conflicting', $second->status);
        $this->assertSame(5, Subject::where('provider', 'openalex')->count());
        $this->assertSame('Synthetic topic', $oldTopic->fresh()->name);
        $oldClaim = $first->assertions()->whereHas('subjectLink', fn ($q) => $q->where('subject_id', $oldTopic->id))->first();
        $newClaim = $second->assertions()->whereHas('subjectLink.subject', fn ($q) => $q->where('name', 'Updated topic name'))->first();
        $this->assertSame($oldClaim->selectionKey(), $newClaim->selectionKey());
        $this->assertSame(VerificationStatus::Conflicting, $newClaim->verificationStatus());
        $this->assertContains($oldClaim->id, $second->conflict_ids);
        $title = $second->assertions()->where('field', 'title')->first();
        $review = app(ReviewIngestionAssertion::class);
        $review->review($title, VerificationStatus::Verified, 'test', 'fixture', 'Checked synthetic title.');
        try {
            app(PublishIngestionItem::class)->publish($second, [$title->id, $newClaim->id]);
            $this->fail('Conflicting taxonomy must not publish.');
        } catch (DomainException) {
            $this->assertDatabaseCount('journal_revisions', 0);
        }
        $review->review($oldClaim, VerificationStatus::Rejected, 'test', 'fixture', 'Superseded taxonomy version.');
        $review->review($newClaim, VerificationStatus::Verified, 'test', 'fixture', 'Checked taxonomy version.');
        $catalog = app(PublishIngestionItem::class)->publish($second, [$title->id, $newClaim->id]);
        $this->assertNotNull($catalog->published_at);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_ancestor_change_creates_versioned_descendant_paths(): void
    {
        $this->candidate();
        $this->stage($this->fixture());
        $data = $this->fixture();
        $data['topics'][0]['domain']['display_name'] = 'Updated domain';
        $item = $this->stage($data, 'ancestor')->items->first();
        $this->assertSame('conflicting', $item->status);
        $this->assertSame(8, Subject::where('provider', 'openalex')->count());
        $this->assertCount(4, $item->conflict_ids);
    }

    public function test_same_response_reuses_observation_claims_and_first_retrieval(): void
    {
        $this->candidate();
        $first = $this->stage($this->fixture());
        $count = JournalAssertion::count();
        $retrieved = $first->items->first()->sourceRecord->retrieved_at->toISOString();
        $this->travel(1)->hours();
        $same = $this->stage($this->fixture());
        $other = $this->stage($this->fixture(), 'another-key');
        $this->assertSame($first->id, $same->id);
        $this->assertSame($first->items->first()->id, $other->items->first()->id);
        $this->assertSame($count, JournalAssertion::count());
        $this->assertSame($retrieved, $other->items->first()->sourceRecord->retrieved_at->toISOString());
        $this->assertSame(4, Subject::where('provider', 'openalex')->count());
        $data = $this->fixture();
        $data['updated_date'] = '2026-10-02T00:00:00Z';
        $changed = $this->stage($data, 'changed-date');
        $this->assertNotSame($first->items->first()->source_record_id, $changed->items->first()->source_record_id);
        $this->assertSame(4, Subject::where('provider', 'openalex')->count());
        $this->assertSame(4, $changed->items->first()->assertions()->where('field', 'subject')->count());
        $this->expectException(DomainException::class);
        $this->stage($data);
    }

    #[DataProvider('optionalData')]
    public function test_optional_data_never_invents_types_taxonomy_or_publisher(string $kind): void
    {
        $this->candidate();
        $data = $this->fixture();
        match ($kind) {
            'taxonomy' => $data['topics'] = null,
            'parents' => $data['topics'][0] = array_diff_key($data['topics'][0], array_flip(['domain', 'field', 'subfield'])),
            'publisher' => $data = array_diff_key($data, array_flip(['host_organization', 'host_organization_name'])),
            'institution' => $data['host_organization'] = 'https://openalex.org/I123456789',
            'new_issn' => $data['issn'][] = '0378-5955',
            'optional' => $data = array_diff_key($data, array_flip(['topics', 'issn_l', 'created_date', 'updated_date', 'works_count', 'cited_by_count'])),
        };
        $item = $this->stage($data)->items->first();
        $this->assertSame('pending', $item->status);
        if (in_array($kind, ['taxonomy', 'optional'], true)) {
            $this->assertSame(0, $item->assertions()->where('field', 'subject')->count());
        }
        if ($kind === 'parents') {
            $this->assertSame(1, Subject::where('provider', 'openalex')->count());
            $this->assertNull(Subject::where('provider', 'openalex')->first()->parent_id);
        }
        if (in_array($kind, ['publisher', 'institution'], true)) {
            $this->assertSame(0, $item->assertions()->where('field', 'publisher')->count());
        }
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertSame(0, $item->assertions()->where('field', 'fee')->count());
    }

    public static function optionalData(): array
    {
        return array_map(fn ($v) => [$v], ['taxonomy', 'parents', 'publisher', 'institution', 'new_issn', 'optional']);
    }

    #[DataProvider('badTaxonomy')]
    public function test_bad_taxonomy_quarantines_original_observations(string $kind): void
    {
        $this->candidate();
        $data = $this->fixture();
        match ($kind) {
            'shape' => $data['topics'] = 'bad',
            'name' => $data['topics'][0]['display_name'] = [],
            'id' => $data['topics'][0]['id'] = 'http://127.0.0.1/topic',
            'level' => $data['topics'][0]['level'] = 'concept',
            'parent' => $data['topics'][0]['field']['id'] = 'https://openalex.org/domains/3',
            'conflict' => $data['topics'][] = [...$data['topics'][0], 'display_name' => 'Disagrees'],
            'parent_conflict' => $data['topics'][] = [...$data['topics'][0], 'domain' => ['id' => 'https://openalex.org/domains/3', 'display_name' => 'Disagrees']],
        };
        $item = $this->stage($data)->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertNotNull($item->source_record_id);
        $this->assertSame(CanonicalJson::hash($data['topics']), CanonicalJson::hash($item->payload['topics']));
        $this->assertCount(0, $item->assertions);
        $this->assertSame(0, Subject::where('provider', 'openalex')->count());
    }

    public static function badTaxonomy(): array
    {
        return array_map(fn ($v) => [$v], ['shape', 'name', 'id', 'level', 'parent', 'conflict', 'parent_conflict']);
    }

    public function test_identifier_conflict_never_reassigns_even_an_untyped_alternate_issn(): void
    {
        $this->candidate();
        $this->candidate(issn: '0378-5955', eissn: null);
        $before = JournalIdentifier::orderBy('id')->get()->toArray();
        $data = $this->fixture();
        $data['issn'][] = '0378-5955';
        $item = $this->stage($data)->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['openalex_identifier_conflict'], $item->errors);
        $this->assertSame($before, JournalIdentifier::orderBy('id')->get()->toArray());
        $this->assertNotNull($item->source_record_id);
        $this->assertCount(0, $item->assertions);
    }

    public function test_title_publisher_conflicts_and_same_title_distinct_issns(): void
    {
        $first = $this->candidate();
        $other = $this->candidate(issn: '0378-5955', eissn: null);
        $data = $this->fixture();
        $data['id'] = 'https://openalex.org/S123456790';
        $data['issn'] = ['0378-5955'];
        $data['issn_l'] = '0378-5955';
        $item = $this->stage($data, 'other', '0378-5955')->items->first();
        $this->assertSame($other->items->first()->journal_id, $item->journal_id);
        $this->assertNotSame($first->items->first()->journal_id, $item->journal_id);
        $data = $this->fixture();
        $data['display_name'] = 'Contradictory title';
        $data['host_organization_name'] = 'Contradictory publisher';
        $conflict = $this->stage($data, 'conflict')->items->first();
        $this->assertSame('conflicting', $conflict->status);
        $this->assertSame('Synthetic DOAJ Research Journal', $first->items->first()->journal->fresh()->title);
        $this->assertCount(2, $conflict->conflict_ids);
        $this->assertDatabaseCount('journals', 2);
    }

    public function test_crossref_doaj_openalex_converge_with_separate_provenance(): void
    {
        $candidate = $this->candidate();
        $data = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/crossref/journal.json')), true, 32, JSON_THROW_ON_ERROR);
        $data['_lookup_issn'] = '1234-5679';
        $provider = new CrossrefProvider;
        $source = JournalSource::create(['type' => 'crossref', 'name' => 'Crossref']);
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, 'crossref', $provider->envelopes([$data]));
        $crossref = app(ProcessIngestionRun::class)->process(IngestionRun::findOrFail($result['run_id']), $provider);
        $openalex = $this->stage($this->fixture());
        $this->assertSame($candidate->items->first()->journal_id, $openalex->items->first()->journal_id);
        $this->assertSame($crossref->items->first()->journal_id, $openalex->items->first()->journal_id);
        $this->assertDatabaseCount('source_records', 3);
        $this->assertDatabaseCount('journal_sources', 3);
        $this->assertDatabaseCount('journal_identifiers', 2);
        Http::assertNothingSent();
    }

    public function test_cli_staged_candidate_repeated_issns_dry_run_and_no_url_fetching(): void
    {
        $this->candidate(false);
        Http::fake(['api.openalex.org/*' => Http::response($this->fixture(), 200)]);
        $before = $this->counts();
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'openalex', '--issn' => ['12345679', '2049-3630', '1234-5679'], '--key' => 'dry', '--limit' => '3', '--dry-run' => true])->assertSuccessful();
        $this->assertSame($before, $this->counts());
        Http::assertSentCount(2);
        Http::assertSent(fn ($r) => $r->url() === 'https://api.openalex.org/sources/issn:2049-3630');
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'openalex', '--issn' => ['1234-5679'], '--key' => 'persist', '--limit' => '1'])->assertSuccessful();
        $run = IngestionRun::where('provider', 'openalex')->first();
        $this->assertSame('staged', $run->items->first()->status);
        app(ProcessIngestionRun::class)->process($run, new OpenAlexProvider);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('journal_revisions', 0);
    }

    private function counts(): array
    {
        $counts = [];
        foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public' ORDER BY tablename") as $table) {
            $counts[$table->tablename] = DB::table($table->tablename)->count();
        }

        return $counts;
    }

    #[DataProvider('invalidCli')]
    public function test_invalid_cli_fails_before_http(array $options): void
    {
        $this->candidate();
        $before = $this->counts();
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'openalex', '--key' => 'bad', ...$options])->assertFailed();
        $this->assertSame($before, $this->counts());
        Http::assertNothingSent();
    }

    public static function invalidCli(): array
    {
        return [[[]], [['--issn' => ['1234-5678']]], [['--issn' => ['0378-5955']]], [['--issn' => ['1234-5679'], '--limit' => '0']], [['--issn' => ['1234-5679'], '--limit' => '101']], [['--issn' => ['1234-5679', '2049-3630'], '--limit' => '1']], [['--issn' => ['1234-5679'], '--transport' => 'api']], [['--issn' => ['1234-5679'], '--file' => 'bad']], [['--issn' => ['1234-5679'], '--query' => 'title']]];
    }

    #[DataProvider('badTransport')]
    public function test_http_failure_codes_are_bounded_and_sanitized(string $kind, string $code, int $calls): void
    {
        if ($kind === 'timeout') {
            Http::fake(['api.openalex.org/*' => Http::failedConnection('private secret')]);
        } else {
            $sequence = Http::sequence();
            for ($i = 0; $i < $calls; $i++) {
                $sequence->push(match ($kind) {
                    'json' => '{bad', 'utf8' => "{\"bad\":\"\xFF\"}", 'schema' => ['results' => []], 'size' => str_repeat('x', 1048577), default => 'private secret',
                }, match ($kind) {
                    '429', 'after' => 429, '5xx' => 503, 'auth' => 401, 'redirect' => 301, default => 200
                },
                    ['Content-Type' => $kind === 'html' ? 'text/html' : 'application/json', ...($kind === 'after' ? ['Retry-After' => '60'] : [])]);
            }
            Http::fake(['api.openalex.org/*' => $sequence]);
        }
        try {
            app(OpenAlexClient::class)->lookup(['1234-5679'], 1);
            $this->fail('Invalid HTTP response must fail.');
        } catch (DomainException $e) {
            $this->assertSame($code, $e->getMessage());
        }
        Http::assertSentCount($calls);
        $this->assertDatabaseCount('ingestion_runs', 0);
    }

    public static function badTransport(): array
    {
        return [['json', 'openalex_invalid_json', 1], ['utf8', 'openalex_invalid_json', 1], ['schema', 'openalex_invalid_response', 1], ['html', 'openalex_not_json', 1], ['size', 'openalex_response_too_large', 1], ['429', 'openalex_rate_limited', 3], ['5xx', 'openalex_unavailable', 3], ['timeout', 'openalex_connection_failed', 3], ['after', 'openalex_retry_deferred', 1], ['auth', 'openalex_auth_or_budget_failed', 1], ['redirect', 'openalex_http_failed', 1]];
    }

    public function test_404_recovery_retry_tls_key_and_fixed_origin(): void
    {
        config(['journal_ingestion.openalex.api_key' => 'synthetic-key']);
        Http::fake(function ($request, $options) {
            $this->assertSame(['Bearer synthetic-key'], $request->header('Authorization'));
            $this->assertStringNotContainsString('synthetic-key', $request->url());
            $this->assertFalse($options['allow_redirects']);
            $this->assertTrue($options['verify'] ?? true);
            $this->assertSame(20, $options['timeout']);
            $this->assertSame(5, $options['connect_timeout']);

            return Http::response($this->fixture(), 200);
        });
        $this->assertCount(1, app(OpenAlexClient::class)->lookup(['1234-5679'], 1)['records']);
        config(['journal_ingestion.openalex.base_url' => 'https://localhost']);
        $this->expectException(DomainException::class);
        app(OpenAlexClient::class)->lookup(['1234-5679'], 1);
    }

    public function test_404_and_transient_retries_recover(): void
    {
        Http::fake(['api.openalex.org/*' => Http::sequence()->push([], 404)->push([], 429, ['Retry-After' => '1'])->push([], 503)->push($this->fixture(), 200)]);
        $result = app(OpenAlexClient::class)->lookup(['1234-5679', '2049-3630'], 2);
        $this->assertSame(['1234-5679'], $result['not_found']);
        $this->assertCount(1, $result['records']);
    }

    public function test_nul_is_hash_only_and_missing_lookup_is_retained_quarantine(): void
    {
        $this->candidate();
        $data = $this->fixture();
        $data['topics'][0]['display_name'] = "Bad\0text";
        $unsafe = $this->stage($data)->items->first();
        $this->assertSame('quarantined', $unsafe->status);
        $this->assertNull($unsafe->source_record_id);
        $this->assertNull($unsafe->payload);
        $data = $this->fixture();
        $data['issn'] = ['2049-3630'];
        $data['issn_l'] = '2049-3630';
        $bad = $this->stage($data, 'wrong')->items->first();
        $this->assertSame('quarantined', $bad->status);
        $this->assertSame(['openalex_identifier_conflict'], $bad->errors);
        $this->assertNotNull($bad->source_record_id);
    }

    public function test_network_failure_does_not_persist_a_partial_batch(): void
    {
        $this->candidate();
        $before = $this->counts();
        Http::fake(['api.openalex.org/*' => Http::sequence()->push($this->fixture(), 200)->push([], 503)->push([], 503)->push([], 503)]);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'openalex', '--issn' => ['1234-5679', '2049-3630'], '--limit' => '2', '--key' => 'partial'])->assertFailed();
        $this->assertSame($before, $this->counts());
        Http::assertSentCount(4);
    }

    public function test_taxonomy_database_guards_and_nondestructive_rollback(): void
    {
        $this->candidate();
        $this->stage($this->fixture());
        $topic = Subject::where('taxonomy_level', 'topic')->first();
        foreach ([['name' => 'Changed'], ['taxonomy_level' => 'concept'], ['parent_id' => $topic->id]] as $change) {
            try {
                DB::transaction(fn () => DB::table('subjects')->where('id', $topic->id)->update($change));
                $this->fail('Immutable provider vocabulary must reject SQL changes.');
            } catch (QueryException $e) {
                $this->assertSame('23514', $e->errorInfo[0]);
            }
        }
        try {
            (require database_path('migrations/2026_10_12_090000_add_provider_taxonomy_to_subjects.php'))->down();
            $this->fail('Populated rollback must not discard evidence.');
        } catch (LogicException) {
            $this->assertTrue(Schema::hasColumn('subjects', 'external_id'));
            $this->assertSame('Synthetic topic', $topic->fresh()->name);
        }
    }

    public function test_empty_migration_rollback_restores_native_uniqueness_and_reapplies(): void
    {
        $migration = require database_path('migrations/2026_10_12_090000_add_provider_taxonomy_to_subjects.php');
        DB::transaction(function () use ($migration): void {
            $migration->down();
            $this->assertFalse(Schema::hasColumn('subjects', 'provider'));
            $this->assertTrue(Schema::hasTable('journal_assertions'));
            $migration->up();
            $this->assertTrue(Schema::hasColumn('subjects', 'provider'));
        });
    }

    public function test_source_id_cannot_silently_change_local_journal_ownership(): void
    {
        $this->candidate();
        $this->stage($this->fixture());
        $this->candidate(issn: '0378-5955', eissn: null);
        $data = $this->fixture();
        $data['issn'] = ['0378-5955'];
        $data['issn_l'] = '0378-5955';
        $item = $this->stage($data, 'transfer', '0378-5955')->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['openalex_identifier_conflict'], $item->errors);
        $this->assertNotNull($item->source_record_id);
        $this->assertNull($item->journal_id);
        $this->assertDatabaseCount('journals', 2);
    }

    public function test_changed_taxonomy_dry_run_reports_conflicts_without_writes(): void
    {
        $this->candidate();
        $this->stage($this->fixture());
        $data = $this->fixture();
        $data['topics'][0]['display_name'] = 'Updated topic';
        $data['_lookup_issn'] = '1234-5679';
        $provider = new OpenAlexProvider;
        $source = JournalSource::where('type', 'openalex')->first();
        $before = $this->counts();
        $preview = app(IngestJournalRecords::class)->ingest($provider, $source, 'preview', $provider->envelopes([$data]), true);
        $this->assertSame($before, $this->counts());
        $this->assertSame('conflicting', $preview['items'][0]['status']);
        $this->assertCount(1, $preview['items'][0]['conflict_ids']);
    }

    public function test_crossref_staging_is_eligible_without_canonical_materialization(): void
    {
        $this->candidate(false);
        $data = json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/crossref/journal.json')), true, 32, JSON_THROW_ON_ERROR);
        $data['_lookup_issn'] = '1234-5679';
        $provider = new CrossrefProvider;
        $source = JournalSource::create(['type' => 'crossref', 'name' => 'Crossref']);
        app(IngestJournalRecords::class)->ingest($provider, $source, 'staged-crossref', $provider->envelopes([$data]));
        $this->assertDatabaseCount('journals', 0);
        $item = $this->stage($this->fixture())->items->first();
        $this->assertSame('pending', $item->status);
        $this->assertDatabaseCount('journals', 1);
    }

    public function test_publication_seals_taxonomy_and_reuses_receipt(): void
    {
        $this->candidate();
        $item = $this->stage($this->fixture())->items->first();
        $ids = $item->assertions()->whereIn('field', ['title', 'subject'])->pluck('id')->all();
        foreach ($item->assertions()->whereIn('id', $ids)->get() as $claim) {
            app(ReviewIngestionAssertion::class)->review($claim, VerificationStatus::Verified, 'test', 'fixture', 'Synthetic evidence checked.');
        }
        $catalog = app(PublishIngestionItem::class)->publish($item, $ids);
        $this->assertSame($catalog->id, app(PublishIngestionItem::class)->publish($item->fresh(), $ids)->id);
        $this->assertDatabaseCount('journal_revision_assertions', 5);
        $this->assertDatabaseCount('catalog_revisions', 1);
        try {
            DB::transaction(fn () => DB::table('journal_revision_assertions')->where('assertion_id', $ids[0])->delete());
            $this->fail('Sealed membership is immutable.');
        } catch (QueryException $e) {
            $this->assertSame('23514', $e->errorInfo[0]);
        }
    }

    public function test_taxonomy_partial_write_failure_rolls_back_and_retry_is_safe(): void
    {
        $this->candidate();
        $data = $this->fixture();
        $data['_lookup_issn'] = '1234-5679';
        $provider = new OpenAlexProvider;
        $source = JournalSource::create(['type' => 'openalex', 'name' => 'OpenAlex']);
        $bad = $data;
        $bad['display_name'] = [];
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, 'rollback', $provider->envelopes([$data, $bad]));
        $before = JournalAssertion::count();
        DB::unprepared("CREATE OR REPLACE FUNCTION test_taxonomy_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.taxonomy_level='subfield' THEN RAISE EXCEPTION 'synthetic failure' USING ERRCODE='23514'; END IF; RETURN NEW; END; $$; CREATE TRIGGER test_taxonomy_fail BEFORE INSERT ON subjects FOR EACH ROW EXECUTE FUNCTION test_taxonomy_fail();");
        try {
            try {
                app(ProcessIngestionRun::class)->process(IngestionRun::findOrFail($result['run_id']), $provider);
                $this->fail('The injected constraint must interrupt processing.');
            } catch (DomainException $exception) {
                $this->assertSame('processing_interrupted', $exception->getMessage());
            }
            $run = IngestionRun::findOrFail($result['run_id']);
            $this->assertSame('failed', $run->status);
            $this->assertSame(1, $run->counters()['records_failed']);
            $this->assertSame(1, $run->counters()['records_quarantined']);
            $this->assertSame($before, JournalAssertion::count());
            $this->assertSame(0, Subject::where('provider', 'openalex')->count());
            $this->assertDatabaseCount('source_records', 3);
        } finally {
            DB::unprepared('DROP TRIGGER test_taxonomy_fail ON subjects; DROP FUNCTION test_taxonomy_fail();');
        }
        $run = app(ProcessIngestionRun::class)->process($run, $provider);
        $this->assertSame('completed', $run->status);
        $this->assertSame(0, $run->counters()['records_failed']);
        $this->assertSame(4, Subject::where('provider', 'openalex')->count());
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public function test_database_rejects_wrong_level_parent_and_duplicate_provider_versions(): void
    {
        $this->candidate();
        $this->stage($this->fixture());
        $topic = Subject::where('taxonomy_level', 'topic')->first();
        $domain = Subject::where('taxonomy_level', 'domain')->first();
        $base = $topic->only(['name', 'provider', 'external_id', 'taxonomy_level', 'revision_fingerprint', 'parent_id']);
        foreach ([[$base, '23505'], [[...$base, 'external_id' => 'https://openalex.org/T12346', 'parent_id' => $domain->id], '23514'], [[...$base, 'external_id' => 'https://openalex.org/T12346', 'taxonomy_level' => 'concept'], '23514']] as [$attributes, $state]) {
            try {
                DB::transaction(fn () => Subject::create($attributes));
                $this->fail('Invalid taxonomy must fail at the database boundary.');
            } catch (QueryException $e) {
                $this->assertSame($state, $e->errorInfo[0]);
            }
        }
    }

    public function test_aggregate_response_budget_and_empty_404_cli_have_no_writes(): void
    {
        $this->candidate();
        $data = $this->fixture();
        $data['extra'] = str_repeat('x', 600000);
        Http::fake(['api.openalex.org/*' => Http::response($data, 200)]);
        try {
            app(OpenAlexClient::class)->lookup(['1234-5679', '2049-3630'], 2);
            $this->fail('Aggregate bytes must be bounded.');
        } catch (DomainException $e) {
            $this->assertSame('openalex_batch_too_large', $e->getMessage());
        }
        $this->assertDatabaseCount('source_records', 1);
    }

    public function test_empty_404_cli_writes_no_source_or_run(): void
    {
        $this->candidate();
        Http::fake(['api.openalex.org/*' => Http::response([], 404)]);
        $before = $this->counts();
        $this->artisan('journals:ingestion', ['action' => 'stage', '--provider' => 'openalex', '--issn' => ['1234-5679'], '--key' => 'missing'])->assertSuccessful();
        $this->assertSame($before, $this->counts());
        Http::assertSentCount(1);
    }

    public function test_synthetic_raw_input_cannot_impersonate_openalex_taxonomy(): void
    {
        $provider = new SyntheticProvider;
        $source = JournalSource::create(['type' => 'other', 'name' => 'synthetic:qualification']);
        $payload = ['title' => 'Synthetic', 'identifiers' => [['type' => 'issn', 'value' => '1234-5679']],
            'taxonomy' => app(OpenAlexTaxonomy::class)->normalize($this->fixture()['topics'])];
        $result = app(IngestJournalRecords::class)->ingest($provider, $source, 'wrong-taxonomy', [['external_id' => 'synthetic', 'source_url' => 'https://catalog.example.test/source', 'retrieved_at' => now()->utc()->format('Y-m-d\TH:i:s\Z'), 'payload' => $payload]]);
        $item = IngestionRun::findOrFail($result['run_id'])->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertNull($item->source_record_id);
        $this->assertSame(0, Subject::where('provider', 'openalex')->count());
        Http::assertNothingSent();
    }

    #[DataProvider('stagedSourceConflicts')]
    public function test_source_id_conflict_with_staged_candidate_preserves_evidence_and_dry_run(bool $includesOmittedOwnedSibling): void
    {
        $candidateA = $this->candidate();
        $linked = $this->stage($this->fixture())->items->first();
        $journalA = $candidateA->items->first()->journal->getAttributes();
        $stagedB = $this->candidate(false, '0378-5955', $includesOmittedOwnedSibling ? '2049-3630' : null);
        $this->assertNull($stagedB->items->first()->journal_id);
        $this->assertFalse(JournalIdentifier::where('normalized_value', '0378-5955')->exists());
        $identifiersBefore = JournalIdentifier::orderBy('id')->get()->toArray();
        $data = $this->fixture();
        $data['issn'] = ['0378-5955'];
        $data['issn_l'] = '0378-5955';
        $data['_lookup_issn'] = '0378-5955';
        $provider = new OpenAlexProvider;
        $source = JournalSource::where('type', 'openalex')->first();
        $before = $this->counts();
        $preview = app(IngestJournalRecords::class)->ingest($provider, $source, 'staged-source-conflict-preview', $provider->envelopes([$data]), true);
        $this->assertSame($before, $this->counts());
        $this->assertSame('quarantined', $preview['items'][0]['status']);
        $this->assertSame(['openalex_identifier_conflict'], $preview['items'][0]['errors']);
        $item = $this->stage($data, 'staged-source-conflict', '0378-5955')->items->first();
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['openalex_identifier_conflict'], $item->errors);
        $this->assertNotNull($item->source_record_id);
        $this->assertSame('S123456789', $item->sourceRecord->external_id);
        $this->assertSame(CanonicalJson::hash($data), CanonicalJson::hash($item->payload));
        $this->assertNull($item->journal_id);
        $this->assertCount(0, $item->assertions);
        $this->assertSame($journalA, $candidateA->items->first()->journal->fresh()->getAttributes());
        $this->assertSame($identifiersBefore, JournalIdentifier::orderBy('id')->get()->toArray());
        $this->assertNull($stagedB->items->first()->fresh()->journal_id);
        $this->assertSame('staged', $stagedB->items->first()->fresh()->status);
        $this->assertSame($linked->journal_id, $linked->fresh()->journal_id);
        $this->assertDatabaseCount('journals', 1);
        $this->assertDatabaseCount('catalog_revisions', 0);
        Http::assertNothingSent();
    }

    public static function stagedSourceConflicts(): array
    {
        return ['staged_only' => [false], 'omitted_owned_sibling' => [true]];
    }

    public function test_topic_moves_to_another_subfield_without_rewriting_old_evidence(): void
    {
        $this->candidate();
        $first = $this->stage($this->fixture())->items->first();
        $oldTopic = Subject::where('taxonomy_level', 'topic')->first();
        $oldParent = $oldTopic->parent;
        $oldClaims = $first->assertions()->pluck('id')->all();
        $data = $this->fixture();
        $data['topics'][0]['subfield'] = ['id' => 'https://openalex.org/subfields/1703', 'display_name' => 'Different synthetic subfield'];
        $second = $this->stage($data, 'moved-topic')->items->first();
        $newTopic = Subject::where('external_id', $oldTopic->external_id)->where('id', '!=', $oldTopic->id)->firstOrFail();
        $this->assertSame('conflicting', $second->status);
        $this->assertSame($oldTopic->external_id, $newTopic->external_id);
        $this->assertNotSame($oldTopic->revision_fingerprint, $newTopic->revision_fingerprint);
        $this->assertSame('https://openalex.org/subfields/1703', $newTopic->parent->external_id);
        $this->assertSame($oldParent->id, $oldTopic->fresh()->parent_id);
        $this->assertSame('https://openalex.org/subfields/1702', $oldTopic->fresh()->parent->external_id);
        $this->assertSame($oldClaims, $first->assertions()->pluck('id')->all());
        $this->assertSame('https://openalex.org/subfields/1702', $first->fresh()->payload['topics'][0]['subfield']['id']);
        $this->assertSame(6, Subject::where('provider', 'openalex')->count());
        $oldClaim = $first->assertions()->whereHas('subjectLink', fn ($q) => $q->where('subject_id', $oldTopic->id))->first();
        $newClaim = $second->assertions()->whereHas('subjectLink', fn ($q) => $q->where('subject_id', $newTopic->id))->first();
        $this->assertSame($oldClaim->selectionKey(), $newClaim->selectionKey());
        $this->assertSame(VerificationStatus::Conflicting, $newClaim->verificationStatus());
        $this->assertContains($oldClaim->id, $second->conflict_ids);
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    public function test_removed_topic_does_not_delete_subjects_assertions_or_reviews(): void
    {
        $this->candidate();
        $first = $this->stage($this->fixture())->items->first();
        $topic = Subject::where('taxonomy_level', 'topic')->first();
        $claim = $first->assertions()->whereHas('subjectLink', fn ($q) => $q->where('subject_id', $topic->id))->first();
        $review = app(ReviewIngestionAssertion::class)->review($claim, VerificationStatus::Verified, 'test', 'fixture', 'Checked old synthetic topic evidence.');
        $subjects = Subject::where('provider', 'openalex')->orderBy('id')->get()->toArray();
        $claims = $first->assertions()->pluck('id')->all();
        $data = $this->fixture();
        $data['topics'] = [];
        $second = $this->stage($data, 'removed-topic')->items->first();
        $this->assertSame('pending', $second->status);
        $this->assertSame([], $second->payload['topics']);
        $this->assertSame(0, $second->assertions()->where('field', 'subject')->count());
        $this->assertSame($subjects, Subject::where('provider', 'openalex')->orderBy('id')->get()->toArray());
        $this->assertSame($claims, $first->assertions()->pluck('id')->all());
        $this->assertSame($review->id, $claim->fresh()->latestReview->id);
        $this->assertSame(VerificationStatus::Verified, $claim->fresh()->verificationStatus());
        $this->assertDatabaseHas('journal_subjects', ['assertion_id' => $claim->id, 'subject_id' => $topic->id]);
        $this->assertCount(1, $first->fresh()->payload['topics']);
        $this->assertNotSame($first->source_record_id, $second->source_record_id);
        $this->assertDatabaseCount('catalog_revisions', 0);
    }

    #[DataProvider('configuredAuthFailures')]
    public function test_configured_key_401_403_never_retry_anonymously(int $status): void
    {
        config(['journal_ingestion.openalex.api_key' => 'synthetic-key']);
        Http::fake(['api.openalex.org/*' => Http::sequence()->push('private error body', $status)->push($this->fixture(), 200)]);
        try {
            app(OpenAlexClient::class)->lookup(['1234-5679'], 1);
            $this->fail('Configured authentication failure must not fall back.');
        } catch (DomainException $exception) {
            $this->assertSame('openalex_auth_or_budget_failed', $exception->getMessage());
        }
        Http::assertSentCount(1);
        Http::assertSent(fn ($request) => $request->header('Authorization') === ['Bearer synthetic-key']
            && $request->url() === 'https://api.openalex.org/sources/issn:1234-5679');
        Sleep::assertSleptTimes(0);
        $this->assertDatabaseCount('ingestion_runs', 0);
        $this->assertDatabaseCount('source_records', 0);
    }

    public static function configuredAuthFailures(): array
    {
        return ['401' => [401], '403' => [403]];
    }
}
