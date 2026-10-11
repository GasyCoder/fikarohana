<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_revision_id', 'journal_id', 'assertion_id', 'review_id', 'selection_key'])]
class JournalRevisionAssertion extends CatalogModel
{
    public $timestamps = false;

    public function journalRevision(): BelongsTo
    {
        return $this->belongsTo(JournalRevision::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(JournalAssertion::class, 'assertion_id');
    }

    public function review(): BelongsTo
    {
        return $this->belongsTo(AssertionReview::class, 'review_id');
    }
}
