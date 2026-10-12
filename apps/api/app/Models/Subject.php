<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'normalized_name', 'parent_id', 'provider', 'external_id', 'taxonomy_level', 'revision_fingerprint'])]
class Subject extends CatalogModel
{
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Subject::class, 'parent_id');
    }

    public function children(): HasMany
    {
        return $this->hasMany(Subject::class, 'parent_id');
    }

    public function journalLinks(): HasMany
    {
        return $this->hasMany(JournalSubject::class);
    }
}
