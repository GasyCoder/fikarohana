<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;

#[Fillable(['journal_source_id', 'provider', 'mapping_version', 'idempotency_key', 'input_hash', 'status', 'errors'])]
class IngestionRun extends CatalogModel
{
    protected function casts(): array
    {
        return ['errors' => 'array'];
    }

    public function source(): BelongsTo
    {
        return $this->belongsTo(JournalSource::class, 'journal_source_id');
    }

    public function items(): BelongsToMany
    {
        return $this->belongsToMany(IngestionItem::class, 'ingestion_run_items')->withPivot(['position', 'unchanged'])->orderByPivot('position');
    }

    /** Current outcomes, counted once per unique item; replay never increments counters. */
    public function counters(): array
    {
        $counts = ['records_seen' => 0, 'records_staged' => 0, 'records_quarantined' => 0, 'records_unchanged' => 0, 'records_failed' => 0];
        foreach ($this->items()->get()->groupBy('id') as $members) {
            $counts['records_seen']++;
            $status = $members->first()->status;
            $counter = match ($status) {
                'quarantined' => 'records_quarantined',
                'failed' => 'records_failed',
                default => $members->every(fn ($item) => (bool) $item->pivot->unchanged) ? 'records_unchanged' : 'records_staged',
            };
            $counts[$counter]++;
        }

        return $counts;
    }
}
