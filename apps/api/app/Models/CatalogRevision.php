<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['published_at'])]
class CatalogRevision extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'version' => 'integer',
            'published_at' => 'immutable_datetime',
        ];
    }

    public function journals(): HasMany
    {
        return $this->hasMany(CatalogRevisionJournal::class);
    }
}
