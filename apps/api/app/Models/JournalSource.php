<?php

namespace App\Models;

use App\Enums\JournalSourceType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['type', 'name', 'base_url'])]
class JournalSource extends CatalogModel
{
    protected function casts(): array
    {
        return [
            'type' => JournalSourceType::class,
        ];
    }

    public function records(): HasMany
    {
        return $this->hasMany(SourceRecord::class);
    }
}
