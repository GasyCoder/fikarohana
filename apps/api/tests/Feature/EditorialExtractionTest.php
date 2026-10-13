<?php

namespace Tests\Feature;

use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\CatalogRevision;
use App\Models\Journal;
use App\Models\JournalAssertion;
use App\Models\JournalFee;
use App\Models\JournalLanguage;
use App\Models\JournalRequirement;
use App\Models\JournalSource;
use App\Models\SourceRecord;
use App\Services\EditorialExtraction\ExtractEditorialSnapshot;
use App\Services\EditorialExtraction\PrepareDocument;
use App\Services\EditorialExtraction\RuleRegistry;
use App\Services\JournalCatalog\PublishJournalRevision;
use App\Services\JournalIngestion\DetectJournalConflicts;
use App\Services\JournalIngestion\ReviewIngestionAssertion;
use App\Services\OfficialSources\OfficialSources;
use App\Services\OfficialSources\PublicDns;
use DomainException;
use DOMDocument;
use DOMXPath;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Process;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\Fixtures\JournalCatalogFixture;
use Tests\TestCase;

class EditorialExtractionTest extends TestCase
{
    use RefreshDatabase;

    private const URL = 'https://journal.example.org/guidelines';

    protected function setUp(): void
    {
        parent::setUp();
        Http::preventStrayRequests();
        Process::shouldReceive('run')->never();
        $this->mock(PublicDns::class, fn ($mock) => $mock->shouldNotReceive('resolve'));
    }

    private function snapshot(string $body, ?SourceRecord $prior = null, string $mime = 'text/html'): SourceRecord
    {
        $source = $prior?->journalSource;
        if (! $source) {
            $journal = Journal::create(['title' => 'Synthetic extraction journal']);
            $source = app(OfficialSources::class)->register($journal, 'official_journal', 'guidelines', self::URL);
            // Synthetic approved fixture, no acquisition or DNS operation.
            $source->forceFill(['approval_status' => 'approved', 'approved_by' => 'fixture-reviewer',
                'approval_basis' => 'Synthetic fixture only', 'approved_at' => now()])->save();
        }

        return SourceRecord::firstOrCreate(['journal_source_id' => $source->id, 'external_id' => hash('sha256', self::URL),
            'revision_fingerprint' => hash('sha256', self::URL."\n".$mime."\n".$body)],
            ['source_url' => self::URL, 'retrieved_at' => now(), 'content_hash' => hash('sha256', $body), 'status' => 'captured',
                'raw_content' => $body, 'snapshot_metadata' => ['requested_url' => self::URL, 'final_url' => self::URL,
                    'http_status' => 200, 'content_type' => $mime, 'bytes' => strlen($body), 'parser_version' => 'official-page-raw-v1',
                    'etag' => null, 'last_modified' => null]]);
    }

    private function fixture(string $name): SourceRecord
    {
        return $this->snapshot(file_get_contents(base_path('tests/Fixtures/official-sources/extraction/'.$name.'.html')));
    }

    private function extract(SourceRecord $record, bool $dry = false): array
    {
        $result = app(ExtractEditorialSnapshot::class)->extract($record, $dry);
        Http::assertNothingSent();

        return $result;
    }

    private function counts(): array
    {
        $counts = [];
        foreach (DB::select("SELECT tablename FROM pg_tables WHERE schemaname = 'public' ORDER BY tablename") as $row) {
            $counts[$row->tablename] = DB::table($row->tablename)->count();
        }

        return $counts;
    }

    #[DataProvider('feeCases')]
    public function test_fee_semantics(string $text, ?string $amount, ?string $currency, ?string $kind, ?string $basis = null): void
    {
        $result = $this->extract($this->snapshot('<p>'.$text.'</p>'));
        if ($kind === null) {
            $this->assertSame([], $result['proposals']);
            $this->assertSame(0, JournalFee::count());
        } else {
            $this->assertCount(1, $result['proposals']);
            $fee = JournalFee::firstOrFail();
            $this->assertSame($amount, $fee->amount);
            $this->assertSame($currency, $fee->currency);
            $this->assertSame($kind, $fee->fee_type->value);
            if ($basis !== null) {
                $this->assertSame($basis, $fee->basis);
            }
            $this->assertSame('pending', $fee->assertion->verificationStatus()->value);
            $this->assertSame($amount === null ? 'unknown' : 'known', $fee->assertion->availability->value);
        }
    }

    public static function feeCases(): array
    {
        return [
            'USD grouped decimal' => ['Article Processing Charge: USD 1,500.25', '1500.2500', 'USD', 'apc'],
            'EUR symbol' => ['APC: €1200', '1200.0000', 'EUR', 'apc'],
            'EUR decimal comma' => ['APC: 1200,50 EUR', '1200.5000', 'EUR', 'apc'],
            'spaces thousands' => ['APC: USD 1 500.25', '1500.2500', 'USD', 'apc'],
            'explicit zero' => ['APC: USD 0', '0.0000', 'USD', 'apc'],
            'submission separate' => ['Submission fee: 500 GBP', '500.0000', 'GBP', 'submission'],
            'generic publication' => ['Publication fee: 500 GBP', '500.0000', 'GBP', 'other'],
            'page charge' => ['Page charge: USD 25', '25.0000', 'USD', 'page'],
            'color charge' => ['Color charge: EUR 20', '20.0000', 'EUR', 'color'],
            'explicit absence' => ['No article processing charges', null, null, 'apc', 'official_reported_no_apc'],
            'explicit no APC' => ['There is no APC.', null, null, 'apc', 'official_reported_no_apc'],
            'no publication charges' => ['Authors are not charged publication fees.', null, null, 'apc', 'official_reported_no_apc'],
            'unknown' => ['Fees may apply.', null, null, null],
            'missing' => ['Author guidance', null, null, null],
            'no currency' => ['APC: 1200', null, null, null],
            'waiver' => ['APC: USD 1500, waived for eligible authors', null, null, null],
            'conditional absence' => ['There is no APC for eligible authors.', null, null, null],
            'promotion' => ['No article processing charges until December.', null, null, null],
            'discount' => ['APC: USD 500 discounted from USD 1000', null, null, null],
            'optional' => ['Optional service: USD 50', null, null, null],
            'range' => ['APC: USD 500-1000', null, null, null],
            'ambiguous grouping' => ['APC: EUR 1.200,50', null, null, null],
            'too big' => ['APC: USD 10000000000', null, null, null],
        ];
    }

    #[DataProvider('textCases')]
    public function test_explicit_text_rules(string $html, string $field, int $count, ?string $value = null): void
    {
        $result = $this->extract($this->snapshot($html));
        $proposals = array_values(array_filter($result['proposals'], fn ($p) => $p['field'] === $field));
        $this->assertCount($count, $proposals);
        if ($value !== null) {
            $this->assertContains($value, array_map(fn ($p) => $p['value']['code'] ?? $p['value']['value_text'] ?? (string) $p['value']['value_number'], $proposals));
        }
    }

    public static function textCases(): array
    {
        return [
            'explicit English' => ['<p>Manuscripts must be submitted in English.</p>', 'language', 1, 'en'],
            'two languages' => ['<p>Articles are accepted in French and English.</p>', 'language', 2, 'fr'],
            'site locale' => ['<html lang="fr"><body><p>Welcome</p></body></html>', 'language', 0],
            'unknown language' => ['<p>Accepted manuscript languages: Unknown</p>', 'language', 0],
            'article list' => ['<p>Article types: Original Research, Review Article, Short Communication, Case Report, Editorial</p>', 'article_type', 5, 'case_report'],
            'custom article' => ['<p>Article types: Custom Insight</p>', 'article_type', 0],
            'duplicates' => ['<p>Article types: Original Research, Original Research</p>', 'article_type', 1],
            'manuscript limit' => ['<p>Manuscript should not exceed 4,000 words.</p>', 'requirement', 1, '4000'],
            'abstract limit' => ['<p>Abstract: maximum 250 words</p>', 'requirement', 1, '250'],
            'article limit' => ['<p>Original Articles: maximum 5,000 words</p>', 'requirement', 1, '5000'],
            'ambiguous number' => ['<p>Many articles have 5000 words.</p>', 'requirement', 0],
            'ambiguous minimum' => ['<p>Manuscript: minimum 4000 words</p>', 'requirement', 0],
            'Vancouver' => ['<p>Reference style: Vancouver</p>', 'requirement', 1, 'Vancouver'],
            'APA' => ['<p>Reference style: APA 7th edition</p>', 'requirement', 1, 'APA 7th edition'],
            'AMA' => ['<p>Citation style: AMA</p>', 'requirement', 1, 'AMA'],
            'citation sample' => ['<p>(Author, 2020), pp. 1-3.</p>', 'requirement', 0],
            'custom style' => ['<p>Reference style: Synthetic House Style</p>', 'requirement', 0],
            'single blind unsupported' => ['<p>Peer review: single-blind</p>', 'requirement', 0],
            'double blind unsupported' => ['<p>Peer review: double-blind</p>', 'requirement', 0],
            'vague review' => ['<p>Reviewers will evaluate manuscripts.</p>', 'requirement', 0],
        ];
    }

    #[DataProvider('linkCases')]
    public function test_links_are_only_metadata(string $href, string $label, string $field, ?string $expected): void
    {
        $result = $this->extract($this->snapshot('<a href="'.htmlspecialchars($href, ENT_QUOTES).'">'.$label.'</a>'));
        $this->assertCount($expected === null ? 0 : 1, $result['proposals']);
        if ($expected !== null) {
            $proposal = $result['proposals'][0];
            $this->assertSame($field, $proposal['field']);
            $this->assertSame($expected, $proposal['value']['official_url'] ?? $proposal['value']['text_value']);
            $claim = JournalAssertion::findOrFail($proposal['assertion_id']);
            $this->assertSame($href, $claim->extraction_metadata['href']);
            $this->assertSame($label, $claim->extraction_metadata['anchor']);
        }
    }

    public static function linkCases(): array
    {
        return [
            'relative template' => ['templates/article.docx', 'Article template (DOCX)', 'template', 'https://journal.example.org/templates/article.docx'],
            'absolute template' => ['https://journal.example.org/template.tex', 'Download manuscript template', 'template', 'https://journal.example.org/template.tex'],
            'extension alone' => ['/file.docx', 'Download document', 'template', null],
            'unapproved template origin' => ['https://untrusted.example.org/template.docx', 'Manuscript template', 'template', null],
            'unsafe scheme' => ['javascript:alert(1)', 'Manuscript template', 'template', null],
            'local file' => ['file:///etc/passwd', 'Manuscript template', 'template', null],
            'credentials' => ['https://user:pass@journal.example.org/template', 'Manuscript template', 'template', null],
            'submission external' => ['https://submit.example.org/start?journal=synthetic#new', 'Submit your manuscript', 'submission_url', 'https://submit.example.org/start?journal=synthetic#new'],
            'submission relative' => ['/submit', 'Online submission', 'submission_url', 'https://journal.example.org/submit'],
            'unrelated login' => ['https://submit.example.org/login', 'Login', 'submission_url', null],
        ];
    }

    public function test_fixtures_scope_and_provenance(): void
    {
        foreach (['fees' => 2, 'no-apc' => 1, 'languages' => 2, 'article-types' => 2, 'word-limits' => 4,
            'reference-style' => 1, 'templates' => 1, 'submission' => 1, 'peer-review' => 0, 'conflicts' => 4, 'malformed' => 2] as $fixture => $count) {
            $record = $this->fixture($fixture);
            $result = $this->extract($record);
            $this->assertCount($count, $result['proposals'], $fixture);
            foreach ($result['proposals'] as $proposal) {
                $claim = JournalAssertion::findOrFail($proposal['assertion_id']);
                $this->assertSame($record->id, $claim->source_record_id);
                $this->assertSame($record->journalSource->official_journal_id, $claim->journal_id);
                $this->assertNotEmpty($claim->source_locator);
                $this->assertNotEmpty($claim->original_wording);
                $this->assertLessThanOrEqual(1024, strlen($claim->original_wording));
                $this->assertSame('editorial-v5', $claim->extraction_metadata['version']);
                $this->assertSame('deterministic_explicit', $claim->extraction_metadata['status']);
                $this->assertArrayNotHasKey('snippet', $proposal);
            }
        }
        $limits = JournalRequirement::where('requirement_type', 'word_count')->get();
        $this->assertTrue($limits->contains(fn ($limit) => $limit->value_number === 5000 && $limit->assertion->article_type_id !== null));
        $this->assertSame(250, JournalRequirement::where('requirement_type', 'abstract_words')->sole()->value_number);
        $this->assertSame(0, CatalogRevision::count());
    }

    public function test_dry_run_has_zero_writes_and_no_body_output(): void
    {
        $record = $this->fixture('fees');
        $before = $this->counts();
        $this->artisan('journals:sources', ['action' => 'extract', '--record' => $record->id, '--dry-run' => true])->assertExitCode(0);
        $this->assertSame($before, $this->counts());
        $preview = $this->extract($record, true);
        $this->assertCount(2, $preview['proposals']);
        $this->assertStringNotContainsString('Synthetic editorial fixture', json_encode($preview));
        $this->assertStringNotContainsString('Article Processing Charge:', json_encode($preview));
    }

    public function test_idempotency_snapshot_reuse_new_evidence_and_version_history(): void
    {
        $record = $this->snapshot('<p>APC: USD 1000</p>');
        $first = $this->extract($record);
        $again = $this->extract($this->snapshot($record->raw_content, $record));
        $this->assertSame($record->id, $again['snapshot_id']);
        $this->assertTrue($again['proposals'][0]['reused']);
        $this->assertSame($first['proposals'][0]['assertion_id'], $again['proposals'][0]['assertion_id']);
        $this->assertSame(1, JournalAssertion::count());
        $new = $this->snapshot('<p>APC: USD 1200</p>', $record);
        $changed = $this->extract($new);
        $this->assertSame([$first['proposals'][0]['assertion_id']], $changed['proposals'][0]['conflict_ids']);
        $this->assertSame('conflicting', $changed['proposals'][0]['review_status']);
        $this->assertSame('1000.0000', JournalFee::where('assertion_id', $first['proposals'][0]['assertion_id'])->sole()->amount);
        $v2 = new ExtractEditorialSnapshot(new PrepareDocument, new RuleRegistry('editorial-v6'), app(DetectJournalConflicts::class));
        $v2->extract($record);
        $this->assertSame(3, JournalAssertion::count());
        $v2->extract($record);
        $this->assertSame(3, JournalAssertion::count());
        $this->assertSame('editorial-v5', JournalAssertion::findOrFail($first['proposals'][0]['assertion_id'])->extraction_metadata['version']);
    }

    public function test_unchanged_fact_on_new_snapshot_preserves_both_observations_without_publication(): void
    {
        $first = $this->snapshot('<p>APC: USD 1000</p>');
        $a = $this->extract($first)['proposals'][0];
        $b = $this->extract($this->snapshot('<p>APC: USD 1000</p><p>Updated page footer</p>', $first))['proposals'][0];
        $this->assertNotSame($a['assertion_id'], $b['assertion_id']);
        $this->assertSame([], $b['conflict_ids']);
        $this->assertSame(2, JournalAssertion::count());
        $this->assertSame(0, CatalogRevision::count());
    }

    public function test_wrong_journal_and_inactive_source_fail_without_writes(): void
    {
        $record = $this->fixture('fees');
        $before = $this->counts();
        try {
            app(ExtractEditorialSnapshot::class)->extract($record, false, Journal::factory()->create()->id);
            $this->fail('Cross-journal extraction accepted.');
        } catch (DomainException $exception) {
            $this->assertSame('editorial_invalid_source', $exception->getMessage());
        }
        $record->journalSource->forceFill(['approval_status' => 'inactive'])->save();
        $this->artisan('journals:sources', ['action' => 'extract', '--record' => $record->id])->assertExitCode(1);
        $this->assertSame(0, JournalAssertion::count());
        $this->assertSame($before['source_records'], SourceRecord::count());
    }

    public function test_unsaved_aggregator_and_mutated_models_are_not_input(): void
    {
        foreach ([new SourceRecord, SourceRecord::factory()->create()] as $record) {
            try {
                $this->extract($record);
                $this->fail('Invalid snapshot accepted.');
            } catch (DomainException $exception) {
                $this->assertContains($exception->getMessage(), ['editorial_snapshot_required', 'editorial_invalid_source']);
            }
        }
        $record = $this->fixture('fees');
        $record->raw_content = '<p>APC: USD 9999</p>';
        $this->extract($record);
        $this->assertSame('1500.2500', JournalFee::where('fee_type', 'apc')->sole()->amount);
    }

    #[DataProvider('invalidDocuments')]
    public function test_invalid_document_preparation(string $body, string $mime, string $code): void
    {
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage($code);
        (new PrepareDocument)->prepare($body, $mime, self::URL);
    }

    public static function invalidDocuments(): array
    {
        return [['<p>APC: USD 1000</p>', 'application/pdf', 'editorial_unsupported_mime'],
            ["<p>bad\xff</p>", 'text/html', 'editorial_invalid_text'], ["<p>bad\0</p>", 'text/html', 'editorial_invalid_text'],
            [str_repeat('x', 262145), 'text/html', 'editorial_invalid_text'],
            [str_repeat('<p>APC: USD 1000</p>', 513), 'text/html', 'editorial_document_limit']];
    }

    public function test_scripts_styles_hidden_boilerplate_entities_and_base_are_inert(): void
    {
        $calls = 0;
        libxml_set_external_entity_loader(function () use (&$calls) {
            $calls++;

            return null;
        });
        try {
            $body = '<!DOCTYPE html SYSTEM "https://untrusted.example.org/external.dtd" [<!ENTITY remote SYSTEM "file:///etc/passwd">]>'
                .'<html><head><base href="https://untrusted.example.org/"><style>APC: USD 900</style></head><body>'
                .'<script>APC: USD 999</script><noscript>APC: USD 888</noscript><nav><p>APC: USD 777</p></nav>'
                .'<p hidden>APC: USD 666</p><p style="display:none">APC: USD 555</p><p aria-hidden="true">APC: USD 444</p>'
                .'<p>APC: USD 1000</p><a href="template.docx">Manuscript template</a><img src="https://untrusted.example.org/image"><p>&remote;</p></body></html>';
            $result = $this->extract($this->snapshot($body));
            $this->assertCount(2, $result['proposals']);
            $this->assertSame('1000.0000', JournalFee::sole()->amount);
            $this->assertSame('https://journal.example.org/template.docx', $result['proposals'][1]['value']['official_url']);
            $this->assertSame(0, $calls);
        } finally {
            libxml_set_external_entity_loader(null);
        }
    }

    public function test_stable_locator_resolves_in_original_dom_before_filtering(): void
    {
        $record = $this->snapshot('<p hidden>APC: USD 999</p><p>APC: USD 1000</p>');
        $this->extract($record);
        $claim = JournalAssertion::sole();
        $dom = new DOMDocument;
        $dom->loadHTML($record->raw_content);
        $this->assertSame('APC: USD 1000', (new DOMXPath($dom))->query($claim->source_locator)->item(0)->textContent);
    }

    public function test_huge_block_is_skipped_without_truncating_qualifiers_or_regex_blowup(): void
    {
        $record = $this->snapshot('<p>APC: USD 1000 '.str_repeat('a', 200000).' conditional waiver</p><p>Reference style: Vancouver</p>');
        $start = microtime(true);
        $result = $this->extract($record);
        $this->assertCount(1, $result['proposals']);
        $this->assertSame('requirement', $result['proposals'][0]['field']);
        $this->assertLessThan(5, microtime(true) - $start);
    }

    public function test_plain_robots_yields_zero_facts(): void
    {
        $record = $this->snapshot("User-agent: *\nCrawl-delay: 30\nDisallow: /submission\n", null, 'text/plain');
        $this->assertSame([], $this->extract($record)['proposals']);
    }

    public function test_multiple_limits_and_styles_are_compared_and_conflicts_retained(): void
    {
        $record = $this->fixture('word-limits');
        $first = $this->extract($record);
        $second = $this->extract($this->snapshot('<p>Original Articles: maximum 6000 words</p><p>Reference style: Vancouver</p>', $record));
        $this->assertCount(1, $second['proposals'][0]['conflict_ids']);
        $this->assertSame('conflicting', $second['proposals'][0]['review_status']);
        $third = $this->extract($this->snapshot('<p>Reference style: APA</p>', $record));
        $this->assertSame('conflicting', $third['proposals'][0]['review_status']);
        $this->assertSame(4, count($first['proposals']));
        $this->assertSame(7, JournalAssertion::count());
    }

    #[DataProvider('absenceOrder')]
    public function test_no_apc_conflicts_in_both_directions(string $a, string $b): void
    {
        $record = $this->snapshot('<p>'.$a.'</p>');
        $first = $this->extract($record)['proposals'][0];
        $second = $this->extract($this->snapshot('<p>'.$b.'</p>', $record))['proposals'][0];
        $this->assertSame([$first['assertion_id']], $second['conflict_ids']);
        $this->assertSame('conflicting', $second['review_status']);
    }

    public static function absenceOrder(): array
    {
        return [['No article processing charges', 'APC: USD 1000'], ['APC: USD 1000', 'There is no APC']];
    }

    public function test_review_and_existing_publication_boundary_and_sealed_history(): void
    {
        $record = $this->fixture('reference-style');
        $result = $this->extract($record);
        $claim = JournalAssertion::findOrFail($result['proposals'][0]['assertion_id']);
        $journal = Journal::findOrFail($claim->journal_id);
        $title = JournalCatalogFixture::assertion($journal, JournalField::Title, [], VerificationStatus::Verified);
        try {
            app(PublishJournalRevision::class)->publish($journal, [$title->id, $claim->id]);
            $this->fail('Pending extraction published.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('verified', $exception->getMessage());
        }
        $this->assertSame(0, CatalogRevision::count());
        app(ReviewIngestionAssertion::class)->review($claim, VerificationStatus::Verified, 'reviewer-001', 'manual_official', 'Synthetic evidence reviewed.');
        $catalog = app(PublishJournalRevision::class)->publish($journal, [$title->id, $claim->id]);
        $this->extract($record);
        $this->assertSame(1, CatalogRevision::count());
        $this->assertSame($catalog->id, CatalogRevision::sole()->id);
        $this->assertSame('verified', $claim->fresh()->verificationStatus()->value);
    }

    public function test_currency_free_absence_cannot_bypass_money_publication(): void
    {
        $record = $this->fixture('no-apc');
        $claim = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        app(ReviewIngestionAssertion::class)->review($claim, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Explicit absence fixture.');
        $journal = Journal::findOrFail($claim->journal_id);
        $title = JournalCatalogFixture::assertion($journal, JournalField::Title, [], VerificationStatus::Verified);
        $this->expectException(DomainException::class);
        app(PublishJournalRevision::class)->publish($journal, [$title->id, $claim->id]);
    }

    public function test_atomic_partial_failure_then_retry(): void
    {
        $record = $this->fixture('fees');
        $before = $this->counts();
        DB::unprepared("CREATE FUNCTION editorial_test_fail() RETURNS trigger LANGUAGE plpgsql AS $$ BEGIN IF NEW.fee_type = 'submission' THEN RAISE EXCEPTION 'synthetic failure' USING ERRCODE = '23514'; END IF; RETURN NEW; END $$; CREATE TRIGGER editorial_test_fail BEFORE INSERT ON journal_fees FOR EACH ROW EXECUTE FUNCTION editorial_test_fail();");
        try {
            try {
                $this->extract($record);
                $this->fail('Expected injected PostgreSQL failure.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
            $this->assertSame($before, $this->counts());
        } finally {
            DB::unprepared('DROP TRIGGER editorial_test_fail ON journal_fees; DROP FUNCTION editorial_test_fail();');
        }
        $this->assertCount(2, $this->extract($record)['proposals']);
        $this->assertSame(2, JournalFee::count());
    }

    public function test_postgresql_metadata_uniqueness_immutability_and_attribution(): void
    {
        $record = $this->fixture('fees');
        $claim = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        foreach (['update', 'delete', 'duplicate', 'foreign_journal', 'missing_rule'] as $operation) {
            try {
                DB::transaction(function () use ($claim, $operation): void {
                    if ($operation === 'update') {
                        DB::table('journal_assertions')->where('id', $claim->id)->update(['extraction_metadata' => '{}']);
                    } elseif ($operation === 'delete') {
                        $claim->delete();
                    } else {
                        $attributes = $claim->getAttributes();
                        unset($attributes['id']);
                        if ($operation === 'foreign_journal') {
                            $attributes['journal_id'] = Journal::factory()->create()->id;
                        }
                        if ($operation === 'missing_rule') {
                            $attributes['extraction_metadata'] = '{}';
                        }
                        $copy = new JournalAssertion;
                        $copy->setRawAttributes($attributes);
                        $copy->save();
                    }
                });
                $this->fail('Unsafe operation accepted: '.$operation);
            } catch (QueryException $exception) {
                $this->assertContains($exception->errorInfo[0], ['23514', '23505']);
            }
        }
        $this->assertSame('editorial-v5', $claim->fresh()->extraction_metadata['version']);
    }

    public function test_cli_rejects_missing_record_mismatch_and_fetch_options(): void
    {
        $record = $this->fixture('fees');
        $this->artisan('journals:sources', ['action' => 'extract'])->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'extract', '--record' => $record->id, '--url' => self::URL])->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'extract', '--record' => $record->id, '--source' => '00000000-0000-0000-0000-000000000001'])->assertExitCode(1);
        $this->artisan('journals:sources', ['action' => 'extract', '--record' => $record->id])->assertExitCode(0);
        Http::assertNothingSent();
    }

    public function test_empty_rollback_and_populated_refusal_preserve_metadata(): void
    {
        $migration = require database_path('migrations/2026_10_12_110000_add_editorial_extraction_metadata.php');
        DB::transaction(function () use ($migration): void {
            (require database_path('migrations/2026_10_13_140000_allow_editorial_v5_metadata.php'))->down();
            (require database_path('migrations/2026_10_13_130000_allow_editorial_v4_metadata.php'))->down();
            (require database_path('migrations/2026_10_13_120000_allow_editorial_v3_metadata.php'))->down();
            $migration->down();
            $migration->up();
            (require database_path('migrations/2026_10_13_120000_allow_editorial_v3_metadata.php'))->up();
            (require database_path('migrations/2026_10_13_130000_allow_editorial_v4_metadata.php'))->up();
            (require database_path('migrations/2026_10_13_140000_allow_editorial_v5_metadata.php'))->up();
        });
        $record = $this->fixture('fees');
        $this->extract($record);
        try {
            DB::transaction(fn () => $migration->down());
            $this->fail('Populated metadata dropped.');
        } catch (DomainException $exception) {
            $this->assertSame('editorial_rollback_preserves_evidence', $exception->getMessage());
        }
        $this->assertSame(2, JournalAssertion::whereNotNull('extraction_metadata')->count());
    }

    public function test_conditional_headings_and_hidden_inline_qualifiers_do_not_create_absence(): void
    {
        $record = $this->snapshot('<h2>Waivers for eligible authors</h2><p>There is no APC</p><h2>Promotional period</h2><p>No article processing charges</p>');
        $this->assertSame([], $this->extract($record)['proposals']);
    }

    public function test_exact_table_and_definition_list_units_are_traceable(): void
    {
        $record = $this->snapshot('<table><tr><th>APC</th><td>USD 1000</td></tr></table><dl><dt>Reference style</dt><dd>Vancouver</dd></dl>');
        $result = $this->extract($record);
        $this->assertCount(2, $result['proposals']);
        $this->assertStringContainsString('/tr', $result['proposals'][0]['locator']);
        $this->assertStringContainsString('/dd', $result['proposals'][1]['locator']);
        $this->assertSame('APC: USD 1000', JournalAssertion::findOrFail($result['proposals'][0]['assertion_id'])->original_wording);
    }

    public function test_revocation_after_parsing_stops_atomic_persistence(): void
    {
        $record = $this->fixture('fees');
        $preparer = $this->mock(PrepareDocument::class);
        $preparer->shouldReceive('prepare')->once()->andReturnUsing(function ($body, $mime, $url) use ($record) {
            $record->journalSource->forceFill(['approval_status' => 'inactive'])->save();

            return (new PrepareDocument)->prepare($body, $mime, $url);
        });
        try {
            $this->extract($record);
            $this->fail('Revoked source created proposals.');
        } catch (DomainException $exception) {
            $this->assertSame('editorial_invalid_source', $exception->getMessage());
        }
        $this->assertSame(0, JournalAssertion::count());
        $this->assertSame(0, JournalFee::count());
        $this->assertSame(1, SourceRecord::count());
    }

    public function test_forged_extraction_metadata_on_loaded_model_does_not_authorize_review(): void
    {
        $journal = Journal::factory()->create();
        $claim = JournalCatalogFixture::assertion($journal, JournalField::Title);
        $claim->extraction_metadata = ['version' => 'editorial-v1'];
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('not_an_ingestion_assertion');
        app(ReviewIngestionAssertion::class)->review($claim, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Spoofed metadata must not authorize review.');
    }

    public function test_distinct_official_sources_keep_fee_conflicts_and_older_reviews(): void
    {
        $first = $this->snapshot('<p>APC: USD 1000</p>');
        $a = JournalAssertion::findOrFail($this->extract($first)['proposals'][0]['assertion_id']);
        app(ReviewIngestionAssertion::class)->review($a, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Synthetic first fee reviewed.');
        $source = app(OfficialSources::class)->register(Journal::findOrFail($a->journal_id), 'official_publisher', 'fees', 'https://publisher.example.org/fees');
        $source->forceFill(['approval_status' => 'approved', 'approved_by' => 'fixture-reviewer', 'approval_basis' => 'Synthetic only', 'approved_at' => now()])->save();
        $body = '<p>APC: USD 1200</p>';
        $url = $source->official_url;
        $record = SourceRecord::create(['journal_source_id' => $source->id, 'external_id' => hash('sha256', $url),
            'revision_fingerprint' => hash('sha256', $url."\ntext/html\n".$body), 'source_url' => $url, 'retrieved_at' => now(),
            'status' => 'captured', 'raw_content' => $body, 'content_hash' => hash('sha256', $body),
            'snapshot_metadata' => ['requested_url' => $url, 'final_url' => $url, 'http_status' => 200, 'content_type' => 'text/html',
                'bytes' => strlen($body), 'parser_version' => 'official-page-raw-v1', 'etag' => null, 'last_modified' => null]]);
        $b = $this->extract($record)['proposals'][0];
        $this->assertSame([$a->id], $b['conflict_ids']);
        $this->assertSame('conflicting', $b['review_status']);
        $this->assertSame('verified', $a->fresh()->verificationStatus()->value);
        $this->assertSame(2, SourceRecord::count());
    }

    #[DataProvider('semanticQualifierCases')]
    public function test_semantic_qualifier_abstention(string $html): void
    {
        $record = $this->snapshot($html);
        $before = $this->counts();
        $this->assertSame([], $this->extract($record)['proposals']);
        $this->assertSame($before, $this->counts());
    }

    public static function semanticQualifierCases(): array
    {
        return [
            'parent waiver heading' => ['<section><h2>Waivers for invited authors</h2><p>There is no APC</p></section>'],
            'wrapper waiver heading' => ['<section><h2>Waivers for invited authors</h2><div><p>There is no APC</p></div></section>'],
            'preceding qualifier' => ['<p>For invited authors only:</p><p>No article processing charges</p>'],
            'preceding qualifier through wrapper' => ['<p>For invited authors only:</p><div><p>There is no APC</p></div>'],
            'examples language' => ['<section><h2>Examples (not journal policy)</h2><div><p>Manuscripts must be submitted in French.</p></div></section>'],
            'conditional fee' => ['<section><h2>Promotional free period</h2><div><p>APC: USD 0</p></div></section>'],
            'unknown article scope' => ['<section><h2>Special invited contributions</h2><div><p>Abstract: maximum 250 words</p></div></section>'],
            'ambiguous nested article scopes' => ['<section><h2>Original Articles</h2><div><h3>Review Articles</h3><p>Abstract: maximum 250 words</p></div></section>'],
        ];
    }

    public function test_semantic_plain_text_qualifiers_abstain_and_standalone_absence_works(): void
    {
        $conditional = $this->snapshot("For invited authors only:\nThere is no APC\n", null, 'text/plain');
        $this->assertSame([], $this->extract($conditional)['proposals']);
        $unqualified = $this->snapshot('<h2>Fees</h2><div><p>There is no APC</p></div>');
        $result = $this->extract($unqualified);
        $this->assertCount(1, $result['proposals']);
        $this->assertSame('official_reported_no_apc', $result['proposals'][0]['value']['basis']);
    }

    #[DataProvider('deletedCases')]
    public function test_semantic_deleted_content_is_not_evidence(string $deleted, string $live, string $field, string $tag): void
    {
        $record = $this->snapshot('<p><'.$tag.'>'.$deleted.'</'.$tag.'></p><p>'.$live.'</p>');
        $result = $this->extract($record);
        $this->assertCount(1, $result['proposals']);
        $this->assertSame($field, $result['proposals'][0]['field']);
        $claim = JournalAssertion::findOrFail($result['proposals'][0]['assertion_id']);
        $this->assertSame($live, $claim->original_wording);
        $this->assertStringContainsString('<'.$tag.'>'.$deleted.'</'.$tag.'>', $record->fresh()->raw_content);
    }

    public static function deletedCases(): array
    {
        $cases = [];
        foreach (['del', 's', 'strike'] as $tag) {
            foreach ([['APC: USD 1500', 'APC: USD 1200', 'fee'],
                ['Manuscripts must be submitted in French.', 'Manuscripts must be submitted in English.', 'language'],
                ['Abstract: maximum 250 words', 'Abstract: maximum 300 words', 'requirement']] as $case) {
                $cases[] = [...$case, $tag];
            }
        }

        return $cases;
    }

    #[DataProvider('operatorCases')]
    public function test_semantic_word_operator_is_explicit(string $html, ?int $limit): void
    {
        $result = $this->extract($this->snapshot($html));
        $this->assertCount($limit === null ? 0 : 1, $result['proposals']);
        if ($limit !== null) {
            $this->assertSame('max', $result['proposals'][0]['value']['operator']);
            $this->assertSame($limit, $result['proposals'][0]['value']['value_number']);
        }
    }

    public static function operatorCases(): array
    {
        return [
            'minimum caption' => ['<table><caption>Minimum word counts</caption><tr><th>Manuscript</th><td>3000 words</td></tr></table>', null],
            'minimum heading' => ['<h2>Minimum word counts</h2><div><p>Manuscript: 3000 words</p></div>', null],
            'exact caption' => ['<table><caption>Exact word counts</caption><tr><th>Manuscript</th><td>3000 words</td></tr></table>', null],
            'minimum direct' => ['<p>Manuscript: minimum 3000 words</p>', null],
            'at least' => ['<p>Manuscript: at least 3000 words</p>', null],
            'exact direct' => ['<p>Manuscript: exactly 3000 words</p>', null],
            'approximate' => ['<p>Manuscript: approximately 3000 words</p>', null],
            'range' => ['<p>Manuscript: between 3000 and 5000 words</p>', null],
            'bare direct' => ['<p>Original Articles: 5000 words</p>', null],
            'maximum' => ['<p>Manuscript: maximum 5000 words</p>', 5000],
            'up to' => ['<p>Manuscript: up to 5000 words</p>', 5000],
            'no more than' => ['<p>Manuscript: no more than 5000 words</p>', 5000],
            'not exceed' => ['<p>Abstract should not exceed 250 words</p>', 250],
            'maximum caption' => ['<table><caption>Maximum word counts</caption><tr><th>Manuscript</th><td>5000 words</td></tr></table>', 5000],
            'maximum column header' => ['<table><tr><th>Article type</th><th>Maximum word count</th></tr><tr><td>Original Articles</td><td>5000 words</td></tr></table>', 5000],
        ];
    }

    public function test_semantic_article_scopes_and_sections_do_not_collide(): void
    {
        $body = '<section><h2>Original Articles</h2><div><p>Abstract: maximum 250 words</p><p>Manuscript: maximum 5000 words</p></div></section>'
            .'<section><h2>Review Articles</h2><div><p>Abstract: maximum 250 words</p><p>Manuscript: maximum 6000 words</p></div></section>';
        $record = $this->snapshot($body);
        $result = $this->extract($record);
        $this->assertCount(4, $result['proposals']);
        $keys = [];
        foreach ($result['proposals'] as $proposal) {
            $this->assertSame([], $proposal['conflict_ids']);
            $claim = JournalAssertion::findOrFail($proposal['assertion_id']);
            $this->assertNotNull($claim->article_type_id);
            $this->assertContains($claim->articleType->code, ['research_article', 'review_article']);
            $this->assertNotEmpty($claim->extraction_metadata['contexts']);
            $this->assertSame('editorial-dom-v5', $claim->extraction_metadata['document_version']);
            $keys[] = $claim->selectionKey();
        }
        $this->assertCount(4, array_unique($keys));
        $changed = $this->extract($this->snapshot('<section><h2>Review Articles</h2><div><p>Abstract: maximum 300 words</p></div></section>', $record));
        $this->assertCount(1, $changed['proposals'][0]['conflict_ids']);
        $competing = JournalAssertion::findOrFail($changed['proposals'][0]['conflict_ids'][0]);
        $this->assertSame('review_article', $competing->articleType->code);
    }

    public function test_semantic_explicit_no_apc_blocks_uncontested_price_until_resolution(): void
    {
        $record = $this->snapshot('<p>APC: USD 1500</p>');
        $price = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        $journal = Journal::findOrFail($price->journal_id);
        $title = JournalCatalogFixture::assertion($journal, JournalField::Title, [], VerificationStatus::Verified);
        app(ReviewIngestionAssertion::class)->review($price, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Synthetic price reviewed.');
        $catalog = app(PublishJournalRevision::class)->publish($journal, [$title->id, $price->id]);
        $revision = $journal->currentRevision;
        $this->assertSame(2, $revision->usableSelections()->count());
        $negative = JournalAssertion::findOrFail($this->extract($this->snapshot('<p>There is no APC</p>', $record))['proposals'][0]['assertion_id']);
        $this->assertTrue($negative->isExplicitNoApc());
        $this->assertSame('conflicting', $negative->verificationStatus()->value);
        $this->assertSame(1, $revision->usableSelections()->count());
        try {
            app(PublishJournalRevision::class)->publish($journal, [$title->id, $price->id]);
            $this->fail('Unresolved explicit absence was ignored.');
        } catch (DomainException $exception) {
            $this->assertStringContainsString('Competing', $exception->getMessage());
        }
        $this->assertSame(1, CatalogRevision::count());
        $this->assertSame($catalog->id, CatalogRevision::sole()->id);
        app(ReviewIngestionAssertion::class)->review($negative, VerificationStatus::Rejected, 'reviewer-001', 'manual', 'Synthetic conflict resolved explicitly.');
        $this->assertSame(2, $revision->usableSelections()->count());
        app(PublishJournalRevision::class)->publish($journal, [$title->id, $price->id]);
        $this->assertSame(2, CatalogRevision::count());
    }

    public function test_semantic_verified_absence_conflicts_with_price_and_does_not_auto_win(): void
    {
        $record = $this->snapshot('<p>There is no APC</p>');
        $negative = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        app(ReviewIngestionAssertion::class)->review($negative, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Synthetic explicit absence reviewed.');
        $new = $this->extract($this->snapshot('<p>APC: USD 1500</p>', $record))['proposals'][0];
        $this->assertSame([$negative->id], $new['conflict_ids']);
        $this->assertSame('conflicting', $new['review_status']);
        $price = JournalAssertion::findOrFail($new['assertion_id']);
        app(ReviewIngestionAssertion::class)->review($price, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Price cannot silently erase contrary absence.');
        $journal = Journal::findOrFail($price->journal_id);
        $title = JournalCatalogFixture::assertion($journal, JournalField::Title, [], VerificationStatus::Verified);
        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Competing');
        app(PublishJournalRevision::class)->publish($journal, [$title->id, $price->id]);
    }

    public function test_semantic_unknown_and_waiver_do_not_block_verified_apc(): void
    {
        $record = $this->snapshot('<p>APC: USD 1500</p>');
        $price = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        $journal = Journal::findOrFail($price->journal_id);
        $title = JournalCatalogFixture::assertion($journal, JournalField::Title, [], VerificationStatus::Verified);
        app(ReviewIngestionAssertion::class)->review($price, VerificationStatus::Verified, 'reviewer-001', 'manual', 'Synthetic price reviewed.');
        app(PublishJournalRevision::class)->publish($journal, [$title->id, $price->id]);
        $revision = $journal->currentRevision;
        $this->assertSame([], $this->extract($this->snapshot('<p>No APC information available</p><p>APC waivers are available for invited authors</p>', $record))['proposals']);
        $unknown = JournalAssertion::create(['journal_id' => $journal->id, 'source_record_id' => $record->id, 'field' => 'fee', 'availability' => 'unknown']);
        JournalFee::create(['journal_id' => $journal->id, 'assertion_id' => $unknown->id, 'fee_type' => 'apc', 'amount' => null, 'currency' => null,
            'basis' => 'not_stated', 'applicability' => 'No usable amount or absence claim.']);
        JournalCatalogFixture::review($unknown, VerificationStatus::Conflicting);
        $this->assertFalse($unknown->hasConcreteClaim());
        $this->assertSame(2, $revision->usableSelections()->count());
        app(PublishJournalRevision::class)->publish($journal, [$title->id, $price->id]);
        $this->assertSame(2, CatalogRevision::count());
    }

    #[DataProvider('semanticFragmentCases')]
    public function test_semantic_fragment_only_links_are_ignored(string $label, string $fragment): void
    {
        $record = $this->snapshot('<a href="'.$fragment.'">'.$label.'</a>');
        $this->assertSame([], $this->extract($record)['proposals']);
    }

    public static function semanticFragmentCases(): array
    {
        return [['Manuscript template', '#template'], ['Submit your manuscript', '#submit'], ['Online submission', '#section-4']];
    }

    public function test_semantic_normalized_amounts_agree_and_currencies_disagree(): void
    {
        $record = $this->snapshot('<p>APC: USD 1200</p>');
        $this->extract($record);
        $same = $this->extract($this->snapshot('<p>APC: USD 1,200</p>', $record))['proposals'][0];
        $this->assertSame([], $same['conflict_ids']);
        $different = $this->extract($this->snapshot('<p>APC: EUR 1200</p>', $record))['proposals'][0];
        $this->assertCount(2, $different['conflict_ids']);
        $this->assertSame('conflicting', $different['review_status']);
    }

    public function test_semantic_review_labels_are_not_collapsed(): void
    {
        $record = $this->snapshot('<p>Article types: Review, Systematic Review, Book Review, Review Article</p>');
        $result = $this->extract($record);
        $this->assertCount(1, $result['proposals']);
        $this->assertSame('review_article', $result['proposals'][0]['value']['code']);
        $this->assertStringContainsString('Systematic Review', JournalAssertion::sole()->original_wording);
    }

    #[DataProvider('structuralContextCases')]
    public function test_structural_context_preserves_scope_or_abstains(string $body, string $mime, array $articles): void
    {
        $record = $this->snapshot($body, null, $mime);
        $before = $this->counts();
        $result = $this->extract($record);
        $this->assertCount(count($articles), $result['proposals']);
        if ($articles === []) {
            $this->assertSame($before, $this->counts());
        }
        foreach ($result['proposals'] as $index => $proposal) {
            $claim = JournalAssertion::findOrFail($proposal['assertion_id']);
            $this->assertSame($articles[$index], $claim->articleType?->code);
            $this->assertSame('requirement', $claim->field->value);
            $this->assertSame('max', $claim->requirement->operator);
            $this->assertSame([], $proposal['conflict_ids']);
            $this->assertSame('pending', $claim->verificationStatus()->value);
            $this->assertNotEmpty($claim->extraction_metadata['contexts']);
        }
    }

    public static function structuralContextCases(): array
    {
        return [
            'HTML qualifier intertitle' => ['<section><p>For invited authors only:</p><h3>Fees</h3><p>There is no APC</p></section>', 'text/html', []],
            'HTML qualifier wrapper' => ['<section><p>For members only:</p><h3>Fees</h3><div><p>There is no APC</p></div></section>', 'text/html', []],
            'HTML article wrapper' => ['<h2>Original Articles</h2><div><h3>Word limits</h3><p>Abstract: maximum 250 words</p></div>', 'text/html', ['research_article']],
            'HTML equal heading wrappers' => ['<h2>Original Articles</h2><div>Earlier section</div><div><h2>Review Articles</h2><h3>Word limits</h3><p>Manuscript: maximum 4000 words</p></div>', 'text/html', ['review_article']],
            'HTML equal generic heading' => ['<h2>Original Articles</h2><div>Earlier section</div><div><h2>Word limits</h2><p>Manuscript: maximum 5000 words</p></div>', 'text/html', [null]],
            'HTML nested hierarchy' => ['<h2>Original Articles</h2><div><h3>Word limits</h3><div><h4>Abstract</h4><p>Abstract: maximum 250 words</p></div></div>', 'text/html', ['research_article']],
            'HTML section boundary' => ['<section><h2>Examples</h2><p>APC: USD 1500</p></section><section><h2>Word limits</h2><p>Abstract: maximum 250 words</p></section>', 'text/html', [null]],
            'HTML aside boundary' => ['<aside><h2>Examples</h2><p>APC: USD 1500</p></aside><main><h2>Word limits</h2><p>Abstract: maximum 250 words</p></main>', 'text/html', [null]],
            'HTML unrelated paragraph is not qualifier' => ['<p>General explanatory introduction.</p><h2>Word limits</h2><p>Abstract: maximum 250 words</p>', 'text/html', [null]],
            'HTML illustration' => ['<h2>Illustration</h2><h3>Word limits</h3><p>Abstract: maximum 250 words</p>', 'text/html', []],
            'plain qualifier intertitle' => ["For invited authors only:\nFees:\nThere is no APC", 'text/plain', []],
            'plain except condition' => ["Except for members:\nFees:\nThere is no APC", 'text/plain', []],
            'plain article chain' => ["Original Articles:\nWord limits:\nAbstract: maximum 250 words", 'text/plain', ['research_article']],
            'plain two articles' => ["Original Articles:\nWord limits:\nAbstract: maximum 250 words\n\nReview Articles:\nWord limits:\nAbstract: maximum 300 words", 'text/plain', ['research_article', 'review_article']],
            'plain prose boundary' => ["Original Articles:\n\nunrelated explanatory prose\n\nWord limits:\nAbstract: maximum 250 words", 'text/plain', [null]],
            'plain exhausted chain' => ["Original Articles:\nunrelated explanatory prose\nAbstract: maximum 250 words", 'text/plain', []],
            'plain unknown article' => ["Systematic Reviews:\nWord limits:\nAbstract: maximum 250 words", 'text/plain', []],
            'plain excessive chain' => [str_repeat("Word limits:\n", 9).'Abstract: maximum 250 words', 'text/plain', []],
            'plain oversized context' => [str_repeat('x', 129).":\nAbstract: maximum 250 words", 'text/plain', []],
            'plain incompatible articles' => ["Original Articles:\nReview Articles:\nWord limits:\nAbstract: maximum 250 words", 'text/plain', []],
        ];
    }

    #[DataProvider('deletedTags')]
    public function test_structural_deleted_markup_alone_creates_no_evidence(string $tag): void
    {
        $record = $this->snapshot('<'.$tag.'>APC: USD 1500</'.$tag.'>');
        $before = $this->counts();
        $this->assertSame([], $this->extract($record)['proposals']);
        $this->assertSame($before, $this->counts());
    }

    public static function deletedTags(): array
    {
        return [['del'], ['s'], ['strike']];
    }

    public function test_semantic_bounded_adversarial_unit_reaches_text_rule_regexes(): void
    {
        $prefix = 'APC: USD ';
        $text = $prefix.str_repeat('9', 1024 - strlen($prefix));
        $document = (new PrepareDocument)->prepare('<p>'.$text.'</p>', 'text/html', self::URL);
        $this->assertCount(1, $document->units);
        $this->assertSame(1024, strlen($document->units[0]->text));
        $start = microtime(true);
        foreach ((new RuleRegistry)->rules() as $rule) {
            $this->assertSame([], $rule->extract($document->units[0], $document));
        }
        $this->assertLessThan(2, microtime(true) - $start);
    }

    public function test_remediation_resource_caps_and_non_order_biased_collection(): void
    {
        $noise = str_repeat('<p>Background details.</p>', 700);
        $early = '<p>Manuscripts must be submitted in English.</p>';
        $late = '<p>Reference style: Vancouver</p>';
        $this->assertCount(2, $this->extract($this->snapshot($early.$noise.$late))['proposals']);
        foreach ([str_repeat('<i>x</i>', 4097),
            str_repeat('<p>APC: '.str_repeat('9', 1019).'</p>', 129),
            str_repeat('<a href="/new">Submit</a>', 129)] as $body) {
            try {
                (new PrepareDocument)->prepare($body, 'text/html', self::URL);
                $this->fail('Resource ceiling silently truncated evidence.');
            } catch (DomainException $exception) {
                $this->assertSame('editorial_document_limit', $exception->getMessage());
            }
        }
    }

    public function test_remediation_v2_v3_v4_history_and_v5_rollback_guard(): void
    {
        $record = $this->snapshot('<p>APC: USD 1500</p>');
        $claim = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        $metadata = $claim->extraction_metadata;
        $metadata['version'] = 'editorial-v2';
        $metadata['document_version'] = 'editorial-dom-v2';
        $metadata['proposal_key'] = hash('sha256', 'synthetic V2 history');
        $legacy = JournalAssertion::create(['journal_id' => $claim->journal_id, 'source_record_id' => $record->id,
            'field' => 'fee', 'availability' => 'known', 'source_locator' => $claim->source_locator,
            'original_wording' => $claim->original_wording, 'extraction_metadata' => $metadata]);
        JournalFee::create(['journal_id' => $claim->journal_id, 'assertion_id' => $legacy->id,
            'fee_type' => 'apc', 'amount' => '1500', 'currency' => 'USD', 'basis' => 'official_explicit_amount', 'applicability' => 'Synthetic V2 historical fee.']);
        $v3metadata = $metadata;
        $v3metadata['version'] = 'editorial-v3';
        $v3metadata['document_version'] = 'editorial-dom-v3';
        $v3metadata['proposal_key'] = hash('sha256', 'synthetic V3 history');
        $v3 = JournalAssertion::create(['journal_id' => $claim->journal_id, 'source_record_id' => $record->id,
            'field' => 'fee', 'availability' => 'known', 'source_locator' => $claim->source_locator,
            'original_wording' => $claim->original_wording, 'extraction_metadata' => $v3metadata]);
        JournalFee::create(['journal_id' => $claim->journal_id, 'assertion_id' => $v3->id,
            'fee_type' => 'apc', 'amount' => '1500', 'currency' => 'USD', 'basis' => 'official_explicit_amount', 'applicability' => 'Synthetic V3 historical fee.']);
        $this->extract($record);
        $v4metadata = $v3metadata;
        $v4metadata['version'] = 'editorial-v4';
        $v4metadata['document_version'] = 'editorial-dom-v4';
        $v4metadata['proposal_key'] = hash('sha256', 'synthetic V4 history');
        $v4 = JournalAssertion::create(['journal_id' => $claim->journal_id, 'source_record_id' => $record->id,
            'field' => 'fee', 'availability' => 'known', 'source_locator' => $claim->source_locator,
            'original_wording' => $claim->original_wording, 'extraction_metadata' => $v4metadata]);
        JournalFee::create(['journal_id' => $claim->journal_id, 'assertion_id' => $v4->id,
            'fee_type' => 'apc', 'amount' => '1500', 'currency' => 'USD', 'basis' => 'official_explicit_amount', 'applicability' => 'Synthetic V4 history.']);
        $this->extract($record);
        $this->assertSame($v4metadata, $v4->fresh()->extraction_metadata);
        $this->assertSame($v3metadata, $v3->fresh()->extraction_metadata);
        $this->assertSame($metadata, $legacy->fresh()->extraction_metadata);
        $this->assertSame(4, JournalAssertion::count());
        try {
            DB::transaction(fn () => (require database_path('migrations/2026_10_13_140000_allow_editorial_v5_metadata.php'))->down());
            $this->fail('V5 rollback erased retained provenance compatibility.');
        } catch (DomainException $exception) {
            $this->assertSame('editorial_rollback_preserves_evidence', $exception->getMessage());
        }
        $this->assertSame(4, JournalAssertion::count());
    }

    #[DataProvider('remediationCases')]
    public function test_remediation_general_evidence(string $html, string $field, int $count): void
    {
        $result = $this->extract($this->snapshot($html));
        $this->assertCount($count, $result['proposals']);
        foreach ($result['proposals'] as $proposal) {
            $this->assertSame($field, $proposal['field']);
            $claim = JournalAssertion::findOrFail($proposal['assertion_id']);
            $this->assertSame('editorial-v5', $claim->extraction_metadata['version']);
            $this->assertSame('editorial-dom-v5', $claim->extraction_metadata['document_version']);
            $this->assertSame('pending', $claim->verificationStatus()->value);
        }
    }

    public static function remediationCases(): array
    {
        $noise = str_repeat('<p>General explanatory background.</p>', 600);

        return [
            'late language' => [$noise.'<p>Manuscripts must be submitted in English.</p>', 'language', 1],
            'late style' => [$noise.'<h2>Reference style</h2><p>Example Journal uses Vancouver style.</p>', 'requirement', 1],
            'quoted style prose' => ['<h2>Reference style</h2><p>Example Journal uses “Vancouver” style, as outlined in the ICMJE sample references.</p>', 'requirement', 1],
            'conditional prose style' => ['<p>Only invited authors uses “Vancouver” style.</p>', 'requirement', 0],
            'conditional quoted style' => ['<h2>For invited authors only</h2><p>Example Journal uses “Vancouver” style.</p>', 'requirement', 0],
            'late conditional fee' => [$noise.'<p>For invited authors only:</p><h3>Fees</h3><p>No APC</p>', 'fee', 0],
            'zero publication prose' => ['<h2>Cost and Sustainability Model</h2><p>Example Journal is an open access journal committed to running at minimal costs, with zero publication fees (article processing charges) or subscription fees.</p>', 'fee', 1],
            'no APC' => ['<p>No APC</p>', 'fee', 1],
            'authors pay no fee' => ['<p>Authors pay no publication fee.</p>', 'fee', 1],
            'invited zero' => ['<p>Zero APC for invited authors</p>', 'fee', 0],
            'promotion' => ['<p>No fee during promotional period</p>', 'fee', 0],
            'waiver' => ['<p>APC waived for eligible authors</p>', 'fee', 0],
            'some authors' => ['<p>Some authors pay no APC</p>', 'fee', 0],
            'additional' => ['<p>No additional APC beyond the standard fee</p>', 'fee', 0],
            'unknown' => ['<p>No APC information is available</p>', 'fee', 0],
            'nav submit' => ['<header><nav><a href="/papers/new">Submit</a><p>APC: USD 999</p><p>Manuscripts must be submitted in French.</p></nav></header>', 'submission_url', 1],
            'nav external system' => ['<nav><a href="https://submission.example.org/new">Submission system</a><a href="/about">About</a></nav>', 'submission_url', 1],
            'nav conditional' => ['<nav><p>For invited authors only:</p><a href="/new">Submit</a></nav>', 'submission_url', 0],
            'nav example' => ['<nav><h2>Examples</h2><a href="/new">Submit</a></nav>', 'submission_url', 0],
            'fragment action' => ['<nav><a href="#submit">Submit</a></nav>', 'submission_url', 0],
            'unsafe action' => ['<nav><a href="javascript:alert(1)">Submit</a></nav>', 'submission_url', 0],
            'abstract list maximum' => ['<h2>Parts of a Submission</h2><h3>Abstract</h3><div><p>The Abstract should:</p><ul><li>Not exceed 300 words</li></ul></div>', 'requirement', 1],
            'domain restriction stays unsupported' => ['<h2>Aims and scope</h2><p>Article types: Research Article and Review Article in the subject areas below.</p>', 'article_type', 0],
        ];
    }

    public function test_semantic_legacy_metadata_and_context_constraints_preserve_history(): void
    {
        $record = $this->snapshot('<p>APC: USD 1500</p>');
        $claim = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        $metadata = $claim->extraction_metadata;
        unset($metadata['contexts']);
        $metadata['version'] = 'editorial-v1';
        $metadata['document_version'] = 'editorial-dom-v1';
        $metadata['proposal_key'] = hash('sha256', 'synthetic legacy proposal');
        $legacy = JournalAssertion::create(['journal_id' => $claim->journal_id, 'source_record_id' => $record->id,
            'field' => 'fee', 'availability' => 'known', 'source_locator' => $claim->source_locator,
            'original_wording' => $claim->original_wording, 'extraction_metadata' => $metadata]);
        JournalFee::create(['journal_id' => $claim->journal_id, 'assertion_id' => $legacy->id, 'fee_type' => 'apc', 'amount' => '1500',
            'currency' => 'USD', 'basis' => 'official_explicit_amount', 'applicability' => 'Synthetic legacy evidence.']);
        $this->extract($record);
        $this->assertSame('editorial-v1', $legacy->fresh()->extraction_metadata['version']);
        $this->assertArrayNotHasKey('contexts', $legacy->fresh()->extraction_metadata);
        $this->assertSame('pending', $legacy->verificationStatus()->value);
        foreach ([['kind' => 'unknown', 'text' => 'Heading', 'locator' => '/html/body/h2'],
            ['kind' => 'heading', 'text' => str_repeat('x', 129), 'locator' => '/html/body/h2'],
            ['kind' => 'heading', 'text' => 'Heading', 'locator' => '']] as $badContext) {
            try {
                DB::transaction(function () use ($claim, $badContext): void {
                    $bad = $claim->extraction_metadata;
                    $bad['contexts'] = [$badContext];
                    $bad['proposal_key'] = hash('sha256', json_encode($badContext));
                    JournalAssertion::create(['journal_id' => $claim->journal_id, 'source_record_id' => $claim->source_record_id,
                        'field' => 'fee', 'availability' => 'known', 'source_locator' => $claim->source_locator,
                        'original_wording' => $claim->original_wording, 'extraction_metadata' => $bad]);
                });
                $this->fail('Malformed context persisted.');
            } catch (QueryException $exception) {
                $this->assertSame('23514', $exception->errorInfo[0]);
            }
        }
    }

    public function test_semantic_official_and_doaj_absence_agree_but_numeric_zero_is_distinct(): void
    {
        $record = $this->snapshot('<p>There is no APC</p>');
        $first = JournalAssertion::findOrFail($this->extract($record)['proposals'][0]['assertion_id']);
        $doaj = JournalSource::factory()->create(['type' => 'doaj', 'name' => 'Synthetic DOAJ', 'base_url' => 'https://doaj.org']);
        $observation = SourceRecord::factory()->create(['journal_source_id' => $doaj->id, 'source_url' => 'https://doaj.org/api/v4/journals/synthetic']);
        $claim = JournalAssertion::create(['journal_id' => $first->journal_id, 'source_record_id' => $observation->id, 'field' => 'fee', 'availability' => 'unknown']);
        JournalFee::create(['journal_id' => $first->journal_id, 'assertion_id' => $claim->id, 'fee_type' => 'apc', 'amount' => null, 'currency' => null,
            'basis' => 'doaj_reported_no_apc', 'applicability' => 'Synthetic explicit provider absence.']);
        $this->assertTrue($claim->isExplicitNoApc());
        $this->assertSame([], app(DetectJournalConflicts::class)->assertion($claim));
        $zero = $this->extract($this->snapshot('<p>APC: USD 0</p>', $record))['proposals'][0];
        $this->assertCount(2, $zero['conflict_ids']);
        $this->assertFalse(JournalAssertion::findOrFail($zero['assertion_id'])->isExplicitNoApc());
    }

    #[DataProvider('v4MaximumCases')]
    public function test_v4_abstract_maximum_grammar(string $text, ?int $maximum): void
    {
        $record = $this->snapshot('<h2>Author instructions</h2><p>'.$text.'</p>');
        $result = $this->extract($record);
        $this->assertCount($maximum === null ? 0 : 1, $result['proposals']);
        if ($maximum !== null) {
            $proposal = $result['proposals'][0];
            $this->assertSame('requirement', $proposal['field']);
            $this->assertSame('abstract_words', $proposal['value']['requirement_type']);
            $this->assertSame('max', $proposal['value']['operator']);
            $this->assertSame($maximum, $proposal['value']['value_number']);
            $this->assertSame('abstract', $proposal['value']['scope']);
            $this->assertNull($proposal['value']['article_code']);
            $claim = JournalAssertion::findOrFail($proposal['assertion_id']);
            $this->assertSame('Author instructions: '.$text, $claim->original_wording);
            $this->assertSame('editorial-v5', $claim->extraction_metadata['version']);
            $this->assertSame('editorial-dom-v5', $claim->extraction_metadata['document_version']);
            $this->assertSame('pending', $claim->verificationStatus()->value);
        }
        Http::assertNothingSent();
    }

    public static function v4MaximumCases(): array
    {
        return [
            ['The abstract must not exceed 250 words.', 250],
            ['An abstract that does not exceed 200 words is required.', 200],
            ['Abstracts should not exceed 300 words.', 300],
            ['an abstract that does not exceed 175 words', 175],
            ['Abstract does not exceed 180 words.', 180],
            ['Abstract maximum 220 words.', 220],
            ['The abstract is limited to 230 words.', 230],
            ['Abstracts are limited to 240 words.', 240],
            ['The abstract should be about 200 words.', null],
            ['The abstract must contain at least 200 words.', null],
            ['Abstract length is 150–200 words.', null],
            ['A 200-word abstract is recommended.', null],
            ['Abstract approximately 200 words.', null],
            ['Abstract usually 200 words.', null],
            ['Abstract minimum 200 words.', null],
            ['Abstract 150–200 words.', null],
            ['An abstract that does not exceed 200 words is required for invited authors.', null],
            ['Some abstracts should not exceed 200 words.', null],
            ['The abstract must not exceed 200 words unless approved.', null],
        ];
    }

    #[DataProvider('v4LinkCases')]
    public function test_v4_submission_link_roles(string $label, bool $expected): void
    {
        $url = 'https://submission.example.net/manuscripts/new';
        $record = $this->snapshot('<nav><a href="'.$url.'">'.$label.'</a></nav>');
        $result = $this->extract($record);
        $this->assertCount($expected ? 1 : 0, $result['proposals']);
        if ($expected) {
            $proposal = $result['proposals'][0];
            $this->assertSame('submission_url', $proposal['field']);
            $this->assertSame($url, $proposal['value']['text_value']);
            $this->assertStringContainsString('/nav/a', $proposal['locator']);
            $this->assertSame('pending', $proposal['review_status']);
        }
        Http::assertNothingSent();
    }

    public static function v4LinkCases(): array
    {
        return [
            ['Electronic submission management system', true],
            ['Manuscript submission system', true],
            ['Submission portal', true],
            ['Manuscript submission portal', true],
            ['Electronic submission system', true],
            ['Submission management system', true],
            ['Submission guidelines', false],
            ['Submission instructions', false],
            ['Preparing your submission', false],
            ['Editorial policies', false],
            ['Author instructions', false],
            ['Manuscript preparation', false],
            ['About the submission portal', false],
        ];
    }

    public function test_v5_unsafe_contexts_remain_rejected(): void
    {
        foreach ([
            '<p>For invited authors only:</p><h3>Submission</h3><p>The abstract must not exceed 250 words.</p>',
            '<h2>Examples</h2><a href="https://submission.example.net/new">Submission portal</a>',
            '<nav><p>For invited authors only:</p><a href="https://submission.example.net/new">Electronic submission management system</a></nav>',
            '<h2>Fees</h2><p>There are no publication fees.</p>',
        ] as $html) {
            $record = $this->snapshot($html);
            $before = $this->counts();
            $this->assertSame([], $this->extract($record)['proposals']);
            $this->assertSame($before, $this->counts());
        }
        Http::assertNothingSent();
    }

    #[DataProvider('v5ContextCases')]
    public function test_v5_context_classification(string $html, ?string $field): void
    {
        $record = $this->snapshot($html);
        $result = $this->extract($record);
        $this->assertCount($field === null ? 0 : 1, $result['proposals']);
        if ($field !== null) {
            $p = $result['proposals'][0];
            $this->assertSame($field, $p['field']);
            $this->assertSame('pending', $p['review_status']);
            $claim = JournalAssertion::findOrFail($p['assertion_id']);
            $this->assertSame('editorial-v5', $claim->extraction_metadata['version']);
            $this->assertSame('editorial-dom-v5', $claim->extraction_metadata['document_version']);
            $this->assertNotEmpty($claim->original_wording);
        }
        Http::assertNothingSent();
    }

    public static function v5ContextCases(): array
    {
        $language = '<p>Manuscripts must be submitted in English.</p>';
        $abstract = '<p>An abstract that does not exceed 210 words is required.</p>';
        $link = '<a href="https://submission.example.net/new">Manuscript submission system</a>';
        $long = str_repeat('Unrelated technical background. ', 10);

        return [
            'neutral language' => ['<h2>Preparing your study</h2>'.$language, 'language'],
            'unknown fragment' => ['<h2>Preparing your study</h2><p>English</p>', null],
            'example language' => ['<h2>Example</h2>'.$language, null],
            'conditional language' => ['<p>For invited authors only:</p>'.$language, null],
            'hypothetical language' => ['<h2>Hypothetical policies</h2>'.$language, null],
            'neutral abstract' => ['<h1>Unknown journal information</h1><h2>Overview</h2>'.$abstract, 'requirement'],
            'unrelated long paragraph' => ['<p>'.$long.'</p>'.$abstract, 'requirement'],
            'prior complete example' => ['<p>Example: '.$long.'</p><h3>Submission</h3>'.$abstract, 'requirement'],
            'direct example still applies' => ['<p>Example: '.$long.'</p>'.$abstract, null],
            'example introduces section' => ['<p>Example:</p><h3>Submission</h3>'.$abstract, null],
            'following example applies' => ['<p>Example: the following section demonstrates formatting.</p><h3>Submission</h3>'.$abstract, null],
            'invited example applies' => ['<p>Example: only invited authors use this workflow.</p><h3>Submission</h3>'.$abstract, null],
            'inside example section' => ['<section><h2>Example</h2>'.$abstract.'</section>', null],
            'conditional through intertitle' => ['<p>For invited authors only:</p><h3>Submission</h3>'.$abstract, null],
            'minimum context' => ['<h2>Minimum word counts</h2>'.$abstract, null],
            'unsupported subject scope' => ['<h2>Articles in the subject areas below</h2>'.$language, null],
            'unsupported article scope' => ['<h2>Special invited contributions</h2>'.$abstract, null],
            'body system' => ['<p>'.$link.'</p>', 'submission_url'],
            'navigation system' => ['<nav>'.$link.'</nav>', 'submission_url'],
            'unknown link wrapper' => ['<h2>Useful tools</h2><div>'.$link.'</div>', 'submission_url'],
            'instructions are not portal' => ['<a href="/instructions">Submission guidelines</a>', null],
            'example link' => ['<h2>Example</h2>'.$link, null],
            'conditional link' => ['<p>For invited authors only:</p>'.$link, null],
            'conditional link own prose' => ['<p>For invited authors only: '.$link.'</p>', null],
            'example link own wrapper' => ['<div>Example: '.$link.'</div>', null],
            'disabled link' => ['<div aria-disabled="true">'.$link.'</div>', null],
            'optional language' => ['<h2>Optional recommendations</h2>'.$language, null],
            'previous example section closes' => ['<section><h2>Examples</h2>'.$abstract.'</section><section><h2>Tools</h2>'.$link.'</section>', 'submission_url'],
            'generic fee absence still not APC' => ['<h2>Overview</h2><p>There are no publication fees.</p>', null],
            'negative types not inverted' => ['<h2>Overview</h2><p>This journal does not publish review articles.</p>', null],
            'alternative styles not flattened' => ['<h2>Overview</h2><p>Reference style: plain or plain-fr</p>', null],
            'unknown table scope' => ['<table><caption>Unrecognized scope</caption><tr><td>Abstract</td><td>maximum 200 words</td></tr></table>', null],
        ];
    }

    #[DataProvider('v5FrenchCases')]
    public function test_v5_french_eligibility_keeps_only_unconditioned_english(string $text, bool $expected): void
    {
        $record = $this->snapshot('<h3>Technical preparation</h3><p>'.$text.'</p>');
        $result = $this->extract($record);
        $this->assertCount($expected ? 1 : 0, $result['proposals']);
        if ($expected) {
            $p = $result['proposals'][0];
            $this->assertSame('language', $p['field']);
            $this->assertSame('en', $p['value']['code']);
            $this->assertTrue($p['value']['supported']);
            $claim = JournalAssertion::findOrFail($p['assertion_id']);
            $this->assertStringContainsString('traduction anglaise', $claim->original_wording);
            $this->assertSame(1, JournalLanguage::count());
        }
        Http::assertNothingSent();
    }

    public static function v5FrenchCases(): array
    {
        $base = 'Langues : Revue Synthétique accepte des articles en anglais, français et allemand ; si l’article n’est pas en anglais, une traduction anglaise du titre et du résumé doit être fournie.';

        return [
            'non-English condition' => [$base, true],
            'formatting notes' => [$base.' Le paquet polyglossia est préchargé ; il ne faut pas l’inclure. Le mélange de langues est autorisé.', true],
            'other publication name' => [str_replace('Revue Synthétique', 'Bulletin des Sciences', $base), true],
            'unknown language' => [str_replace('allemand', 'japonais', $base), false],
            'condition on English' => [str_replace('n’est pas en anglais', 'est en anglais', $base), false],
            'additional condition' => [$base.' Les articles anglais sont acceptés uniquement sur invitation.', false],
            'conditional technical note' => [$base.' Le paquet test est obligatoire si le manuscrit est en anglais.', false],
            'unknown trailing prose' => [$base.' Autres règles applicables.', false],
            'English not listed' => [str_replace('anglais, français et allemand', 'français et allemand', $base), false],
            'conditional journal subject' => [str_replace('Revue Synthétique', 'Seulement certains auteurs', $base), false],
        ];
    }
}
