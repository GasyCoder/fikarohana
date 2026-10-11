<?php

namespace Tests\Feature;

use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\CatalogRevision;
use App\Models\IngestionItem;
use App\Models\IngestionRun;
use App\Models\Journal;
use App\Models\JournalAssertion;
use App\Models\JournalFee;
use App\Models\JournalIdentifier;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\JournalIngestion\CanonicalJson;
use App\Services\JournalIngestion\IngestJournalRecords;
use App\Services\JournalIngestion\PostgresText;
use App\Services\JournalIngestion\ProcessIngestionItem;
use App\Services\JournalIngestion\ProviderRegistry;
use App\Services\JournalIngestion\PublishIngestionItem;
use App\Services\JournalIngestion\ReviewIngestionAssertion;
use App\Services\JournalIngestion\SyntheticProvider;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Str;
use LogicException;
use Tests\TestCase;

class JournalIngestionTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function records(): array
    {
        return json_decode(file_get_contents(base_path('tests/Fixtures/ingestion/synthetic.json')), true, 32, JSON_THROW_ON_ERROR);
    }

    private function source(): JournalSource
    {
        return JournalSource::factory()->create(['type' => 'other']);
    }

    private function stage(array $records, ?JournalSource $source = null, string $key = 'test', ?SyntheticProvider $provider = null): IngestionRun
    {
        $result = app(IngestJournalRecords::class)->ingest($provider ?? new SyntheticProvider, $source ?? $this->source(), $key, $records);

        return IngestionRun::findOrFail($result['run_id']);
    }

    private function process(IngestionRun $run, ?SyntheticProvider $provider = null): IngestionItem
    {
        return app(ProcessIngestionItem::class)->process($run->items->first(), $provider ?? new SyntheticProvider);
    }

    private function review(JournalAssertion $claim, VerificationStatus $status = VerificationStatus::Verified): void
    {
        app(ReviewIngestionAssertion::class)->review($claim, $status, 'synthetic-operator', 'manual_synthetic', 'Reviewed synthetic fixture only.');
    }

    private function publish(IngestionItem $item): CatalogRevision
    {
        return app(PublishIngestionItem::class)->publish($item, $item->assertions->pluck('id')->all());
    }

    private function constraint(callable $operation, string $state = '23514'): void
    {
        try {
            DB::transaction($operation);
            $this->fail('Expected a database constraint failure.');
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);
        }
    }

    public function test_staging_is_separate_from_canonical_facts_and_partial_invalid_records_are_quarantined(): void
    {
        $run = $this->stage($this->records());
        $this->assertSame(3, $run->items()->count());
        $this->assertSame(['staged', 'staged', 'quarantined'], $run->items->pluck('status')->all());
        $this->assertDatabaseCount('journals', 0);
        $this->assertDatabaseCount('journal_assertions', 0);
        $this->assertDatabaseCount('source_records', 3);
        $this->assertSame(['invalid_proposal'], $run->items->last()->errors);
        Http::assertNothingSent();
    }

    public function test_dry_run_validates_and_resolves_without_any_writes(): void
    {
        $source = $this->source();
        $result = app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $source, 'dry', $this->records(), true);
        $this->assertTrue($result['dry_run']);
        $this->assertSame(['valid', 'valid', 'quarantined'], array_column($result['items'], 'status'));
        foreach (['ingestion_runs', 'ingestion_items', 'source_records', 'journals', 'journal_assertions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
        $item = $this->process($this->stage([$this->records()[0]], $source));
        $before = DB::table('ingestion_items')->count();
        $second = app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $source, 'dry2', [$this->records()[0]], true);
        $this->assertSame($item->journal_id, $second['items'][0]['journal_id']);
        $this->assertSame($before, DB::table('ingestion_items')->count());
    }

    public function test_run_key_replay_and_duplicate_items_are_idempotent(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $run = $this->stage([$record, $record], $source);
        $this->assertSame(2, $run->items()->count());
        $this->assertDatabaseCount('ingestion_items', 1);
        $replay = app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $source, 'test', [$record, $record]);
        $this->assertTrue($replay['replayed']);
        $this->assertSame($run->id, $replay['run_id']);
        $item = $this->process($run);
        $again = $this->process($run);
        $this->assertSame($item->id, $again->id);
        $this->assertDatabaseCount('journal_assertions', 4);
        $this->assertDatabaseCount('journal_identifiers', 1);
        $this->assertDatabaseCount('assertion_reviews', 0);
    }

    public function test_reusing_run_key_with_changed_input_is_rejected(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $this->stage([$record], $source);
        $record['payload']['title'] = 'Changed synthetic title';
        try {
            $this->stage([$record], $source);
            $this->fail('Changed input must not reuse a key.');
        } catch (DomainException $exception) {
            $this->assertSame('idempotency_key_reused_with_changed_input', $exception->getMessage());
        }
        $this->assertDatabaseCount('ingestion_runs', 1);
        $this->assertDatabaseCount('source_records', 1);
    }

    public function test_repeat_observation_never_resets_retrieval_or_creates_claims(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $first = $this->process($this->stage([$record], $source));
        $record['retrieved_at'] = '2026-02-01T12:00:00Z';
        $second = $this->process($this->stage([$record], $source, 'again'));
        $this->assertSame($first->id, $second->id);
        $this->assertSame('2026-01-01', $second->sourceRecord->retrieved_at->format('Y-m-d'));
        $this->assertDatabaseCount('source_records', 1);
        $this->assertDatabaseCount('journal_assertions', 4);
    }

    public function test_mapping_version_replay_reuses_evidence_and_publication(): void
    {
        $source = $this->source();
        $records = [$this->records()[0]];
        $first = $this->process($this->stage($records, $source));
        foreach ($first->assertions as $claim) {
            $this->review($claim);
        }
        $catalog = $this->publish($first);
        $provider = new class extends SyntheticProvider
        {
            public function mappingVersion(): string
            {
                return 'synthetic-v2';
            }
        };
        $second = $this->process($this->stage($records, $source, 'mapping2', $provider), $provider);
        $this->assertNotSame($first->id, $second->id);
        $this->assertSame($first->source_record_id, $second->source_record_id);
        $this->assertDatabaseCount('journal_assertions', 4);
        // The same observation reuses the identifier evidence too, without recreating ownership.
        $ids = $second->assertions->pluck('id')->all();
        $secondCatalog = app(PublishIngestionItem::class)->publish($second, $ids);
        $this->assertSame($catalog->id, $secondCatalog->id);
        $this->assertSame($secondCatalog->id, app(PublishIngestionItem::class)->publish($second, $ids)->id);
        $this->assertDatabaseCount('source_records', 1);
        $this->assertDatabaseCount('assertion_reviews', 4);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_changed_observation_resolves_existing_identity_and_preserves_conflicting_values(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $first = $this->process($this->stage([$record], $source));
        foreach ($first->assertions as $claim) {
            $this->review($claim);
        }
        $this->publish($first);
        $record['payload']['fees'][0]['amount'] = '250.50';
        $before = JournalAssertion::count();
        $preview = app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $source, 'changed-preview', [$record], true);
        $this->assertSame('conflicting', $preview['items'][0]['status']);
        $this->assertCount(1, $preview['items'][0]['conflict_ids']);
        $this->assertSame($before, JournalAssertion::count());
        $second = $this->process($this->stage([$record], $source, 'changed'));
        $this->assertSame($first->journal_id, $second->journal_id);
        $this->assertSame('conflicting', $second->status);
        $fee = $second->assertions->firstWhere('field', JournalField::Fee);
        $this->assertSame(VerificationStatus::Conflicting, $fee->verificationStatus());
        $this->assertSame('250.5000', $fee->fee->amount);
        $this->assertCount(1, $second->conflict_ids);
        $this->assertSame('0.0000', JournalAssertion::findOrFail($second->conflict_ids[0])->fee->amount);
        $this->assertDatabaseCount('source_records', 2);
        $this->assertDatabaseCount('journal_fees', 2);
        $this->assertDatabaseCount('journal_identifiers', 1);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_same_title_never_merges_different_valid_identifiers(): void
    {
        $one = $this->records()[0];
        $two = $this->records()[1];
        $two['payload']['title'] = $one['payload']['title'];
        $run = $this->stage([$one, $two]);
        foreach ($run->items as $item) {
            app(ProcessIngestionItem::class)->process($item, new SyntheticProvider);
        }
        $this->assertDatabaseCount('journals', 2);
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertSame(2, Journal::where('title', $one['payload']['title'])->count());
    }

    public function test_issn_and_eissn_pointing_to_two_owners_are_quarantined_without_merging(): void
    {
        $source = $this->source();
        $one = $this->process($this->stage([$this->records()[0]], $source));
        $two = $this->process($this->stage([$this->records()[1]], $source, 'second'));
        $record = $this->records()[0];
        $record['payload']['identifiers'][] = $this->records()[1]['payload']['identifiers'][0];
        $third = $this->process($this->stage([$record], $source, 'collision'));
        $this->assertSame('quarantined', $third->status);
        $this->assertSame(['identifier_owner_conflict'], $third->errors);
        $this->assertNull($third->journal_id);
        $this->assertNotSame($one->journal_id, $two->journal_id);
        $this->assertDatabaseCount('journals', 2);
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertSame(0, $third->assertions()->count());
    }

    public function test_identifier_type_reassignment_is_quarantined(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $this->process($this->stage([$record], $source));
        $record['payload']['identifiers'][0]['type'] = 'eissn';
        $item = $this->process($this->stage([$record], $source, 'reassignment'));
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['identifier_type_conflict'], $item->errors);
        $this->assertDatabaseCount('journal_identifiers', 1);
        $this->assertSame('issn', JournalIdentifier::first()->type);
    }

    public function test_unknown_fees_are_not_zero_and_malformed_amounts_are_rejected(): void
    {
        $item = $this->process($this->stage([$this->records()[1]]));
        $fee = JournalFee::firstOrFail();
        $this->assertNull($fee->amount);
        $this->assertNull($fee->currency);
        $this->assertSame('unknown', $fee->assertion->availability->value);
        $this->assertSame('pending', $item->status);
        foreach (['NaN', '-1', '1.00001', '10000000000', '1e3'] as $amount) {
            $record = $this->records()[0];
            $record['payload']['fees'][0]['amount'] = $amount;
            $run = $this->stage([$record]);
            $this->assertSame('quarantined', $run->items->first()->status);
        }
        $record = $this->records()[1];
        $record['payload']['fees'][0]['amount'] = '0';
        $record['payload']['fees'][0]['currency'] = 'USD';
        $this->assertSame('quarantined', $this->stage([$record])->items->first()->status);
    }

    public function test_missing_and_unsupported_fields_do_not_become_inferred_claims(): void
    {
        $record = $this->records()[0];
        unset($record['payload']['fees'], $record['payload']['scope']);
        $item = $this->process($this->stage([$record]));
        $this->assertSame(2, $item->assertions()->count());
        $this->assertDatabaseCount('journal_fees', 0);
        $this->assertDatabaseCount('journal_languages', 0);
        $record['payload']['token'] = 'never-retain-secret';
        $rejected = $this->stage([$record])->items->first();
        $this->assertNull($rejected->payload);
        $this->assertNull($rejected->source_record_id);
        $this->assertSame(['invalid_record'], $rejected->errors);
    }

    public function test_urls_and_future_retrieval_are_validated_without_fetching(): void
    {
        foreach (['file:///etc/passwd', 'https://user:secret@example.test/journal', 'https://example.test/journal?token=secret'] as $url) {
            $record = $this->records()[0];
            $record['source_url'] = $url;
            $item = $this->stage([$record])->items->first();
            $this->assertSame('quarantined', $item->status);
            $this->assertNull($item->payload);
        }
        $record = $this->records()[0];
        $record['retrieved_at'] = '2999-01-01T12:00:00Z';
        $this->assertSame('quarantined', $this->stage([$record])->items->first()->status);
        Http::assertNothingSent();
    }

    public function test_publication_requires_explicit_reviews_and_is_repeat_safe(): void
    {
        $item = $this->process($this->stage([$this->records()[0]]));
        try {
            $this->publish($item);
            $this->fail('Pending claims cannot publish.');
        } catch (DomainException) {
            $this->assertDatabaseCount('journal_revisions', 0);
            $this->assertDatabaseCount('catalog_revisions', 0);
        }
        foreach ($item->assertions as $claim) {
            $this->review($claim);
            $this->assertSame($item->source_record_id, $claim->source_record_id);
        }
        $catalog = $this->publish($item);
        $this->assertSame($catalog->id, $this->publish($item)->id);
        $this->assertSame('published', $item->fresh()->status);
        $this->assertDatabaseCount('catalog_revisions', 1);
        $this->assertDatabaseCount('journal_revisions', 1);
        $this->assertFalse($item->journal->active);
        $this->assertSame(4, DB::table('journal_revision_assertions')->count());
        Http::assertNothingSent();
    }

    public function test_conflicts_block_publication_until_explicit_resolution_and_old_evidence_remains(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $old = $this->process($this->stage([$record], $source));
        foreach ($old->assertions as $claim) {
            $this->review($claim);
        }
        $record['payload']['fees'][0]['amount'] = '500';
        $new = $this->process($this->stage([$record], $source, 'new'));
        foreach ($new->assertions as $claim) {
            $this->review($claim);
        }
        try {
            $this->publish($new);
            $this->fail('Unresolved competing claims cannot publish.');
        } catch (DomainException) {
            $this->assertDatabaseCount('catalog_revisions', 0);
            $this->assertDatabaseCount('journal_revisions', 0);
        }
        foreach ($old->assertions->where('field', '!=', JournalField::Identifier) as $claim) {
            $this->review($claim, VerificationStatus::Rejected);
        }
        $this->publish($new);
        $this->assertDatabaseCount('journal_fees', 2);
        $this->assertDatabaseCount('source_records', 2);
        $this->assertDatabaseCount('journal_assertions', 7);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_unknown_fee_selection_cannot_be_published_even_if_reviewed(): void
    {
        $item = $this->process($this->stage([$this->records()[1]]));
        foreach ($item->assertions as $claim) {
            $this->review($claim);
        }
        $this->expectException(DomainException::class);
        $this->publish($item);
    }

    public function test_invalid_review_metadata_and_foreign_publication_selections_are_denied(): void
    {
        $item = $this->process($this->stage([$this->records()[0]]));
        $claim = $item->assertions->first();
        try {
            app(ReviewIngestionAssertion::class)->review($claim, VerificationStatus::Verified, '', 'manual', 'reason');
            $this->fail('An operator reference is required.');
        } catch (DomainException) {
            $this->assertDatabaseCount('assertion_reviews', 0);
        }
        $other = $this->process($this->stage([$this->records()[1]]));
        $this->expectException(DomainException::class);
        app(PublishIngestionItem::class)->publish($item, $other->assertions->pluck('id')->all());
    }

    public function test_sql_protects_staging_input_attribution_and_audit(): void
    {
        $run = $this->stage([$this->records()[0]]);
        $item = $this->process($run);
        $this->constraint(fn () => DB::table('ingestion_items')->where('id', $item->id)->update(['payload' => '{}']));
        $this->constraint(fn () => $item->delete());
        $this->constraint(fn () => $run->update(['input_hash' => str_repeat('0', 64)]));
        $this->constraint(fn () => $run->delete());
        $this->constraint(fn () => DB::table('ingestion_claims')->where('ingestion_item_id', $item->id)->delete());
        $this->constraint(fn () => DB::table('ingestion_claims')->insert([
            'ingestion_item_id' => $item->id, 'assertion_id' => $item->assertions->first()->id, 'claim_hash' => str_repeat('0', 64),
        ]));
        $this->constraint(fn () => $item->update(['status' => 'staged']));
        $this->constraint(fn () => $item->update(['status' => 'published']));
        $this->constraint(fn () => $item->sourceRecord->delete());
        $other = $this->process($this->stage([$this->records()[1]]));
        $this->constraint(fn () => DB::table('ingestion_claims')->insert([
            'ingestion_item_id' => $item->id, 'assertion_id' => $other->assertions->first()->id, 'claim_hash' => str_repeat('0', 64),
        ]));
        $run->refresh()->update(['status' => 'completed']);
        $this->constraint(fn () => DB::table('ingestion_run_items')->insert([
            'ingestion_run_id' => $run->id, 'ingestion_item_id' => $item->id, 'position' => 4,
        ]));
    }

    public function test_generic_artisan_workflow_resumes_and_outputs_only_operational_metadata(): void
    {
        $source = $this->source();
        $path = base_path('tests/Fixtures/ingestion/synthetic.json');
        $this->artisan('journals:ingestion', ['action' => 'stage', '--source' => $source->id, '--file' => $path, '--key' => 'cli', '--dry-run' => true])->assertSuccessful();
        $this->assertDatabaseCount('ingestion_runs', 0);
        $this->artisan('journals:ingestion', ['action' => 'stage', '--source' => $source->id, '--file' => $path, '--key' => 'cli'])->assertSuccessful();
        $run = IngestionRun::firstOrFail();
        $first = $this->process($run); // Simulate an interruption after one committed item.
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertSuccessful();
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertSuccessful();
        $this->artisan('journals:ingestion', ['action' => 'status', 'id' => $run->id])->assertSuccessful();
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertDatabaseCount('journals', 2);
        foreach ($first->assertions as $claim) {
            $this->artisan('journals:ingestion', ['action' => 'review', 'id' => $claim->id, '--status' => 'verified', '--actor' => 'test-operator', '--method' => 'manual', '--reason' => 'Synthetic fixture review.'])->assertSuccessful();
        }
        $this->artisan('journals:ingestion', ['action' => 'publish', 'id' => $first->id, '--assertions' => $first->assertions->pluck('id')->implode(',')])->assertSuccessful();
        $this->assertDatabaseCount('catalog_revisions', 1);
        Http::assertNothingSent();
    }

    public function test_cli_does_not_open_remote_paths_or_expose_input_on_failure(): void
    {
        $source = $this->source();
        $this->artisan('journals:ingestion', ['action' => 'stage', '--source' => $source->id, '--file' => 'https://example.test/secret', '--key' => 'remote'])
            ->expectsOutput('Ingestion operation failed; inspect staged status/error codes and command arguments.')->assertFailed();
        $this->artisan('journals:ingestion', ['action' => 'source', 'id' => 'test', '--dry-run' => true])->assertFailed();
        $this->assertDatabaseCount('ingestion_runs', 0);
        Http::assertNothingSent();
    }

    public function test_provider_registry_excludes_later_providers_and_source_type_mismatch_is_denied(): void
    {
        $this->assertSame('doaj', app(ProviderRegistry::class)->get('doaj')->key());
        foreach (['openalex', 'official'] as $name) {
            try {
                app(ProviderRegistry::class)->get($name);
                $this->fail('Real providers must not be registered.');
            } catch (DomainException $exception) {
                $this->assertSame('provider_not_registered', $exception->getMessage());
            }
        }
        $source = JournalSource::factory()->create(['type' => 'doaj']);
        $this->expectException(DomainException::class);
        $this->stage([$this->records()[0]], $source);
    }

    public function test_ingestion_lock_blocks_a_second_postgresql_session(): void
    {
        $this->stage([$this->records()[0]]);
        config(['database.connections.ingestion_lock_test' => config('database.connections.pgsql')]);
        $other = DB::connection('ingestion_lock_test');
        try {
            $this->assertFalse($other->selectOne('SELECT pg_try_advisory_xact_lock(170003) AS acquired')->acquired);
        } finally {
            DB::purge('ingestion_lock_test');
        }
    }

    public function test_ingestion_migration_rollback_preserves_catalog_and_vector(): void
    {
        try {
            DB::transaction(function () {
                (require database_path('migrations/2026_10_11_180000_create_journal_ingestion.php'))->down();
                $this->assertFalse(Schema::hasTable('ingestion_items'));
                $this->assertTrue(Schema::hasTable('journal_assertions'));
                $this->assertTrue(DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname='vector') AS enabled")->enabled);
                throw new LogicException('Restore schema.');
            });
        } catch (LogicException) {
            $this->assertTrue(Schema::hasTable('ingestion_items'));
        }
    }

    public function test_normalization_keeps_original_values_and_evidenced_not_applicable_fees(): void
    {
        $record = $this->records()[0];
        $record['payload']['title'] = '  Synthetic Original Title  ';
        $record['payload']['website'] = 'https://CATALOG.example.test/CaseSensitive';
        $record['payload']['country'] = 'mg';
        $record['payload']['fees'][0] = array_replace($record['payload']['fees'][0], ['availability' => 'not_applicable', 'amount' => null, 'currency' => null]);
        $item = $this->process($this->stage([$record]));
        $this->assertSame('12345679', $item->payload['identifiers'][0]['value']);
        $this->assertSame('1234-5679', $item->normalized['identifiers'][0]['value']);
        $this->assertSame('https://catalog.example.test/CaseSensitive', $item->normalized['website']);
        $this->assertSame('MG', $item->normalized['country']);
        $title = $item->assertions->firstWhere('field', JournalField::Title);
        $this->assertSame('  Synthetic Original Title  ', $title->original_wording);
        $this->assertSame('Synthetic Original Title', $title->text_value);
        $this->assertSame('not_applicable', JournalFee::first()->assertion->availability->value);
        foreach ($item->assertions as $claim) {
            $this->review($claim);
        }
        $this->publish($item);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_a_quarantined_observation_is_not_promoted_by_reingestion(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $hash = CanonicalJson::hash([$record['source_url'], $record['payload']]);
        SourceRecord::create([
            'journal_source_id' => $source->id, 'external_id' => $record['external_id'], 'revision_fingerprint' => $hash,
            'source_url' => $record['source_url'], 'retrieved_at' => $record['retrieved_at'], 'content_hash' => $hash, 'status' => 'quarantined',
        ]);
        $item = $this->process($this->stage([$record], $source));
        $this->assertSame('quarantined', $item->status);
        $this->assertSame(['quarantined_source'], $item->errors);
        $this->assertDatabaseCount('journal_assertions', 0);
        $this->assertDatabaseCount('journals', 0);
        $this->assertSame('quarantined', $item->sourceRecord->status);
    }

    public function test_sql_uniqueness_and_source_attribution_cannot_be_bypassed(): void
    {
        $source = $this->source();
        $run = $this->stage([$this->records()[0]], $source);
        $item = $run->items->first();
        $duplicate = $run->getAttributes();
        $duplicate['id'] = (string) Str::uuid7();
        $this->constraint(fn () => DB::table('ingestion_runs')->insert($duplicate), '23505');
        $duplicateItem = $item->getAttributes();
        $duplicateItem['id'] = (string) Str::uuid7();
        $this->constraint(fn () => DB::table('ingestion_items')->insert($duplicateItem), '23505');
        $duplicateItem['journal_source_id'] = $this->source()->id;
        $this->constraint(fn () => DB::table('ingestion_items')->insert($duplicateItem));
        $different = $this->stage([$this->records()[1]])->items->first();
        $this->constraint(fn () => DB::table('ingestion_run_items')->insert([
            'ingestion_run_id' => $run->id, 'ingestion_item_id' => $different->id, 'position' => 99,
        ]));
    }

    public function test_changed_publication_selection_is_rejected_and_receipt_is_immutable(): void
    {
        $item = $this->process($this->stage([$this->records()[0]]));
        foreach ($item->assertions as $claim) {
            $this->review($claim);
        }
        $catalog = $this->publish($item);
        $this->constraint(fn () => $item->fresh()->update(['publication_hash' => str_repeat('0', 64)]));
        try {
            app(PublishIngestionItem::class)->publish($item, [$item->assertions->firstWhere('field', JournalField::Title)->id]);
            $this->fail('A published receipt cannot select different claims.');
        } catch (DomainException $exception) {
            $this->assertSame('published_selection_cannot_change', $exception->getMessage());
        }
        $this->assertSame($catalog->id, $item->fresh()->catalog_revision_id);
        $this->assertDatabaseCount('catalog_revisions', 1);
    }

    public function test_command_interruption_preserves_earlier_items_and_records_safe_diagnostics(): void
    {
        $run = $this->stage($this->records());
        $first = $this->process($run);
        $processor = app(ProcessIngestionItem::class);
        $mock = \Mockery::mock(ProcessIngestionItem::class);
        $mock->shouldReceive('process')->andReturnUsing(function ($item, $provider) use ($processor, $first) {
            if ($item->id === $first->id) {
                return $processor->process($item, $provider);
            }
            throw new \RuntimeException('Simulated private diagnostic: never print this.');
        });
        $this->instance(ProcessIngestionItem::class, $mock);
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])
            ->expectsOutput('Ingestion operation failed; inspect staged status/error codes and command arguments.')->assertFailed();
        $this->assertSame('failed', $run->fresh()->status);
        $this->assertSame(['processing_interrupted'], $run->fresh()->errors);
        $this->assertDatabaseCount('journals', 1);
        $this->assertSame('failed', $run->items()->get()[1]->status);
        $this->instance(ProcessIngestionItem::class, $processor);
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertSuccessful();
        $this->assertDatabaseCount('journals', 2);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame($first->id, $run->items()->first()->id);
    }

    public function test_nul_record_is_quarantined_without_losing_its_valid_batch_neighbors(): void
    {
        $bad = $this->records()[0];
        $bad['external_id'] = 'unsafe-synthetic';
        $bad['payload']['fees'][0]['applicability'] = "unsafe-fragment\0must-not-leak";
        $run = $this->stage([$this->records()[0], $bad, $this->records()[1]]);
        $this->assertSame(['staged', 'quarantined', 'staged'], $run->items->pluck('status')->all());
        $unsafe = $run->items[1];
        $this->assertNull($unsafe->payload);
        $this->assertNull($unsafe->source_record_id);
        $this->assertSame(['postgres_incompatible_text'], $unsafe->errors);
        $this->assertMatchesRegularExpression('/^[0-9a-f]{64}$/', $unsafe->item_key);
        $this->assertStringNotContainsString('unsafe-fragment', json_encode($unsafe->getAttributes()));
        $this->assertDatabaseCount('source_records', 2);
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertSuccessful();
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertDatabaseCount('journals', 2);
        $this->assertSame([
            'records_seen' => 3, 'records_staged' => 2, 'records_quarantined' => 1, 'records_unchanged' => 0, 'records_failed' => 0,
        ], $run->counters());
        Http::assertNothingSent();
    }

    public function test_postgres_text_validation_checks_nested_objects_values_and_keys_without_mutation(): void
    {
        $valid = ['prose' => "Français — 中文\n", 'nested' => (object) ['value' => 'Synthetic']];
        PostgresText::validate($valid);
        $this->assertSame("Français — 中文\n", $valid['prose']);
        foreach ([['nested' => [(object) ['value' => "a\0b"]]], ["bad\0key" => 'value'], ['value' => "\xFF"]] as $unsafe) {
            try {
                PostgresText::validate($unsafe);
                $this->fail('Incompatible nested text must be rejected.');
            } catch (DomainException $exception) {
                $this->assertSame('postgres_incompatible_text', $exception->getMessage());
            }
        }
        foreach (['title', 'scope'] as $field) {
            $record = $this->records()[0];
            $record['payload'][$field] = "synthetic\0unsafe";
            $this->assertSame(['postgres_incompatible_text'], $this->stage([$record])->items->first()->errors);
        }
    }

    public function test_nul_dry_run_quarantines_without_retaining_any_diagnostics_or_evidence(): void
    {
        $record = $this->records()[0];
        $record['payload']['title'] = "unsafe\0title";
        $result = app(IngestJournalRecords::class)->ingest(new SyntheticProvider, $this->source(), 'preview', [$record], true);
        $this->assertSame(['postgres_incompatible_text'], $result['items'][0]['errors']);
        $this->assertStringNotContainsString('unsafe', json_encode($result));
        foreach (['ingestion_source_providers', 'ingestion_runs', 'ingestion_items', 'source_records', 'journal_assertions', 'journal_revisions', 'catalog_revisions'] as $table) {
            $this->assertDatabaseCount($table, 0);
        }
    }

    public function test_cli_limit_selects_the_prefix_and_validates_all_bounds_before_writes(): void
    {
        $source = $this->source();
        $path = base_path('tests/Fixtures/ingestion/synthetic.json');
        foreach ([null, '1', '100'] as $index => $limit) {
            $args = ['action' => 'stage', '--source' => $source->id, '--file' => $path, '--key' => 'limit-'.$index];
            if ($limit !== null) {
                $args['--limit'] = $limit;
            }
            $this->artisan('journals:ingestion', $args)->assertSuccessful();
            $run = IngestionRun::where('idempotency_key', 'limit-'.$index)->firstOrFail();
            $this->assertSame($limit === '1' ? 1 : 3, $run->items()->count());
        }
        $before = IngestionRun::count();
        foreach (['0', '-1', '1.5', 'text', '101', '', '1e2', '99999999999999999999'] as $limit) {
            $this->artisan('journals:ingestion', ['action' => 'stage', '--limit' => $limit, '--file' => $path, '--source' => $source->id, '--key' => 'invalid'])
                ->expectsOutput('--limit must be an integer from 1 to 100.')->assertExitCode(2);
        }
        $this->assertSame($before, IngestionRun::count());
        $this->artisan('journals:ingestion', ['action' => 'source', 'id' => 'invalid-limit', '--limit' => '1'])
            ->expectsOutput('--limit is supported only for stage.')->assertExitCode(2);
        $largeFile = tempnam(sys_get_temp_dir(), 'ingestion-limit-');
        try {
            $records = array_fill(0, 100, $this->records()[0]);
            $records[] = ['payload' => ['title' => "excluded\0record"]];
            file_put_contents($largeFile, json_encode($records, JSON_THROW_ON_ERROR));
            foreach ([null, '100'] as $index => $limit) {
                $args = ['action' => 'stage', '--source' => $source->id, '--file' => $largeFile, '--key' => 'ceiling-'.$index];
                if ($limit !== null) {
                    $args['--limit'] = $limit;
                }
                $this->artisan('journals:ingestion', $args)->assertExitCode(0);
                $run = IngestionRun::where('idempotency_key', 'ceiling-'.$index)->firstOrFail();
                $this->assertSame(100, $run->items()->count());
                $this->assertSame(1, $run->counters()['records_seen']);
                $this->assertSame(0, $run->counters()['records_quarantined']);
            }
        } finally {
            unlink($largeFile);
        }
        Http::assertNothingSent();
    }

    public function test_unique_item_counters_are_stable_for_duplicates_replays_and_mixed_results(): void
    {
        $source = $this->source();
        $record = $this->records()[0];
        $run = $this->stage([$record, $record], $source);
        $expected = ['records_seen' => 1, 'records_staged' => 1, 'records_quarantined' => 0, 'records_unchanged' => 0, 'records_failed' => 0];
        $this->assertSame($expected, $run->counters());
        $this->process($run);
        $this->assertSame($expected, $run->counters());
        $this->stage([$record, $record], $source);
        $this->assertSame($expected, $run->counters());
        $next = $this->stage([$record, $record, $this->records()[2]], $source, 'next');
        $this->assertSame(['records_seen' => 2, 'records_staged' => 0, 'records_quarantined' => 1, 'records_unchanged' => 1, 'records_failed' => 0], $next->counters());
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $next->id])->assertSuccessful();
        $this->assertSame(1, $next->counters()['records_unchanged']);
    }

    public function test_real_item_constraint_failure_rolls_back_partial_writes_and_retries_safely(): void
    {
        $run = $this->stage([$this->records()[0], $this->records()[1]]);
        $first = $this->process($run);
        $second = $run->items[1];
        // A real database rejection after journal, item, identifier and title writes.
        DB::unprepared(<<<'SQL'
CREATE FUNCTION pg_temp.reject_synthetic_unknown_fee() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.amount IS NULL THEN
  IF NOT EXISTS (SELECT 1 FROM journal_identifiers WHERE journal_id = NEW.journal_id)
   OR NOT EXISTS (SELECT 1 FROM journal_assertions WHERE journal_id = NEW.journal_id AND field = 'title')
   OR NOT EXISTS (SELECT 1 FROM ingestion_items WHERE journal_id = NEW.journal_id AND normalized IS NOT NULL) THEN
   RAISE EXCEPTION 'failure_before_expected_writes' USING ERRCODE='23514';
  END IF;
  RAISE EXCEPTION 'controlled_failure_after_item_writes' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER test_reject_unknown_fee BEFORE INSERT ON journal_fees FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_synthetic_unknown_fee();
SQL);
        try {
            try {
                app(ProcessIngestionItem::class)->process($second, new SyntheticProvider);
                $this->fail('The real transaction must fail after its partial writes.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
                $this->assertStringContainsString('controlled_failure_after_item_writes', $exception->getMessage());
            }
            $this->assertDatabaseCount('journals', 1);
            $this->assertDatabaseCount('journal_identifiers', 1);
            $this->assertDatabaseCount('journal_assertions', 4);
            $this->assertDatabaseCount('journal_fees', 1);
            $this->assertSame(0, $second->assertions()->count());
            $this->assertNull($second->fresh()->journal_id);
            $this->assertNull($second->fresh()->normalized);
            $this->assertSame('staged', $second->fresh()->status);
            $this->assertSame('pending', $first->fresh()->status);
            $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])
                ->expectsOutput('Ingestion operation failed; inspect staged status/error codes and command arguments.')->assertExitCode(1);
            $this->assertSame('failed', $run->fresh()->status);
            $this->assertSame(['processing_interrupted'], $run->fresh()->errors);
            $this->assertSame(['processing_interrupted'], $second->fresh()->errors);
            $this->assertSame(['records_seen' => 2, 'records_staged' => 1, 'records_quarantined' => 0, 'records_unchanged' => 0, 'records_failed' => 1], $run->counters());
            $this->assertDatabaseCount('journals', 1);
            $this->assertDatabaseCount('journal_revisions', 0);
            $this->assertDatabaseCount('catalog_revisions', 0);
        } finally {
            DB::unprepared('DROP TRIGGER test_reject_unknown_fee ON journal_fees; DROP FUNCTION pg_temp.reject_synthetic_unknown_fee();');
        }
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertExitCode(0);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(['processing_interrupted'], $run->fresh()->errors);
        $this->assertSame([], $second->fresh()->errors);
        $this->assertDatabaseCount('journals', 2);
        $this->assertDatabaseCount('journal_assertions', 7);
        $this->assertDatabaseCount('journal_identifiers', 2);
        $this->assertSame(0, $run->counters()['records_failed']);
        $this->assertSame(2, $run->counters()['records_staged']);
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertExitCode(0);
        $this->assertDatabaseCount('journal_assertions', 7);
        Http::assertNothingSent();
    }

    public function test_source_binding_prevents_cross_provider_collisions_at_database_level(): void
    {
        $record = $this->records()[0];
        $one = $this->stage([$record]);
        $provider = new class extends SyntheticProvider
        {
            public function key(): string
            {
                return 'synthetic-alternative';
            }
        };
        $source = $this->source();
        $two = $this->stage([$record], $source, 'other-provider', $provider);
        $this->assertNotSame($one->items->first()->source_record_id, $two->items->first()->source_record_id);
        $this->assertNotSame($one->items->first()->id, $two->items->first()->id);
        $this->assertSame('synthetic-alternative', DB::table('ingestion_source_providers')->where('journal_source_id', $source->id)->value('provider'));
        $this->constraint(fn () => $two->update(['provider' => 'synthetic']));
        $runData = $two->getAttributes();
        $runData['id'] = (string) Str::uuid7();
        $runData['idempotency_key'] = 'different-key';
        $runData['provider'] = 'synthetic';
        $this->constraint(fn () => DB::table('ingestion_runs')->insert($runData));
        $this->constraint(fn () => DB::table('ingestion_source_providers')->where('journal_source_id', $source->id)->update(['provider' => 'synthetic']));
        try {
            $this->stage([$record], $source, 'denied');
            $this->fail('One source must not be reused by another provider.');
        } catch (DomainException $exception) {
            $this->assertSame('source_bound_to_different_provider', $exception->getMessage());
        }
        $this->assertDatabaseCount('source_records', 2);
        $this->assertDatabaseCount('ingestion_items', 2);
    }

    public function test_completed_run_cannot_have_unprocessed_items_and_all_quarantine_exits_zero(): void
    {
        $run = $this->stage([$this->records()[0]]);
        $this->constraint(fn () => $run->update(['status' => 'completed']));
        $quarantine = $this->stage([$this->records()[2]]);
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $quarantine->id])->assertExitCode(0);
        $this->assertSame('completed', $quarantine->fresh()->status);
        $this->assertSame(1, $quarantine->counters()['records_quarantined']);
        $this->assertDatabaseCount('journals', 0);
    }

    public function test_run_completion_failure_retains_processed_items_and_can_resume(): void
    {
        $run = $this->stage([$this->records()[0]]);
        DB::unprepared(<<<'SQL'
CREATE FUNCTION pg_temp.reject_run_completion() RETURNS trigger LANGUAGE plpgsql AS $$
BEGIN
 IF NEW.status = 'completed' THEN
  RAISE EXCEPTION 'internal_completion_error' USING ERRCODE='23514';
 END IF;
 RETURN NEW;
END;
$$;
CREATE TRIGGER test_reject_run_completion BEFORE UPDATE ON ingestion_runs FOR EACH ROW EXECUTE FUNCTION pg_temp.reject_run_completion();
SQL);
        try {
            $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])
                ->expectsOutput('Ingestion operation failed; inspect staged status/error codes and command arguments.')->assertExitCode(1);
            $this->assertSame('failed', $run->fresh()->status);
            $this->assertSame(['processing_interrupted'], $run->fresh()->errors);
            $this->assertSame('pending', $run->items()->first()->status);
            $this->assertSame(0, $run->counters()['records_failed']);
            $this->assertDatabaseCount('journal_assertions', 4);
        } finally {
            DB::unprepared('DROP TRIGGER test_reject_run_completion ON ingestion_runs; DROP FUNCTION pg_temp.reject_run_completion();');
        }
        $this->artisan('journals:ingestion', ['action' => 'process', 'id' => $run->id])->assertExitCode(0);
        $this->assertSame('completed', $run->fresh()->status);
        $this->assertSame(['processing_interrupted'], $run->fresh()->errors);
        $this->assertDatabaseCount('journal_assertions', 4);
        Http::assertNothingSent();
    }

    public function test_batch_limits_and_payload_url_secrets_are_rejected_before_retention(): void
    {
        $source = $this->source();
        foreach ([[], array_fill(0, 101, $this->records()[0])] as $records) {
            try {
                $this->stage($records, $source);
                $this->fail('Unbounded or empty batches are invalid.');
            } catch (DomainException) {
                $this->assertDatabaseCount('ingestion_runs', 0);
            }
        }
        $record = $this->records()[0];
        $record['payload']['website'] = 'https://user:do-not-retain@example.test/home';
        $item = $this->stage([$record], $source)->items->first();
        $this->assertNull($item->payload);
        $this->assertNull($item->source_record_id);
        $this->assertSame('quarantined', $item->status);
        Http::assertNothingSent();
    }
}
