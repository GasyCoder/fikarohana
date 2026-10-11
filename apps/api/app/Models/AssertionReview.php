<?php

namespace App\Models;

use App\Enums\VerificationStatus;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

#[Fillable(['assertion_id', 'status', 'method', 'reviewer_reference', 'reviewed_at', 'verified_at', 'reason'])]
class AssertionReview extends CatalogModel
{
    public $timestamps = false;

    protected function casts(): array
    {
        return [
            'status' => VerificationStatus::class,
            'reviewed_at' => 'immutable_datetime',
            'verified_at' => 'immutable_datetime',
        ];
    }

    public function assertion(): BelongsTo
    {
        return $this->belongsTo(JournalAssertion::class, 'assertion_id');
    }
}
