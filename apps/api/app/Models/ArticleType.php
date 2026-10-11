<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\HasMany;

#[Fillable(['code', 'name', 'normalized_name'])]
class ArticleType extends CatalogModel
{
    public function journalLinks(): HasMany
    {
        return $this->hasMany(JournalArticleType::class);
    }
}
