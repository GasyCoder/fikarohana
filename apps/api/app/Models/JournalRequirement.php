<?php

namespace App\Models;

use App\Enums\RequirementType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_id', 'assertion_id', 'requirement_type', 'operator', 'value_number', 'value_text', 'value_items', 'notes'])]
class JournalRequirement extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'requirement_type' => RequirementType::class,
            'value_number' => 'integer',
            'value_items' => 'array',
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
