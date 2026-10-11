<?php

namespace App\Services\JournalIngestion;

use App\Models\CatalogRevision;
use App\Models\IngestionItem;
use App\Services\JournalCatalog\PublishJournalRevision;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishIngestionItem
{
    public function __construct(private PublishJournalRevision $publisher) {}

    public function publish(IngestionItem $item, array $assertionIds): CatalogRevision
    {
        sort($assertionIds);
        if ($assertionIds === [] || count(array_unique($assertionIds)) !== count($assertionIds)) {
            throw new DomainException('explicit_distinct_selections_required');
        }
        $hash = CanonicalJson::hash($assertionIds);

        return DB::transaction(function () use ($item, $assertionIds, $hash): CatalogRevision {
            DB::select('SELECT pg_advisory_xact_lock(170001)');
            $item = IngestionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if ($item->status === 'published') {
                if (! hash_equals($item->publication_hash, $hash)) {
                    throw new DomainException('published_selection_cannot_change');
                }

                return CatalogRevision::findOrFail($item->catalog_revision_id);
            }
            if (! in_array($item->status, ['pending', 'conflicting'], true)
                || $item->assertions()->whereIn('journal_assertions.id', $assertionIds)->count() !== count($assertionIds)) {
                throw new DomainException('publication_requires_materialized_item_selections');
            }
            // Check other staging versions of this same observation/selection before creating a revision.
            $previous = IngestionItem::where('source_record_id', $item->source_record_id)->where('publication_hash', $hash)->where('status', 'published')->first();
            $catalog = $previous === null ? $this->publisher->publish($item->journal, $assertionIds) : CatalogRevision::findOrFail($previous->catalog_revision_id);
            $item->update(['status' => 'published', 'catalog_revision_id' => $catalog->id, 'publication_hash' => $hash]);

            return $catalog;
        });
    }
}
