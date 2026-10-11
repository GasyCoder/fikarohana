<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['journal_source_id', 'source_record_id', 'mapping_version', 'item_key', 'payload', 'normalized', 'errors', 'conflict_ids', 'status', 'journal_id', 'catalog_revision_id', 'publication_hash'])]
class IngestionItem extends CatalogModel
{
    protected function casts(): array
    {
        return ['payload' => 'array', 'normalized' => 'array', 'errors' => 'array', 'conflict_ids' => 'array'];
    }

    public function sourceRecord(): BelongsTo
    {
        return $this->belongsTo(SourceRecord::class);
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function assertions(): BelongsToMany
    {
        return $this->belongsToMany(JournalAssertion::class, 'ingestion_claims', 'ingestion_item_id', 'assertion_id')->withPivot('claim_hash');
    }
}
