<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_id', 'assertion_id', 'subject_id'])]
class JournalSubject extends CatalogModel
{
    public $timestamps = false;

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(JournalAssertion::class, 'assertion_id');
    }

    public function subject(): BelongsTo
    {
        return $this->belongsTo(Subject::class);
    }
}
