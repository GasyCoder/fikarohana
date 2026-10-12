<?php

namespace Tests\Feature;

use App\Enums\JournalField;
use App\Enums\JournalSourceType;
use App\Enums\VerificationStatus;
use App\Models\ArticleType;
use App\Models\AssertionReview;
use App\Models\CatalogRevision;
use App\Models\CatalogRevisionJournal;
use App\Models\IndexationService;
use App\Models\Journal;
use App\Models\JournalArticleType;
use App\Models\JournalAssertion;
use App\Models\JournalFee;
use App\Models\JournalIdentifier;
use App\Models\JournalIndexation;
use App\Models\JournalLanguage;
use App\Models\JournalRequirement;
use App\Models\JournalRevision;
use App\Models\JournalRevisionAssertion;
use App\Models\JournalSource;
use App\Models\JournalSubject;
use App\Models\JournalTemplate;
use App\Models\Language;
use App\Models\Publisher;
use App\Models\SourceRecord;
use App\Models\Subject;
use App\Rules\Issn;
use App\Services\JournalCatalog\PublishJournalRevision;
use App\Services\JournalCatalog\SourcePrecedence;
use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use InvalidArgumentException;
use LogicException;
use Tests\Fixtures\JournalCatalogFixture as Fixture;
use Tests\TestCase;

class JournalCatalogTest extends TestCase
{
    use RefreshDatabase;

    private function constraint(callable $operation, string $state = '23514'): void
    {
        try {
            DB::transaction($operation);
        } catch (QueryException $exception) {
            $this->assertSame($state, $exception->errorInfo[0]);

            return;
        }
        $this->fail('Expected PostgreSQL to reject an invalid catalog operation.');
    }

    private function title(Journal $journal): JournalAssertion
    {
        return Fixture::assertion($journal, JournalField::Title, status: VerificationStatus::Verified);
    }

    private function publish(Journal $journal, array $claims): CatalogRevision
    {
        return app(PublishJournalRevision::class)->publish($journal, array_map(fn ($claim) => $claim->id, $claims));
    }

    public function test_journal_identity_publisher_uuid7_and_same_title(): void
    {
        $this->assertSame('pgsql', DB::connection()->getDriverName());
        $publisher = Publisher::factory()->create(['name' => ' Synthetic   Publisher ']);
        $first = Journal::factory()->create(['title' => ' Synthetic   Journal ', 'publisher_id' => $publisher->id]);
        $second = Journal::factory()->create(['title' => $first->title]);
        $this->assertSame('synthetic publisher', $publisher->normalized_name);
        $this->assertSame('synthetic journal', $first->normalized_title);
        $this->assertSame(' Synthetic   Journal ', $first->title);
        $this->assertSame($publisher->id, $first->publisher->id);
        $this->assertSame(1, $publisher->journals()->count());
        $this->assertNotSame($first->id, $second->id);
        $this->assertTrue(Str::isUuid($first->id));
        $this->assertSame('7', $first->id[14]);
        $this->assertFalse($first->active);
        $this->assertNull($first->currentRevision);
    }

    public function test_issn_checksum_normalization_and_reusable_validation(): void
    {
        $this->assertSame('1234-5679', CatalogNormalizer::issn(' 1234 5679 '));
        $this->assertSame('0000-006X', CatalogNormalizer::issn('0000006x'));
        $this->assertTrue(Validator::make(['issn' => '12345679'], ['issn' => [new Issn]])->passes());
        $this->assertFalse(Validator::make(['issn' => '1234-5678'], ['issn' => [new Issn]])->passes());
        $this->assertFalse(Validator::make(['issn' => []], ['issn' => [new Issn]])->passes());
        $this->expectException(InvalidArgumentException::class);
        CatalogNormalizer::issn('not-an-issn');
    }

    public function test_identifiers_have_unique_cross_type_ownership_and_database_checksum(): void
    {
        $journal = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Identifier);
        $identifier = JournalIdentifier::create(['journal_id' => $journal->id, 'assertion_id' => $claim->id, 'type' => 'issn', 'value' => '12345679']);
        $this->assertSame('1234-5679', $identifier->normalized_value);
        $this->assertSame($claim->id, $identifier->assertion->id);
        $other = Journal::factory()->create();
        $otherClaim = Fixture::assertion($other, JournalField::Identifier);
        $this->constraint(fn () => JournalIdentifier::create(['journal_id' => $other->id, 'assertion_id' => $otherClaim->id, 'type' => 'eissn', 'value' => '1234-5679']), '23505');
        $this->constraint(fn () => DB::table('journal_identifiers')->insert([
            'id' => (string) Str::uuid7(), 'journal_id' => $other->id, 'assertion_id' => $otherClaim->id,
            'type' => 'issn', 'value' => '1234-5678', 'normalized_value' => '1234-5678',
        ]));
        $this->assertSame(1, JournalIdentifier::count());
    }

    public function test_subject_hierarchy_rejects_cycles_and_has_evidenced_journal_links(): void
    {
        $root = Subject::create(['name' => 'Synthetic Science']);
        $child = Subject::create(['name' => 'Synthetic Subfield', 'parent_id' => $root->id]);
        $this->assertSame($root->id, $child->parent->id);
        $this->assertSame($child->id, $root->children->first()->id);
        $this->constraint(fn () => DB::table('subjects')->where('id', $root->id)->update(['parent_id' => $child->id]));
        $this->constraint(fn () => Subject::create(['name' => 'synthetic science']), '23505');
        $journal = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Subject, status: VerificationStatus::Verified);
        $link = JournalSubject::create(['journal_id' => $journal->id, 'assertion_id' => $claim->id, 'subject_id' => $child->id]);
        $this->assertSame($child->id, $journal->subjectLinks->first()->subject->id);
        $this->assertSame(VerificationStatus::Verified, $link->assertion->verificationStatus());
        $this->constraint(fn () => $root->update(['name' => 'Rewritten taxonomy']));
    }

    public function test_verified_french_and_article_types_are_evidenced_not_inferred(): void
    {
        $journal = Journal::factory()->create();
        $unknown = Journal::factory()->create(['official_website_url' => 'https://journal.example.test/fr']);
        $french = Language::create(['code' => 'fr', 'name' => 'French']);
        $article = ArticleType::create(['code' => 'research_article', 'name' => 'Research article']);
        $languageClaim = Fixture::assertion($journal, JournalField::Language, status: VerificationStatus::Verified);
        $typeClaim = Fixture::assertion($journal, JournalField::ArticleType, status: VerificationStatus::Verified);
        $language = JournalLanguage::create(['journal_id' => $journal->id, 'assertion_id' => $languageClaim->id, 'language_id' => $french->id, 'supported' => true]);
        JournalArticleType::create(['journal_id' => $journal->id, 'assertion_id' => $typeClaim->id, 'article_type_id' => $article->id, 'supported' => true]);
        $this->assertSame('fr', $journal->languageLinks->first()->language->code);
        $this->assertTrue($language->supported);
        $this->assertSame($article->id, $journal->articleTypeLinks->first()->articleType->id);
        $this->assertSame(VerificationStatus::Verified, $language->assertion->verificationStatus());
        $this->assertCount(0, $unknown->languageLinks);
        $this->constraint(fn () => DB::table('languages')->insert(['id' => (string) Str::uuid7(), 'code' => 'invalid-language-code', 'name' => 'Unknown', 'normalized_name' => 'unknown']));
    }

    public function test_explicit_zero_unknown_and_paid_fees_are_distinct_exact_decimals(): void
    {
        $journal = Journal::factory()->create();
        $free = Fixture::fee($journal, '0', VerificationStatus::Verified);
        $unknown = Fixture::fee($journal, null);
        $paid = Fixture::fee($journal, '500.25', VerificationStatus::Verified);
        $this->assertSame('0.0000', $free->amount);
        $this->assertSame('USD', $free->currency);
        $this->assertNull($unknown->amount);
        $this->assertNull($unknown->currency);
        $this->assertSame('unknown', $unknown->assertion->availability->value);
        $this->assertSame(VerificationStatus::Pending, $unknown->assertion->verificationStatus());
        $this->assertSame('500.2500', $paid->amount);
        $this->assertSame(VerificationStatus::Verified, $free->assertion->verificationStatus());
        $this->assertSame(3, $journal->fees()->count());
    }

    public function test_database_fee_checks_require_currency_and_unknown_has_no_amount(): void
    {
        $journal = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Fee);
        $row = ['id' => (string) Str::uuid7(), 'journal_id' => $journal->id, 'assertion_id' => $claim->id, 'fee_type' => 'apc', 'amount' => '20', 'basis' => 'per_article', 'applicability' => 'Synthetic context'];
        $this->constraint(fn () => DB::table('journal_fees')->insert($row));
        $this->constraint(fn () => DB::table('journal_fees')->insert(array_replace($row, ['amount' => '-1', 'currency' => 'USD'])));
        $this->constraint(fn () => DB::table('journal_fees')->insert(array_replace($row, ['amount' => 'NaN', 'currency' => 'USD'])));
        $this->constraint(fn () => DB::table('journal_fees')->insert(array_replace($row, ['currency' => 'usd'])));
        $unknown = Fixture::assertion($journal, JournalField::Fee, ['availability' => 'unknown']);
        $this->constraint(fn () => DB::table('journal_fees')->insert(array_replace($row, ['assertion_id' => $unknown->id, 'currency' => 'USD'])));
        $this->assertSame(0, JournalFee::count());
    }

    public function test_requirements_use_controlled_typed_values(): void
    {
        $journal = Journal::factory()->create();
        $wordClaim = Fixture::assertion($journal, JournalField::Requirement);
        $word = JournalRequirement::create(['journal_id' => $journal->id, 'assertion_id' => $wordClaim->id, 'requirement_type' => 'abstract_words', 'operator' => 'max', 'value_number' => 250]);
        $this->assertSame(250, $word->value_number);
        $formatsClaim = Fixture::assertion($journal, JournalField::Requirement);
        $formats = JournalRequirement::create(['journal_id' => $journal->id, 'assertion_id' => $formatsClaim->id, 'requirement_type' => 'file_formats', 'operator' => 'list', 'value_items' => ['docx', 'tex']]);
        $this->assertSame(['docx', 'tex'], $formats->fresh()->value_items);
        $bad = Fixture::assertion($journal, JournalField::Requirement);
        $this->constraint(fn () => DB::table('journal_requirements')->insert(['id' => (string) Str::uuid7(), 'journal_id' => $journal->id, 'assertion_id' => $bad->id, 'requirement_type' => 'abstract_words', 'operator' => 'max', 'value_text' => '250']));
        $this->constraint(fn () => DB::table('journal_requirements')->insert(['id' => (string) Str::uuid7(), 'journal_id' => $journal->id, 'assertion_id' => $bad->id, 'requirement_type' => 'file_formats', 'operator' => 'list', 'value_items' => '["docx", 4]']));
        $this->constraint(fn () => DB::table('journal_requirements')->insert(['id' => (string) Str::uuid7(), 'journal_id' => $journal->id, 'assertion_id' => $bad->id, 'requirement_type' => 'file_formats', 'operator' => 'list', 'value_items' => '["   "]']));
        $this->constraint(fn () => DB::table('journal_requirements')->insert(['id' => (string) Str::uuid7(), 'journal_id' => $journal->id, 'assertion_id' => $bad->id, 'requirement_type' => 'file_formats', 'operator' => 'list', 'value_items' => '"docx"']));
    }

    public function test_template_and_indexation_provenance_are_explicit(): void
    {
        $journal = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Template, status: VerificationStatus::Verified);
        $template = JournalTemplate::create(['journal_id' => $journal->id, 'assertion_id' => $claim->id, 'format' => 'word', 'official_url' => 'https://JOURNAL.example.test/template.docx', 'label' => 'Synthetic official template']);
        $this->assertSame('https://journal.example.test/template.docx', $template->official_url);
        $this->assertSame('official_journal', $template->assertion->sourceRecord->journalSource->type->value);
        $this->assertNotNull($template->assertion->latestReview->verified_at);
        $registry = JournalSource::factory()->create(['type' => 'index_registry']);
        $observation = SourceRecord::factory()->create(['journal_source_id' => $registry->id]);
        $indexClaim = Fixture::assertion($journal, JournalField::Indexation, status: VerificationStatus::Verified, source: $observation);
        $index = IndexationService::create(['code' => 'synthetic_index', 'name' => 'Synthetic Index']);
        $membership = JournalIndexation::create(['journal_id' => $journal->id, 'assertion_id' => $indexClaim->id, 'indexation_service_id' => $index->id, 'supported' => true]);
        $this->assertTrue($membership->supported);
        $this->assertSame($observation->id, $membership->assertion->sourceRecord->id);
        $aggregator = JournalSource::factory()->create(['type' => 'openalex']);
        $badSource = SourceRecord::factory()->create(['journal_source_id' => $aggregator->id]);
        $badClaim = Fixture::assertion($journal, JournalField::Template, source: $badSource);
        $this->constraint(fn () => JournalTemplate::create(['journal_id' => $journal->id, 'assertion_id' => $badClaim->id, 'format' => 'word', 'official_url' => 'https://journal.example.test/template.docx', 'label' => 'Unsupported official claim']));
    }

    public function test_source_records_have_idempotency_keys_and_immutable_observations(): void
    {
        $record = SourceRecord::factory()->create();
        $same = SourceRecord::firstOrCreate([
            'journal_source_id' => $record->journal_source_id, 'external_id' => $record->external_id,
            'revision_fingerprint' => $record->revision_fingerprint,
        ], ['source_url' => $record->source_url, 'retrieved_at' => now()]);
        $this->assertSame($record->id, $same->id);
        $this->assertTrue($record->retrieved_at->equalTo($same->retrieved_at));
        $this->constraint(fn () => DB::table('source_records')->insert(array_replace($record->getAttributes(), ['id' => (string) Str::uuid7()])), '23505');
        $new = SourceRecord::factory()->create(['journal_source_id' => $record->journal_source_id, 'external_id' => $record->external_id, 'revision_fingerprint' => 'synthetic-v2']);
        $this->assertNotSame($record->id, $new->id);
        $this->constraint(fn () => $record->update(['retrieved_at' => now()]));
        $this->constraint(fn () => $record->journalSource->update(['type' => 'crossref']));
    }

    public function test_field_and_journal_provenance_are_enforced_by_composite_foreign_keys(): void
    {
        $journal = Journal::factory()->create();
        $other = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Fee, status: VerificationStatus::Verified);
        $row = ['id' => (string) Str::uuid7(), 'journal_id' => $other->id, 'assertion_id' => $claim->id, 'fee_type' => 'apc', 'amount' => '0', 'currency' => 'USD', 'basis' => 'per_article', 'applicability' => 'Synthetic'];
        $this->constraint(fn () => DB::table('journal_fees')->insert($row), '23503');
        $wrongField = Fixture::assertion($journal, JournalField::Language);
        $this->constraint(fn () => DB::table('journal_fees')->insert(array_replace($row, ['journal_id' => $journal->id, 'assertion_id' => $wrongField->id])), '23503');
        $this->assertNotNull($claim->sourceRecord->retrieved_at);
        $this->assertSame('manual', $claim->latestReview->method);
        $this->assertSame('synthetic-reviewer', $claim->latestReview->reviewer_reference);
    }

    public function test_conflicting_and_stale_assertions_are_retained_and_not_published(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $first = Fixture::fee($journal, '100', VerificationStatus::Conflicting);
        $second = Fixture::fee($journal, '900', VerificationStatus::Conflicting);
        Fixture::review($first->assertion, VerificationStatus::Stale);
        $this->assertSame(VerificationStatus::Stale, $first->assertion->verificationStatus());
        $this->assertSame(2, $first->assertion->reviews()->count());
        $this->assertSame(VerificationStatus::Conflicting, $second->assertion->verificationStatus());
        $this->expectException(DomainException::class);
        try {
            $this->publish($journal, [$title, $second->assertion]);
        } finally {
            $this->assertSame(2, JournalFee::count());
            $this->assertSame(0, JournalRevision::count());
        }
    }

    public function test_verified_competing_fees_require_review_resolution(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $first = Fixture::fee($journal, '0', VerificationStatus::Verified);
        $second = Fixture::fee($journal, '500', VerificationStatus::Verified);
        try {
            $this->publish($journal, [$title, $first->assertion]);
            $this->fail('Competing fees must not silently pick a winner.');
        } catch (DomainException) {
            $this->assertSame(0, JournalRevision::count());
        }
        Fixture::review($second->assertion, VerificationStatus::Rejected);
        $catalog = $this->publish($journal, [$title, $first->assertion]);
        $this->assertNotNull($catalog->published_at);
        $this->assertSame(2, JournalFee::count());
        $this->assertSame(2, $second->assertion->reviews()->count());
    }

    public function test_revisions_preserve_values_reviews_and_complete_catalog_snapshots(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $zero = Fixture::fee($journal, '0', VerificationStatus::Verified);
        $first = $this->publish($journal, [$title, $zero->assertion]);
        $firstRevision = $journal->currentRevision()->firstOrFail();
        $originalReview = $zero->assertion->latestReview;
        Fixture::review($zero->assertion, VerificationStatus::Stale);
        $paid = Fixture::fee($journal, '600', VerificationStatus::Verified, ['supersedes_id' => $zero->assertion_id]);
        $second = $this->publish($journal, [$title, $paid->assertion]);
        $this->assertSame(2, $journal->currentRevision()->firstOrFail()->version);
        $this->assertTrue($second->version > $first->version);
        $this->assertSame($firstRevision->id, $first->journals->first()->journal_revision_id);
        $selection = $firstRevision->selections()->where('assertion_id', $zero->assertion_id)->firstOrFail();
        $this->assertSame('0.0000', $selection->assertion->fee->amount);
        $this->assertSame($originalReview->id, $selection->review_id);
        $this->assertSame(VerificationStatus::Verified, $selection->review->status);
        $this->assertSame(VerificationStatus::Stale, $selection->assertion->verificationStatus());
        $this->assertSame($zero->assertion_id, $paid->assertion->supersedes->id);
        $other = Journal::factory()->create();
        $third = $this->publish($other, [$this->title($other)]);
        $this->assertCount(2, $third->journals);
        $this->assertCount(1, $first->journals);
        $this->assertSame(2, $zero->assertion->reviews()->count());
    }

    public function test_history_cannot_be_updated_deleted_or_extended_after_publication(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $fee = Fixture::fee($journal, '0', VerificationStatus::Verified);
        $catalog = $this->publish($journal, [$title, $fee->assertion]);
        $revision = $journal->currentRevision()->firstOrFail();
        $this->constraint(fn () => $fee->update(['amount' => '10']));
        $this->constraint(fn () => $fee->delete());
        $this->constraint(fn () => $title->update(['text_value' => 'Overwritten title']));
        $this->constraint(fn () => $title->latestReview->delete());
        $this->constraint(fn () => $title->sourceRecord->delete());
        $this->constraint(fn () => $revision->update(['version' => 9]));
        $this->constraint(fn () => $catalog->delete());
        $this->constraint(fn () => $revision->selections->first()->delete());
        $otherTitle = Fixture::assertion($journal, JournalField::Title, ['text_value' => 'Replacement'], VerificationStatus::Verified);
        $this->constraint(fn () => JournalRevisionAssertion::create(['journal_revision_id' => $revision->id, 'journal_id' => $journal->id, 'assertion_id' => $otherTitle->id, 'review_id' => $otherTitle->latestReview->id, 'selection_key' => 'late-title']));
        $this->constraint(fn () => CatalogRevisionJournal::create(['catalog_revision_id' => $catalog->id, 'journal_id' => $journal->id, 'journal_revision_id' => $revision->id]));
        $this->assertSame('0.0000', $fee->fresh()->amount);
        $this->assertSame(2, $revision->selections()->count());
    }

    public function test_foreign_keys_deletion_and_optional_unreferenced_identity_cleanup(): void
    {
        $journal = Journal::factory()->create();
        $fee = Fixture::fee($journal, '20');
        $this->constraint(fn () => $journal->delete(), '23503');
        $this->constraint(fn () => $journal->publisher->delete(), '23503');
        $this->constraint(fn () => $fee->assertion->sourceRecord->journalSource->delete(), '23503');
        $this->constraint(fn () => Journal::factory()->create(['publisher_id' => (string) Str::uuid7()]), '23503');
        $unused = Journal::factory()->create();
        $unusedId = $unused->id;
        $unused->delete();
        $this->assertDatabaseMissing('journals', ['id' => $unusedId]);
        $this->assertSame(1, JournalFee::count());
        $this->assertSame(1, $journal->assertions()->count());
    }

    public function test_review_status_dates_and_source_precedence_are_explicit(): void
    {
        $journal = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Title);
        $this->assertSame(VerificationStatus::Pending, $claim->verificationStatus());
        $this->constraint(fn () => DB::table('assertion_reviews')->insert(['id' => (string) Str::uuid7(), 'assertion_id' => $claim->id, 'status' => 'verified', 'method' => 'manual', 'reviewed_at' => now()]));
        $this->constraint(fn () => AssertionReview::create(['assertion_id' => $claim->id, 'status' => 'verified', 'method' => 'manual', 'reviewed_at' => now()->subDays(2), 'verified_at' => now()->subDays(2)]));
        $this->constraint(fn () => DB::table('assertion_reviews')->insert(['id' => (string) Str::uuid7(), 'assertion_id' => $claim->id, 'status' => 'high_confidence', 'method' => 'automatic', 'reviewed_at' => now()]));
        $this->assertSame([JournalSourceType::OfficialJournal, JournalSourceType::OfficialPublisher], SourcePrecedence::preferredTypes(JournalField::Fee));
        $this->assertSame([JournalSourceType::IndexRegistry], SourcePrecedence::preferredTypes(JournalField::Indexation));
        $this->assertSame(0, $claim->reviews()->count());
    }

    public function test_urls_are_normalized_without_fetching_and_unsafe_schemes_are_rejected(): void
    {
        $this->assertSame('https://journal.example.test/Path?x=A#Section', CatalogNormalizer::url('https://JOURNAL.EXAMPLE.TEST/Path?x=A#Section'));
        $this->constraint(fn () => DB::table('journal_sources')->insert(['id' => (string) Str::uuid7(), 'name' => 'Synthetic unsafe', 'type' => 'other', 'base_url' => 'javascript:alert(1)']));
        $this->expectException(InvalidArgumentException::class);
        CatalogNormalizer::url('https://user:password@journal.example.test/path');
    }

    public function test_unknown_fees_and_quarantined_sources_cannot_be_published(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $unknown = Fixture::fee($journal, null, VerificationStatus::Verified);
        try {
            $this->publish($journal, [$title, $unknown->assertion]);
            $this->fail('Unknown fee cannot become a verified published amount.');
        } catch (DomainException) {
            $this->assertSame(0, JournalRevision::count());
        }
        $quarantined = SourceRecord::factory()->create(['status' => 'quarantined']);
        $badTitle = Fixture::assertion($journal, JournalField::Title, status: VerificationStatus::Verified, source: $quarantined);
        Fixture::review($title, VerificationStatus::Rejected);
        $this->constraint(fn () => $this->publish($journal, [$badTitle]));
        $this->assertSame(0, CatalogRevision::count());
    }

    public function test_effective_dates_and_same_field_replacement_are_enforced(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $expired = Fixture::fee($journal, '10', VerificationStatus::Verified, ['effective_from' => now()->subDays(3), 'effective_until' => now()->subDay()]);
        $this->constraint(fn () => $this->publish($journal, [$title, $expired->assertion]));
        $other = Journal::factory()->create();
        $this->constraint(fn () => Fixture::assertion($other, JournalField::Fee, ['supersedes_id' => $expired->assertion_id]), '23503');
        $this->constraint(fn () => Fixture::assertion($journal, JournalField::Template, ['supersedes_id' => $expired->assertion_id]), '23503');
        $this->constraint(fn () => Fixture::assertion($journal, JournalField::Fee, ['effective_from' => now(), 'effective_until' => now()->subDay()]));
    }

    public function test_current_usability_excludes_stale_and_competing_facts_without_erasing_history(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $fee = Fixture::fee($journal, '0', VerificationStatus::Verified);
        $this->publish($journal, [$title, $fee->assertion]);
        $revision = $journal->currentRevision()->firstOrFail();
        $this->assertSame(2, $revision->usableSelections()->count());
        $competing = Fixture::fee($journal, '500', VerificationStatus::Conflicting);
        $this->assertSame(1, $revision->usableSelections()->count());
        Fixture::review($competing->assertion, VerificationStatus::Rejected);
        $this->assertSame(2, $revision->usableSelections()->count());
        Fixture::review($fee->assertion, VerificationStatus::Stale);
        $this->assertSame(1, $revision->usableSelections()->count());
        $this->assertSame(2, $revision->selections()->count());
        $this->assertSame('0.0000', $fee->fresh()->amount);
    }

    public function test_database_requires_draft_revisions_real_values_and_current_review_at_sealing(): void
    {
        $journal = Journal::factory()->create();
        $title = $this->title($journal);
        $this->constraint(fn () => JournalRevision::create(['journal_id' => $journal->id, 'version' => 1, 'published_at' => now()]));
        $revision = JournalRevision::create(['journal_id' => $journal->id, 'version' => 1]);
        $missingFee = Fixture::assertion($journal, JournalField::Fee, status: VerificationStatus::Verified);
        $this->constraint(fn () => JournalRevisionAssertion::create([
            'journal_revision_id' => $revision->id, 'journal_id' => $journal->id, 'assertion_id' => $missingFee->id,
            'review_id' => $missingFee->latestReview->id, 'selection_key' => 'fee:apc::',
        ]));
        JournalRevisionAssertion::create([
            'journal_revision_id' => $revision->id, 'journal_id' => $journal->id, 'assertion_id' => $title->id,
            'review_id' => $title->latestReview->id, 'selection_key' => $title->selectionKey(),
        ]);
        Fixture::review($title, VerificationStatus::Stale);
        $this->constraint(fn () => $revision->update(['published_at' => now()]));
        $this->assertNull($revision->fresh()->published_at);
    }

    public function test_explicit_not_applicable_fee_and_unsupported_language_need_evidence(): void
    {
        $journal = Journal::factory()->create();
        $claim = Fixture::assertion($journal, JournalField::Fee, ['availability' => 'not_applicable', 'original_wording' => 'Synthetic fee does not apply to this context.'], VerificationStatus::Verified);
        $fee = JournalFee::create(['journal_id' => $journal->id, 'assertion_id' => $claim->id, 'fee_type' => 'apc', 'basis' => 'per_article', 'applicability' => 'Synthetic context']);
        $this->assertNull($fee->amount);
        $this->assertSame('not_applicable', $fee->assertion->availability->value);
        $french = Language::create(['code' => 'fr', 'name' => 'French']);
        $languageClaim = Fixture::assertion($journal, JournalField::Language, status: VerificationStatus::Verified);
        $language = JournalLanguage::create(['journal_id' => $journal->id, 'assertion_id' => $languageClaim->id, 'language_id' => $french->id, 'supported' => false]);
        $this->assertFalse($language->supported);
        $this->assertSame(VerificationStatus::Verified, $language->assertion->verificationStatus());
        $catalog = $this->publish($journal, [$this->title($journal), $claim, $languageClaim]);
        $this->assertCount(3, $catalog->journals->first()->journalRevision->selections);
        $this->constraint(fn () => $language->delete());
        $this->constraint(fn () => $french->delete(), '23503');
        $this->assertDatabaseHas('journal_assertions', ['id' => $languageClaim->id]);
        $this->constraint(fn () => $french->update(['code' => 'en']));
    }

    public function test_catalog_migration_rollback_preserves_vector_extension(): void
    {
        $migration = require database_path('migrations/2026_10_11_170000_create_journal_catalog.php');
        try {
            DB::transaction(function () use ($migration): void {
                (require database_path('migrations/2026_10_12_100000_add_official_source_foundation.php'))->down();
                (require database_path('migrations/2026_10_12_090000_add_provider_taxonomy_to_subjects.php'))->down();
                // Dependent ingestion tables must roll back before their catalog parents.
                (require database_path('migrations/2026_10_11_180000_create_journal_ingestion.php'))->down();
                $migration->down();
                $this->assertFalse(Schema::hasTable('journals'));
                $this->assertFalse(Schema::hasTable('journal_assertions'));
                $this->assertTrue(DB::selectOne("SELECT EXISTS (SELECT 1 FROM pg_extension WHERE extname = 'vector') AS enabled")->enabled);
                throw new LogicException('Restore the isolated test schema through transaction rollback.');
            });
        } catch (LogicException $exception) {
            $this->assertSame('Restore the isolated test schema through transaction rollback.', $exception->getMessage());
        }
        $this->assertTrue(Schema::hasTable('journal_assertions'));
    }

    public function test_fees_for_distinct_article_contexts_can_share_a_revision(): void
    {
        $journal = Journal::factory()->create();
        $research = ArticleType::create(['code' => 'research_article', 'name' => 'Research article']);
        $review = ArticleType::create(['code' => 'review', 'name' => 'Review']);
        $first = Fixture::fee($journal, '0', VerificationStatus::Verified, ['article_type_id' => $research->id]);
        $second = Fixture::fee($journal, '100', VerificationStatus::Verified, ['article_type_id' => $review->id]);
        $catalog = $this->publish($journal, [$this->title($journal), $first->assertion, $second->assertion]);
        $revision = $catalog->journals->first()->journalRevision;
        $this->assertSame(3, $revision->usableSelections()->count());
        $this->assertNotSame($first->assertion->selectionKey(), $second->assertion->selectionKey());
        $this->assertSame($research->id, $first->assertion->articleType->id);
        $this->assertSame($review->id, $second->assertion->articleType->id);
    }
}
