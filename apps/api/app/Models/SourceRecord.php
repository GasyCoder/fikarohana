<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['journal_source_id', 'external_id', 'revision_fingerprint', 'source_url', 'retrieved_at', 'content_hash', 'raw_metadata_reference', 'status'])]
class SourceRecord extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'retrieved_at' => 'immutable_datetime',
        ];
    }

    public function journalSource(): BelongsTo
    {
        return $this->belongsTo(JournalSource::class);
    }

    public function assertions(): HasMany
    {
        return $this->hasMany(JournalAssertion::class);
    }
}
