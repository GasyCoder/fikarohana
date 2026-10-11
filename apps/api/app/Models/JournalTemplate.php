<?php

namespace App\Models;

use App\Enums\TemplateFormat;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_id', 'assertion_id', 'format', 'official_url', 'label'])]
class JournalTemplate extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'format' => TemplateFormat::class,
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
}
