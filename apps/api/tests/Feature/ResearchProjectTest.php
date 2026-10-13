<?php

namespace Tests\Feature;

use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\ArticleType;
use App\Models\Journal;
use App\Models\JournalFee;
use App\Models\JournalRequirement;
use App\Models\JournalTemplate;
use App\Models\ResearchProject;
use App\Models\User;
use App\Services\JournalCatalog\PublishJournalRevision;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use Tests\Fixtures\JournalCatalogFixture as Fixture;
use Tests\TestCase;

class ResearchProjectTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
    }

    private function project(User $user): ResearchProject
    {
        $p = new ResearchProject(['title' => 'Private research', 'keywords' => ['private'], 'subject_ids' => []]);
        $p->user_id = $user->id;
        $p->save();

        return $p;
    }

    private function journal(?string $fee = null, bool $noApc = false, bool $extras = false): Journal
    {
        $j = Journal::factory()->create(['active' => true]);
        $claims = [Fixture::assertion($j, JournalField::Title, ['text_value' => 'Canonical journal'], VerificationStatus::Verified)];
        if ($fee !== null || $noApc) {
            $a = Fixture::assertion($j, JournalField::Fee, ['availability' => $noApc ? 'not_applicable' : 'known'], VerificationStatus::Verified);
            JournalFee::create(['journal_id' => $j->id, 'assertion_id' => $a->id, 'fee_type' => 'apc', 'amount' => $fee, 'currency' => $noApc ? null : 'USD', 'basis' => 'per_article', 'applicability' => 'Synthetic conditional context; retain this qualifier.']);
            $claims[] = $a;
        }
        if ($extras) {
            $a = Fixture::assertion($j, JournalField::Requirement, [], VerificationStatus::Verified);
            JournalRequirement::create(['journal_id' => $j->id, 'assertion_id' => $a->id, 'requirement_type' => 'abstract_words', 'operator' => 'max', 'value_number' => 250, 'notes' => 'Structured abstract only.']);
            $claims[] = $a;
            $a = Fixture::assertion($j, JournalField::Template, [], VerificationStatus::Verified);
            JournalTemplate::create(['journal_id' => $j->id, 'assertion_id' => $a->id, 'format' => 'word', 'official_url' => 'https://journal.example/template.docx', 'label' => 'Official template']);
            $claims[] = $a;
        }
        app(PublishJournalRevision::class)->publish($j, array_map(fn ($a) => $a->id, $claims));

        return $j;
    }

    public function test_all_project_operations_require_authentication(): void
    {
        $p = $this->project(User::factory()->create());
        foreach ([['GET', '/projects', []], ['POST', '/projects', ['title' => 'Private']], ['GET', '/projects/'.$p->id, []], ['PATCH', '/projects/'.$p->id, ['title' => 'Changed']], ['DELETE', '/projects/'.$p->id, []], ['PUT', '/projects/'.$p->id.'/journal', ['journal_id' => $p->id]]] as [$method,$path,$body]) {
            $r = $this->json($method, '/api/v1'.$path, $body)->assertUnauthorized();
            $this->assertStringNotContainsString('Private research', $r->getContent());
        }
    }

    public function test_owner_crud_and_paginated_list_isolation(): void
    {
        $u = User::factory()->create();
        $other = $this->project(User::factory()->create());
        $this->actingAs($u);
        $id = $this->postJson('/api/v1/projects', ['title' => 'Study', 'abstract' => 'Confidential summary', 'keywords' => ['ecology'], 'no_apc_only' => true])->assertCreated()->assertJsonPath('data.journal', null)->assertJsonPath('data.no_apc_only', true)->json('data.id');
        $this->getJson('/api/v1/projects')->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.id', $id);
        $this->getJson('/api/v1/projects/'.$id)->assertOk()->assertJsonPath('data.abstract', 'Confidential summary');
        $this->patchJson('/api/v1/projects/'.$id, ['title' => 'Revised', 'abstract' => null])->assertOk()->assertJsonPath('data.title', 'Revised')->assertJsonPath('data.abstract', null);
        $this->deleteJson('/api/v1/projects/'.$id)->assertNoContent();
        $this->assertDatabaseMissing('research_projects', ['id' => $id]);
        $this->assertDatabaseHas('research_projects', ['id' => $other->id]);
    }

    public function test_cross_user_policy_denies_all_object_operations(): void
    {
        $p = $this->project(User::factory()->create());
        $this->actingAs(User::factory()->create());
        foreach ([['GET', []], ['PATCH', ['title' => 'Stolen']], ['DELETE', []]] as [$m,$body]) {
            $this->json($m, '/api/v1/projects/'.$p->id, $body)->assertForbidden();
        }
        $this->putJson('/api/v1/projects/'.$p->id.'/journal', ['journal_id' => $p->id])->assertForbidden();
        $this->assertDatabaseHas('research_projects', ['id' => $p->id, 'title' => 'Private research']);
    }

    public function test_selection_replacement_and_deletion_preserve_catalog(): void
    {
        $u = User::factory()->create();
        $p = $this->project($u);
        $a = $this->journal();
        $b = $this->journal();
        $this->actingAs($u);
        foreach ([$a, $b] as $j) {
            $this->putJson('/api/v1/projects/'.$p->id.'/journal', ['journal_id' => $j->id])->assertOk()->assertJsonPath('data.selected_journal_id', $j->id)->assertJsonPath('data.journal.title', 'Canonical journal');
        }
        $this->deleteJson('/api/v1/projects/'.$p->id)->assertNoContent();
        $this->assertDatabaseHas('journals', ['id' => $a->id]);
        $this->assertDatabaseHas('journals', ['id' => $b->id]);
    }

    public function test_unpublished_inactive_and_stale_journals_cannot_be_selected(): void
    {
        $u = User::factory()->create();
        $p = $this->project($u);
        $unpublished = Journal::factory()->create(['active' => true]);
        $inactive = $this->journal();
        $inactive->update(['active' => false]);
        $stale = $this->journal();
        Fixture::review($stale->assertions()->where('field', 'title')->sole(), VerificationStatus::Stale);
        $this->actingAs($u);
        foreach ([$unpublished, $inactive, $stale] as $j) {
            $this->putJson('/api/v1/projects/'.$p->id.'/journal', ['journal_id' => $j->id])->assertUnprocessable();
        }
    }

    public function test_canonical_projection_preserves_qualifiers_and_excludes_internal_proposals(): void
    {
        $u = User::factory()->create();
        $p = $this->project($u);
        $j = $this->journal('0', extras: true);
        Fixture::assertion($j, JournalField::Scope, ['text_value' => 'Unreviewed editorial secret']);
        $this->actingAs($u);
        $r = $this->putJson('/api/v1/projects/'.$p->id.'/journal', ['journal_id' => $j->id])->assertOk()->assertJsonPath('data.journal.apc.status', 'known')->assertJsonPath('data.journal.apc.amount', '0.0000')->assertJsonPath('data.journal.requirements.0.value_number', 250)->assertJsonPath('data.journal.requirements.0.notes', 'Structured abstract only.')->assertJsonPath('data.journal.templates.0.official_url', 'https://journal.example/template.docx');
        foreach (['assertion_id', 'source_record_id', 'raw_content', 'reviewer_reference', 'Unreviewed editorial secret'] as $private) {
            $this->assertStringNotContainsString($private, $r->getContent());
        }
        $this->assertStringContainsString('retain this qualifier', $r->getContent());
    }

    public function test_unknown_and_explicit_no_apc_remain_distinct(): void
    {
        $u = User::factory()->create();
        $p = $this->project($u);
        $this->actingAs($u);
        foreach ([[$this->journal(), 'unknown'], [$this->journal(noApc: true), 'no_apc']] as [$j,$status]) {
            $this->putJson('/api/v1/projects/'.$p->id.'/journal', ['journal_id' => $j->id])->assertOk()->assertJsonPath('data.journal.apc.status', $status)->assertJsonCount(0, 'data.journal.requirements');
        }
    }

    public function test_selected_journal_becoming_unusable_is_not_projected(): void
    {
        $u = User::factory()->create();
        $p = $this->project($u);
        $j = $this->journal();
        $this->actingAs($u);
        $this->putJson('/api/v1/projects/'.$p->id.'/journal', ['journal_id' => $j->id])->assertOk();
        Fixture::review($j->assertions()->where('field', 'title')->sole(), VerificationStatus::Stale);
        $this->getJson('/api/v1/projects/'.$p->id)->assertOk()->assertJsonPath('data.selected_journal_id', $j->id)->assertJsonPath('data.journal', null);
    }

    public function test_owner_and_selection_cannot_be_mass_assigned_and_input_is_bounded(): void
    {
        $this->actingAs(User::factory()->create());
        $this->postJson('/api/v1/projects', ['title' => 'Study', 'user_id' => 999])->assertUnprocessable();
        $this->postJson('/api/v1/projects', ['title' => str_repeat('a', 501)])->assertUnprocessable();
        $this->postJson('/api/v1/projects', ['title' => 'Study', 'selected_journal_id' => 'arbitrary'])->assertUnprocessable();
    }

    public function test_sanctum_bearer_authentication_works_without_spa_cookies(): void
    {
        $u = User::factory()->create();
        $token = $u->createToken('synthetic', ['*'], now()->addHour())->plainTextToken;
        $this->withToken($token)->postJson('/api/v1/projects', ['title' => 'Mobile project'])->assertCreated();
    }

    public function test_spa_project_writes_require_csrf_and_work_with_existing_session(): void
    {
        config(['sanctum.stateful' => ['localhost:3000']]);
        $this->app['env'] = 'local';
        $user = User::factory()->create();
        $this->actingAs($user, 'web')->withHeader('Origin', 'http://localhost:3000');
        $this->postJson('/api/v1/projects', ['title' => 'Private study'])->assertStatus(419);
        $response = $this->withSession(['_token' => 'synthetic-csrf'])->withHeader('X-CSRF-TOKEN', 'synthetic-csrf')
            ->postJson('/api/v1/projects', ['title' => 'Private study'])->assertCreated();
        $this->assertStringContainsString('no-store', $response->headers->get('Cache-Control'));
    }

    public function test_requirements_follow_project_scope_and_current_verification(): void
    {
        $user = User::factory()->create();
        $project = $this->project($user);
        $type = ArticleType::create(['code' => 'research_article', 'name' => 'Research article']);
        $journal = Journal::factory()->create(['active' => true]);
        $title = Fixture::assertion($journal, JournalField::Title, ['text_value' => 'Scoped journal'], VerificationStatus::Verified);
        $requirement = Fixture::assertion($journal, JournalField::Requirement, ['article_type_id' => $type->id], VerificationStatus::Verified);
        JournalRequirement::create(['journal_id' => $journal->id, 'assertion_id' => $requirement->id, 'requirement_type' => 'abstract_words', 'operator' => 'max', 'value_number' => 200]);
        app(PublishJournalRevision::class)->publish($journal, [$title->id, $requirement->id]);
        $this->actingAs($user);
        $this->putJson('/api/v1/projects/'.$project->id.'/journal', ['journal_id' => $journal->id])->assertOk()->assertJsonCount(0, 'data.journal.requirements');
        $this->patchJson('/api/v1/projects/'.$project->id, ['article_type' => 'research_article'])->assertOk()->assertJsonPath('data.journal.requirements.0.article_type', 'research_article');
        Fixture::review($requirement, VerificationStatus::Stale);
        $this->getJson('/api/v1/projects/'.$project->id)->assertOk()->assertJsonCount(0, 'data.journal.requirements');
    }
}
