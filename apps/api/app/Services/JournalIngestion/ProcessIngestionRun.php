<?php

namespace App\Services\JournalIngestion;

use App\Models\IngestionItem;
use App\Models\IngestionRun;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

class ProcessIngestionRun
{
    public function __construct(private ProcessIngestionItem $processor) {}

    public function process(IngestionRun $run, ProviderAdapter $provider): IngestionRun
    {
        if ($run->provider !== $provider->key() || $run->mapping_version !== $provider->mappingVersion()) {
            throw new DomainException('run_provider_or_mapping_mismatch');
        }
        if ($run->fresh()->status === 'completed') {
            return $run->fresh();
        }
        foreach ($run->items()->get()->unique('id') as $item) {
            try {
                $this->processor->process($item, $provider);
            } catch (Throwable) {
                // The item transaction has rolled back; record only a fixed diagnostic afterwards.
                DB::transaction(function () use ($run, $item): void {
                    DB::select('SELECT pg_advisory_xact_lock(170003)');
                    $currentRun = IngestionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                    $currentItem = IngestionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
                    if (in_array($currentItem->status, ['staged', 'failed'], true)) {
                        $currentItem->update(['status' => 'failed', 'errors' => ['processing_interrupted']]);
                    }
                    if ($currentRun->status !== 'completed') {
                        $currentRun->update(['status' => 'failed', 'errors' => ['processing_interrupted']]);
                    }
                });
                throw new DomainException('processing_interrupted');
            }
        }
        try {
            DB::transaction(function () use ($run): void {
                $current = IngestionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'completed') {
                    $current->update(['status' => 'completed']);
                }
            });
        } catch (Throwable) {
            DB::transaction(function () use ($run): void {
                $current = IngestionRun::whereKey($run->id)->lockForUpdate()->firstOrFail();
                if ($current->status !== 'completed') {
                    $current->update(['status' => 'failed', 'errors' => ['processing_interrupted']]);
                }
            });
            throw new DomainException('processing_interrupted');
        }

        return $run->fresh();
    }
}
