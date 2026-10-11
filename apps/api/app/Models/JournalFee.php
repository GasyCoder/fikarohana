<?php

namespace App\Models;

use App\Enums\FeeType;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['journal_id', 'assertion_id', 'fee_type', 'amount', 'currency', 'basis', 'applicability', 'notes'])]
class JournalFee extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'fee_type' => FeeType::class,
            'amount' => 'decimal:4',
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
