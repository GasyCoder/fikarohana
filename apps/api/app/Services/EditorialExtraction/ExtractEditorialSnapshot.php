<?php

namespace App\Services\EditorialExtraction;

use App\Models\ArticleType;
use App\Models\AssertionReview;
use App\Models\JournalArticleType;
use App\Models\JournalAssertion;
use App\Models\JournalFee;
use App\Models\JournalLanguage;
use App\Models\JournalRequirement;
use App\Models\JournalSource;
use App\Models\JournalTemplate;
use App\Models\Language;
use App\Models\SourceRecord;
use App\Services\JournalIngestion\CanonicalJson;
use App\Services\JournalIngestion\DetectJournalConflicts;
use DomainException;
use Illuminate\Support\Facades\DB;

class ExtractEditorialSnapshot
{
    public function __construct(private PrepareDocument $preparer, private RuleRegistry $registry, private DetectJournalConflicts $conflicts) {}

    public function extract(SourceRecord $record, bool $dryRun = false, ?string $journalId = null): array
    {
        // Reload exclusively by persisted ID; caller-mutated models/raw HTML are never accepted.
        $record = SourceRecord::find($record->id);
        if (! $record) {
            throw new DomainException('editorial_snapshot_required');
        }
        $source = $record->journalSource;
        $this->validateSource($record, $source, $journalId);
        $document = $this->preparer->prepare($record->raw_content, $record->snapshot_metadata['content_type'], $record->snapshot_metadata['final_url']);
        $proposals = [];
        foreach ($this->registry->rules() as $rule) {
            foreach ($document->units as $unit) {
                foreach ($rule->extract($unit, $document) as $value) {
                    $identity = array_diff_key($value, ['label' => true]);
                    $key = CanonicalJson::hash([$this->registry->version(), $rule->id(), $rule->field(), $identity]);
                    $proposals[$key] ??= ['field' => $rule->field(), 'value' => $value, 'locator' => $unit->locator,
                        'snippet' => $unit->heading !== '' ? $unit->heading.': '.$unit->text : $unit->text,
                        'metadata' => ['proposal_key' => $key, 'rule' => $rule->id(), 'version' => $this->registry->version(),
                            'document_version' => PrepareDocument::VERSION, 'status' => 'deterministic_explicit',
                            'scope' => $value['scope'] ?? 'unspecified',
                            'contexts' => $unit->contexts,
                            ...($unit->href === null ? [] : ['href' => $unit->href, 'anchor' => $unit->text])]];
                }
            }
        }
        if (count($proposals) > 128) {
            throw new DomainException('editorial_proposal_limit');
        }
        // Whole supporting unit required, including qualifiers; never silently truncate.
        foreach ($proposals as $key => $proposal) {
            if (strlen($proposal['snippet']) > 1024 || strlen(CanonicalJson::encode($proposal['metadata'])) > 6000) {
                unset($proposals[$key]);
            }
        }
        $result = ['source_id' => $source->id, 'snapshot_id' => $record->id, 'journal_id' => $source->official_journal_id,
            'extractor_version' => $this->registry->version(), 'dry_run' => $dryRun, 'proposals' => []];
        if ($dryRun) {
            foreach ($proposals as $proposal) {
                $result['proposals'][] = $this->summary($proposal);
            }

            return $result;
        }

        return DB::transaction(function () use ($record, $source, $journalId, $proposals, $result): array {
            // Shared publication/review lock. Parsing and rules run before the write transaction.
            DB::select('SELECT pg_advisory_xact_lock(170001)');
            $source = JournalSource::whereKey($source->id)->lockForUpdate()->first();
            $this->validateSource($record, $source, $journalId);
            foreach ($proposals as $proposal) {
                $claim = JournalAssertion::where('source_record_id', $record->id)
                    ->where('extraction_metadata->proposal_key', $proposal['metadata']['proposal_key'])->first();
                $reused = $claim !== null;
                if (! $claim) {
                    $value = $proposal['value'];
                    $articleId = isset($value['article_code']) ? $this->article($value['article_code'])->id : null;
                    $claim = JournalAssertion::create(['journal_id' => $source->official_journal_id, 'source_record_id' => $record->id,
                        'field' => $proposal['field'], 'availability' => $value['availability'] ?? 'known',
                        'article_type_id' => $articleId, 'text_value' => $value['text_value'] ?? null,
                        'source_locator' => $proposal['locator'], 'original_wording' => $proposal['snippet'],
                        'extraction_metadata' => $proposal['metadata']]);
                    $this->materialize($claim, $value);
                    $ids = $this->conflicts->assertion($claim);
                    if ($ids !== []) {
                        AssertionReview::create(['assertion_id' => $claim->id, 'status' => 'conflicting', 'method' => 'editorial_comparison',
                            'reviewed_at' => now(), 'reason' => 'Competing field/context evidence; explicit review required.']);
                    }
                } else {
                    $ids = $this->conflicts->assertion($claim);
                }
                $result['proposals'][] = $this->summary($proposal) + ['assertion_id' => $claim->id, 'reused' => $reused,
                    'review_status' => $claim->verificationStatus()->value, 'conflict_ids' => $ids];
            }

            return $result;
        });
    }

    private function validateSource(SourceRecord $record, ?JournalSource $source, ?string $journalId): void
    {
        if (! $source || ! in_array($source->type->value, ['official_journal', 'official_publisher'], true)
            || $source->approval_status !== 'approved' || ! $source->official_journal_id
            || ($journalId !== null && $source->official_journal_id !== $journalId)
            || $record->status !== 'captured' || $record->raw_content === null || ! is_array($record->snapshot_metadata)
            || ($record->snapshot_metadata['parser_version'] ?? null) !== 'official-page-raw-v1') {
            throw new DomainException('editorial_invalid_source');
        }
    }

    private function materialize(JournalAssertion $claim, array $value): void
    {
        $attributes = ['journal_id' => $claim->journal_id, 'assertion_id' => $claim->id];
        switch ($claim->field->value) {
            case 'fee':
                JournalFee::create($attributes + array_intersect_key($value, array_flip(['fee_type', 'amount', 'currency', 'basis', 'applicability'])));
                break;
            case 'requirement':
                JournalRequirement::create($attributes + array_intersect_key($value, array_flip(['requirement_type', 'operator', 'value_number', 'value_text', 'notes'])));
                break;
            case 'template':
                JournalTemplate::create($attributes + $value);
                break;
            case 'language':
                $language = Language::firstOrCreate(['code' => $value['code']], ['name' => $value['label']]);
                JournalLanguage::create($attributes + ['language_id' => $language->id, 'supported' => true]);
                break;
            case 'article_type':
                JournalArticleType::create($attributes + ['article_type_id' => $this->article($value['code'])->id, 'supported' => true]);
                break;
        }
    }

    private function article(string $code): ArticleType
    {
        return ArticleType::firstOrCreate(['code' => $code], ['name' => ucwords(str_replace('_', ' ', $code))]);
    }

    private function summary(array $proposal): array
    {
        // Operator summaries contain normalized facts and IDs/locators, never evidence snippets/page bodies.
        return ['field' => $proposal['field'], 'value' => $proposal['value'], 'locator' => $proposal['locator'],
            'rule' => $proposal['metadata']['rule'], 'deterministic_status' => $proposal['metadata']['status']];
    }
}
