<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['name', 'normalized_name', 'website_url', 'country_code'])]
class Publisher extends CatalogModel
{
    public function journals(): HasMany
    {
        return $this->hasMany(Journal::class);
    }
}
