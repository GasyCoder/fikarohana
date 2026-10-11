<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['catalog_revision_id', 'journal_id', 'journal_revision_id'])]
class CatalogRevisionJournal extends CatalogModel
{
    public $timestamps = false;

    public function catalogRevision(): BelongsTo
    {
        return $this->belongsTo(CatalogRevision::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function journalRevision(): BelongsTo
    {
        return $this->belongsTo(JournalRevision::class);
    }
}
