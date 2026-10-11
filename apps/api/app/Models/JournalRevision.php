<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['journal_id', 'version', 'published_at'])]
class JournalRevision extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function selections(): HasMany
    {
        return $this->hasMany(JournalRevisionAssertion::class);
    }

    /** Current usability is separate from the immutable as-published evidence selection. */
    public function usableSelections(): HasMany
    {
        return $this->selections()->whereRaw("EXISTS (
            SELECT 1 FROM journal_assertions a WHERE a.id = journal_revision_assertions.assertion_id
            AND (SELECT r.status FROM assertion_reviews r WHERE r.assertion_id = a.id ORDER BY r.sequence DESC LIMIT 1) = 'verified'
            AND (a.effective_from IS NULL OR a.effective_from <= CURRENT_TIMESTAMP)
            AND (a.effective_until IS NULL OR a.effective_until > CURRENT_TIMESTAMP)
            AND NOT EXISTS (
                SELECT 1 FROM journal_assertions other WHERE other.journal_id = a.journal_id AND other.field = a.field
                AND other.id <> a.id AND other.availability <> 'unknown'
                AND (SELECT r.status FROM assertion_reviews r WHERE r.assertion_id = other.id ORDER BY r.sequence DESC LIMIT 1) IN ('verified', 'conflicting')
                AND catalog_assertion_selection_key(other.id) = journal_revision_assertions.selection_key
            )
        )");
    }
}
