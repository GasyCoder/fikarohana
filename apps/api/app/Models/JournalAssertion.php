<?php

namespace App\Models;

use App\Enums\FactAvailability;
use App\Enums\FeeType;
use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;

#[Fillable(['journal_id', 'source_record_id', 'field', 'availability', 'text_value', 'publisher_id', 'article_type_id', 'language_id', 'source_locator', 'original_wording', 'effective_from', 'effective_until', 'supersedes_id', 'extraction_metadata'])]
class JournalAssertion extends CatalogModel
{
    public const NO_APC_BASES = ['doaj_reported_no_apc', 'official_reported_no_apc'];

    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'extraction_metadata' => 'array',
            'field' => JournalField::class,
            'availability' => FactAvailability::class,
            'effective_from' => 'immutable_datetime',
            'effective_until' => 'immutable_datetime',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(SourceRecord::class);
    }

    public function publisher(): BelongsTo
    {
        return $this->belongsTo(Publisher::class);
    }

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class);
    }

    public function language(): BelongsTo
    {
        return $this->belongsTo(Language::class);
    }

    public function supersedes(): BelongsTo
    {
        return $this->belongsTo(JournalAssertion::class, 'supersedes_id');
    }

    public function replacements(): HasMany
    {
        return $this->hasMany(JournalAssertion::class, 'supersedes_id');
    }

    public function reviews(): HasMany
    {
        return $this->hasMany(AssertionReview::class, 'assertion_id');
    }

    public function fee(): HasOne
    {
        return $this->hasOne(JournalFee::class, 'assertion_id');
    }

    public function requirement(): HasOne
    {
        return $this->hasOne(JournalRequirement::class, 'assertion_id');
    }

    public function template(): HasOne
    {
        return $this->hasOne(JournalTemplate::class, 'assertion_id');
    }

    public function identifier(): HasOne
    {
        return $this->hasOne(JournalIdentifier::class, 'assertion_id');
    }

    public function subjectLink(): HasOne
    {
        return $this->hasOne(JournalSubject::class, 'assertion_id');
    }

    public function languageLink(): HasOne
    {
        return $this->hasOne(JournalLanguage::class, 'assertion_id');
    }

    public function articleTypeLink(): HasOne
    {
        return $this->hasOne(JournalArticleType::class, 'assertion_id');
    }

    public function indexation(): HasOne
    {
        return $this->hasOne(JournalIndexation::class, 'assertion_id');
    }

    public function latestReview(): HasOne
    {
        return $this->hasOne(AssertionReview::class, 'assertion_id')->orderByDesc('sequence');
    }

    public function verificationStatus(): VerificationStatus
    {
        return $this->latestReview()->first()?->status ?? VerificationStatus::Pending;
    }

    public function isExplicitNoApc(): bool
    {
        return $this->field === JournalField::Fee && $this->availability === FactAvailability::Unknown
            && $this->fee?->fee_type === FeeType::Apc && $this->fee->amount === null && $this->fee->currency === null
            && in_array($this->fee->basis, self::NO_APC_BASES, true);
    }

    public function hasConcreteClaim(): bool
    {
        return $this->availability !== FactAvailability::Unknown || $this->isExplicitNoApc();
    }

    public function selectionKey(): string
    {
        $discriminator = match ($this->field) {
            JournalField::Fee => $this->fee?->fee_type->value,
            JournalField::Requirement => $this->requirement?->requirement_type->value,
            JournalField::Template => $this->template?->format->value,
            JournalField::Identifier => $this->identifier?->normalized_value,
            JournalField::Subject => $this->subjectLink?->subject?->provider === null
                ? $this->subjectLink?->subject_id
                : $this->subjectLink->subject->provider.'|'.$this->subjectLink->subject->external_id,
            JournalField::Language => $this->languageLink?->language_id,
            JournalField::ArticleType => $this->articleTypeLink?->article_type_id,
            JournalField::Indexation => $this->indexation?->indexation_service_id,
            default => null,
        };
        if ($discriminator === null && in_array($this->field, [JournalField::Fee, JournalField::Requirement,
            JournalField::Template, JournalField::Identifier, JournalField::Subject, JournalField::Language,
            JournalField::ArticleType, JournalField::Indexation], true)) {
            throw new \DomainException('An assertion requires its typed fact before publication.');
        }

        return implode(':', [$this->field->value, $discriminator ?? '', $this->article_type_id ?? '', $this->language_id ?? '']);
    }
}
