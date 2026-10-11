<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_id', 'assertion_id', 'article_type_id', 'supported'])]
class JournalArticleType extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'supported' => 'boolean',
        ];
    }

    public function journal(): BelongsTo
    {
        return $this->belongsTo(Journal::class);
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(JournalAssertion::class, 'assertion_id');
    }

    public function articleType(): BelongsTo
    {
        return $this->belongsTo(ArticleType::class);
    }
}
