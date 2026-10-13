<?php

namespace Tests\Feature;

use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\ArticleType;
use App\Models\CatalogRevision;
use App\Models\IndexationService;
use App\Models\Journal;
use App\Models\JournalArticleType;
use App\Models\JournalFee;
use App\Models\JournalIdentifier;
use App\Models\JournalIndexation;
use App\Models\JournalLanguage;
use App\Models\JournalRevision;
use App\Models\JournalSubject;
use App\Models\Language;
use App\Models\Publisher;
use App\Models\Subject;
use App\Services\JournalCatalog\PublishJournalRevision;
use App\Services\JournalDiscovery\JournalRelevanceScorer;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\JournalCatalogFixture as Fixture;
use Tests\TestCase;

class JournalDiscoveryTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function publish(string $title, array $facts = [], bool $active = true): Journal
    {
        $j = Journal::factory()->create(['title' => 'Working title must not leak', 'active' => $active]);
        $claims = [Fixture::assertion($j, JournalField::Title, ['text_value' => $title], VerificationStatus::Verified)];
        foreach ($facts as $field => $value) {
            $a = Fixture::assertion($j, JournalField::from($field), $field === 'publisher' ? ['publisher_id' => $value->id] : ($field === 'fee' ? ['availability' => $value === null ? 'not_applicable' : 'known'] : []), VerificationStatus::Verified);
            $base = ['journal_id' => $j->id, 'assertion_id' => $a->id];
            match ($field) {
                'subject' => JournalSubject::create($base + ['subject_id' => $value->id]),
                'language' => JournalLanguage::create($base + ['language_id' => $value->id, 'supported' => true]),
                'article_type' => JournalArticleType::create($base + ['article_type_id' => $value->id, 'supported' => true]),
                'indexation' => JournalIndexation::create($base + ['indexation_service_id' => $value->id, 'supported' => true]),
                'fee' => JournalFee::create($base + ['fee_type' => 'apc', 'amount' => $value, 'currency' => $value === null ? null : 'USD', 'basis' => 'per_article', 'applicability' => 'All manuscripts; synthetic evidence.']),
                'identifier' => JournalIdentifier::create($base + ['type' => 'eissn', 'value' => $value]),
                'publisher' => null,
            };
            $claims[] = $a;
        }
        app(PublishJournalRevision::class)->publish($j, array_map(fn ($a) => $a->id, $claims));

        return $j;
    }

    public function test_anonymous_published_only_and_detail_never_exposes_working_data(): void
    {
        $j = $this->publish('Canonical Biology');
        $this->publish('Inactive', active: false);
        Journal::factory()->create(['active' => true]);
        Fixture::assertion($j, JournalField::Scope, ['text_value' => 'Unreviewed secret scope']);
        $this->getJson('/api/v1/journals')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.title', 'Canonical Biology');
        $r = $this->getJson('/api/v1/journals/'.$j->id)->assertOk()->assertJsonPath('data.title', 'Canonical Biology')->assertJsonCount(1, 'data.provenance');
        $this->assertStringNotContainsString('Unreviewed secret', $r->getContent());
        $this->assertStringNotContainsString('raw_content', $r->getContent());
        $this->getJson('/api/v1/journals/not-a-uuid')->assertNotFound();
    }

    public function test_discovery_text_publisher_identifier_and_subject_filters(): void
    {
        $subject = Subject::create(['name' => 'Machine Learning']);
        $publisher = Publisher::factory()->create(['name' => 'University Science']);
        $j = $this->publish('Algorithms', ['subject' => $subject, 'publisher' => $publisher, 'identifier' => '1234-5679']);
        foreach (['q=learning', 'q=university', 'q=1234-5679', 'subject='.$subject->id, 'publisher='.$publisher->id] as $filter) {
            $this->getJson('/api/v1/journals?'.$filter)->assertOk()->assertJsonPath('data.0.id', $j->id);
        }
        $this->getJson('/api/v1/journals?q=%25')->assertOk()->assertJsonCount(0, 'data');
    }

    public function test_hard_filters_unknown_no_apc_and_language_article_indexation(): void
    {
        $language = Language::create(['code' => 'en', 'name' => 'English']);
        $type = ArticleType::create(['code' => 'research_article', 'name' => 'Research Article']);
        $index = IndexationService::create(['code' => 'doaj', 'name' => 'DOAJ']);
        $yes = $this->publish('Eligible', ['language' => $language, 'article_type' => $type, 'indexation' => $index, 'fee' => null]);
        $this->publish('Unknown');
        $this->publish('Paid', ['fee' => '100']);
        foreach (['language=en', 'article_type=research_article', 'indexation=doaj', 'no_apc=true'] as $filter) {
            $this->getJson('/api/v1/journals?'.$filter)->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $yes->id);
        }
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Unknown', 'language' => 'en', 'article_type' => 'research_article', 'no_apc_only' => true, 'indexation_services' => ['doaj']])
            ->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $yes->id)->assertJsonPath('data.0.apc.status', 'no_apc');
    }

    public function test_latest_reviews_and_conflicts_remove_current_metadata(): void
    {
        $j = $this->publish('Verified');
        $claim = $j->assertions()->where('field', 'title')->sole();
        Fixture::review($claim, VerificationStatus::Stale);
        $this->getJson('/api/v1/journals')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/journals/'.$j->id)->assertNotFound();
    }

    public function test_pagination_and_stable_uuid_order(): void
    {
        $ids = [];
        for ($i = 0; $i < 3; $i++) {
            $ids[] = $this->publish('Journal '.$i)->id;
        }
        sort($ids);
        $this->getJson('/api/v1/journals?per_page=2')->assertOk()->assertJsonCount(2, 'data')->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('meta.total', 3);
        $this->getJson('/api/v1/journals?per_page=2&page=2')->assertOk()->assertJsonPath('data.0.id', $ids[2]);
    }

    public function test_default_limit_and_deterministic_ties(): void
    {
        $ids = [];
        for ($i = 0; $i < 6; $i++) {
            $ids[] = $this->publish('Matching Journal')->id;
        }
        sort($ids);
        $input = ['title' => 'Matching'];
        $first = $this->postJson('/api/v1/journal-recommendations', $input)->assertOk()->assertJsonCount(5, 'data')->assertJsonPath('data.0.id', $ids[0])->assertJsonPath('data.0.relevance_score', 100);
        $second = $this->postJson('/api/v1/journal-recommendations', $input)->assertOk();
        $this->assertSame($first->json(), $second->json());
        $this->postJson('/api/v1/journal-recommendations', $input + ['limit' => 2])->assertOk()->assertJsonCount(2, 'data');
    }

    public function test_exact_hierarchical_and_combined_active_normalization(): void
    {
        $root = Subject::create(['name' => 'Computer Science']);
        $child = Subject::create(['name' => 'Machine Learning', 'parent_id' => $root->id]);
        $exact = $this->publish('Learning Research', ['subject' => $child]);
        $ancestor = $this->publish('Computer Science', ['subject' => $root]);
        $r = $this->postJson('/api/v1/journal-recommendations', ['title' => 'Learning', 'subject_ids' => [$child->id]])->assertOk();
        $r->assertJsonPath('data.0.id', $exact->id)->assertJsonPath('data.0.relevance_score', 100)->assertJsonPath('data.1.id', $ancestor->id)->assertJsonPath('data.1.relevance_score', 42);
        $this->assertContains('subject_match', array_column($r->json('data.0.reasons'), 'type'));
        $this->assertContains('taxonomy_match', array_column($r->json('data.1.reasons'), 'type'));
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Learning'])->assertOk()->assertJsonPath('data.0.relevance_score', 100)->assertJsonPath('data.0.score_components.taxonomy', null);
        $this->postJson('/api/v1/journal-recommendations', ['title' => '!', 'subject_ids' => [$root->id]])->assertOk()->assertJsonPath('data.0.id', $ancestor->id)->assertJsonPath('data.0.relevance_score', 100);
    }

    public function test_empty_and_fewer_results_no_network_no_writes_no_acceptance_terms(): void
    {
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study'])->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('meta.candidates_after_filters', 0);
        $this->publish('Study');
        $before = DB::table('journal_assertions')->count();
        $catalogs = CatalogRevision::count();
        $r = $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study'])->assertOk()->assertJsonCount(1, 'data');
        $this->assertSame($before, DB::table('journal_assertions')->count());
        $this->assertSame($catalogs, CatalogRevision::count());
        foreach (['acceptance_probability', 'acceptance_score', 'chance_of_acceptance', 'quality_score'] as $term) {
            $this->assertStringNotContainsString($term, $r->getContent());
        }
        Http::assertNothingSent();
    }

    public function test_pending_editorial_proposals_and_expired_facts_are_excluded(): void
    {
        $j = $this->publish('Published');
        Fixture::fee($j, '0', assertionValues: ['original_wording' => 'Pending editorial proposal']);
        $this->getJson('/api/v1/journals?no_apc=true')->assertOk()->assertJsonCount(0, 'data');
        $r = $this->getJson('/api/v1/journals/'.$j->id)->assertOk()->assertJsonPath('data.apc.status', 'unknown');
        $this->assertStringNotContainsString('Pending editorial proposal', $r->getContent());
        $this->assertSame([], $r->json('data.fees'));
    }

    public function test_validation_boundaries_and_invalid_vocabulary(): void
    {
        foreach ([['title' => ''], ['title' => str_repeat('x', 501)], ['title' => 'x', 'abstract' => str_repeat('x', 10001)],
            ['title' => 'x', 'keywords' => array_fill(0, 21, 'x')], ['title' => 'x', 'keywords' => [str_repeat('x', 101)]],
            ['title' => 'x', 'subject_ids' => ['bad']], ['title' => 'x', 'language' => 'unknown'],
            ['title' => 'x', 'article_type' => 'unknown'], ['title' => 'x', 'indexation_services' => ['unknown']],
            ['title' => 'x', 'limit' => 11], ['title' => 'x', 'limit' => 0], ['title' => 'x', 'no_apc_only' => 'yes']] as $input) {
            $this->postJson('/api/v1/journal-recommendations', $input)->assertUnprocessable()->assertJsonPath('error.code', 'validation_error');
        }
        foreach (['per_page=101', 'page=0', 'subject=bad', 'language=unknown', 'indexation=bad'] as $filter) {
            $this->getJson('/api/v1/journals?'.$filter)->assertUnprocessable();
        }
    }

    public function test_numeric_zero_is_known_money_not_explicit_no_apc(): void
    {
        $zero = $this->publish('Zero fee', ['fee' => '0']);
        $paid = $this->publish('Paid fee', ['fee' => '20']);
        $this->publish('Unknown');
        $this->getJson('/api/v1/journals?no_apc=true')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/journals/'.$zero->id)->assertOk()->assertJsonPath('data.apc.status', 'known')->assertJsonPath('data.apc.amount', '0.0000')->assertJsonPath('data.apc.currency', 'USD');
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Zero fee', 'no_apc_only' => true])->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/journals?no_apc=false')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $paid->id);
    }

    public function test_scoped_fee_and_stale_language_cannot_be_generalized(): void
    {
        $type = ArticleType::create(['code' => 'research_article', 'name' => 'Research Article']);
        $language = Language::create(['code' => 'en', 'name' => 'English']);
        $j = Journal::factory()->create(['active' => true]);
        $title = Fixture::assertion($j, JournalField::Title, ['text_value' => 'Scoped Journal'], VerificationStatus::Verified);
        $fee = Fixture::assertion($j, JournalField::Fee, ['availability' => 'not_applicable', 'article_type_id' => $type->id], VerificationStatus::Verified);
        JournalFee::create(['journal_id' => $j->id, 'assertion_id' => $fee->id, 'fee_type' => 'apc', 'basis' => 'per_article', 'applicability' => 'Research articles only.']);
        $lang = Fixture::assertion($j, JournalField::Language, [], VerificationStatus::Verified);
        JournalLanguage::create(['journal_id' => $j->id, 'assertion_id' => $lang->id, 'language_id' => $language->id, 'supported' => true]);
        app(PublishJournalRevision::class)->publish($j, [$title->id, $fee->id, $lang->id]);
        Fixture::review($lang, VerificationStatus::Stale);
        $this->getJson('/api/v1/journals?no_apc=true')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/journals?language=en')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/journals/'.$j->id)->assertOk()->assertJsonPath('data.apc.status', 'unknown');
    }

    public function test_conflicting_draft_and_unpublished_revision_never_replace_snapshot(): void
    {
        $j = $this->publish('Published title');
        $id = $j->currentRevision->id;
        JournalRevision::create(['journal_id' => $j->id, 'version' => 2]);
        Fixture::assertion($j, JournalField::Title, ['text_value' => 'Proposed title']);
        $this->getJson('/api/v1/journals?q=Proposed')->assertOk()->assertJsonCount(0, 'data');
        $this->getJson('/api/v1/journals/'.$j->id)->assertOk()->assertJsonPath('data.title', 'Published title')->assertJsonPath('data.revision_id', $id);
        Fixture::assertion($j, JournalField::Title, ['text_value' => 'Conflicting title'], VerificationStatus::Conflicting);
        $this->getJson('/api/v1/journals/'.$j->id)->assertNotFound();
    }

    public function test_descendant_taxonomy_and_weights_are_normalized(): void
    {
        $root = Subject::create(['name' => 'Science']);
        $child = Subject::create(['name' => 'Biology', 'parent_id' => $root->id]);
        $grandchild = Subject::create(['name' => 'Genetics', 'parent_id' => $child->id]);
        $this->publish('Genetics', ['subject' => $grandchild]);
        config(['journal_recommendation.weights' => ['taxonomy' => 6, 'lexical' => 4]]);
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Genetics', 'subject_ids' => [$root->id]])->assertOk()
            ->assertJsonPath('data.0.score_components.taxonomy', 49)->assertJsonPath('data.0.relevance_score', 69.4);
        $this->postJson('/api/v1/journal-recommendations', ['title' => '!', 'subject_ids' => [$root->id]])->assertOk()->assertJsonPath('data.0.relevance_score', 49);
    }

    public function test_array_bounds_and_all_required_indexations(): void
    {
        $doaj = IndexationService::create(['code' => 'doaj', 'name' => 'DOAJ']);
        IndexationService::create(['code' => 'scopus', 'name' => 'Scopus']);
        $this->publish('Study', ['indexation' => $doaj]);
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study', 'indexation_services' => ['doaj', 'scopus']])->assertOk()->assertJsonCount(0, 'data');
        foreach ([['subject_ids' => array_fill(0, 11, 'invalid')], ['indexation_services' => array_fill(0, 11, 'doaj')], ['keywords' => ['same', 'same']]] as $input) {
            $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study'] + $input)->assertUnprocessable();
        }
    }

    public function test_query_count_is_bounded_and_recommendations_write_no_tables(): void
    {
        for ($i = 0; $i < 10; $i++) {
            $this->publish('Journal Study '.$i);
        }
        $counts = [];
        foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname='public'") as $table) {
            $counts[$table->tablename] = DB::table($table->tablename)->count();
        }
        DB::enableQueryLog();
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study', 'limit' => 10])->assertOk()->assertJsonCount(10, 'data');
        $queries = DB::getQueryLog();
        DB::disableQueryLog();
        $this->assertLessThan(35, count($queries));
        foreach ($queries as $query) {
            $this->assertDoesNotMatchRegularExpression('/^(insert|update|delete)\\b/i', $query['query']);
        }
        foreach ($counts as $table => $count) {
            $this->assertSame($count, DB::table($table)->count(), $table);
        }
        Http::assertNothingSent();
    }

    public function test_public_rate_limit_and_safe_error_contract(): void
    {
        config(['journal_recommendation.public_requests_per_minute' => 1]);
        $this->getJson('/api/v1/journals')->assertOk();
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study'])->assertStatus(429)->assertJsonPath('error.code', 'rate_limited');
    }

    public function test_waiver_promotional_and_conditional_zero_are_not_no_apc(): void
    {
        foreach (['Eligible author waiver only', 'Promotional zero during launch', 'Conditional zero for invited manuscripts'] as $condition) {
            $j = Journal::factory()->create(['active' => true]);
            $title = Fixture::assertion($j, JournalField::Title, ['text_value' => $condition], VerificationStatus::Verified);
            $fee = Fixture::assertion($j, JournalField::Fee, status: VerificationStatus::Verified);
            JournalFee::create(['journal_id' => $j->id, 'assertion_id' => $fee->id, 'fee_type' => 'apc', 'amount' => '0', 'currency' => 'USD', 'basis' => 'conditional_waiver', 'applicability' => $condition]);
            app(PublishJournalRevision::class)->publish($j, [$title->id, $fee->id]);
            $this->getJson('/api/v1/journals/'.$j->id)->assertOk()->assertJsonPath('data.apc.status', 'known');
        }
        $explicit = $this->publish('Explicit absence', ['fee' => null]);
        $this->publish('Unknown');
        $this->publish('Paid', ['fee' => '100']);
        $this->getJson('/api/v1/journals?no_apc=true')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $explicit->id);
        $this->postJson('/api/v1/journal-recommendations', ['title' => 'Study', 'no_apc_only' => true])->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $explicit->id);
        Http::assertNothingSent();
    }

    public function test_config_validation_and_bounds(): void
    {
        $scorer = app(JournalRelevanceScorer::class);
        $this->assertCount(24, $scorer->tokens(['title' => implode(' ', range(100, 150))]));
        config(['journal_recommendation.weights.lexical' => -1]);
        $this->expectException(\InvalidArgumentException::class);
        $scorer->settings();
    }
}
