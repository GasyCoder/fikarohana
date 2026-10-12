<?php

namespace App\Services\JournalIngestion;

use App\Models\IngestionItem;
use App\Models\JournalIdentifier;
use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Support\Facades\DB;
use Throwable;

/** Read-only eligibility/type evidence; never looks up candidates by title. */
class ExistingJournalCandidates
{
    public function identifiers(string $issn, string $consumer = 'crossref'): array
    {
        if (strlen($issn) > 32) {
            throw new DomainException($consumer.'_invalid_request');
        }
        $issn = CatalogNormalizer::issn($issn);
        $owned = JournalIdentifier::where('normalized_value', $issn)->first();
        if ($owned !== null) {
            return JournalIdentifier::where('journal_id', $owned->journal_id)->get()
                ->map(fn ($identifier) => ['type' => $identifier->type, 'value' => $identifier->normalized_value])->all();
        }
        $candidates = [];
        // Read/remap at most 100 staged proposals; no unbounded scan or provider crawler.
        $items = IngestionItem::whereIn('journal_source_id', DB::table('ingestion_source_providers')->select('journal_source_id')->whereIn('provider', ($consumer === 'openalex' ? ['doaj', 'synthetic', 'crossref'] : ['doaj', 'synthetic'])))
            ->where('status', 'staged')->limit(101)->get();
        if ($items->count() > 100) {
            throw new DomainException($consumer.'_candidate_ambiguous');
        }
        foreach ($items as $item) {
            try {
                $provider = DB::table('ingestion_source_providers')->where('journal_source_id', $item->journal_source_id)->value('provider');
                $proposal = app(ProviderRegistry::class)->get($provider)->normalize($item->payload);
            } catch (Throwable) {
                continue;
            }
            if (! in_array($issn, array_column($proposal['identifiers'], 'value'), true)) {
                continue;
            }
            foreach ($proposal['identifiers'] as $identifier) {
                $value = CatalogNormalizer::issn($identifier['value']);
                if (isset($candidates[$value]) && $candidates[$value]['type'] !== $identifier['type']) {
                    throw new DomainException($consumer.'_candidate_ambiguous');
                }
                $candidates[$value] = ['type' => $identifier['type'], 'value' => $value];
            }
        }
        if (! isset($candidates[$issn])) {
            throw new DomainException($consumer.'_existing_candidate_required');
        }

        return array_values($candidates);
    }
}
