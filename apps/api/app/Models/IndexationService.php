<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'normalized_name'])]
class IndexationService extends CatalogModel
{
    public function journalIndexations(): HasMany
    {
        return $this->hasMany(JournalIndexation::class);
    }
}
