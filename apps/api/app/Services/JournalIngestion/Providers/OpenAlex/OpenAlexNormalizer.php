<?php

namespace App\Services\JournalIngestion\Providers\OpenAlex;

use App\Models\IngestionItem;
use App\Models\JournalIdentifier;
use App\Services\JournalIngestion\ExistingJournalCandidates;
use App\Services\JournalIngestion\PostgresText;
use App\Services\JournalIngestion\ResolveJournalIdentity;
use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Support\Facades\Validator;

class OpenAlexNormalizer
{
    public function normalize(array $payload): array
    {
        PostgresText::validate($payload);
        Validator::make($payload, [
            '_lookup_issn' => 'required|string|max:32',
            'display_name' => 'required|string|max:255',
            'issn' => 'required|array|list|min:1|max:20',
            'issn.*' => 'required|string|max:32',
            'issn_l' => 'sometimes|nullable|string|max:32',
            'type' => 'required|in:journal',
            'host_organization' => 'sometimes|nullable|string|max:128',
            'host_organization_name' => 'sometimes|nullable|string|max:255',
        ])->validate();
        $lookup = CatalogNormalizer::issn($payload['_lookup_issn']);
        $candidates = app(ExistingJournalCandidates::class)->identifiers($lookup, 'openalex');
        $known = array_column($candidates, 'type', 'value');
        $values = array_map(CatalogNormalizer::issn(...), $payload['issn']);
        if (count(array_unique($values)) !== count($values) || ! in_array($lookup, $values, true)
            || (isset($payload['issn_l']) && ! in_array(CatalogNormalizer::issn($payload['issn_l']), $values, true))) {
            throw new DomainException('openalex_identifier_conflict');
        }
        // ISSN-L is linking identity, never inferred print/electronic type.
        $owners = JournalIdentifier::whereIn('normalized_value', $values)->get();
        if ($owners->pluck('journal_id')->unique()->count() > 1) {
            throw new DomainException('openalex_identifier_conflict');
        }
        foreach ($owners as $owner) {
            $known[$owner->normalized_value] = $owner->type;
        }
        $proposal = ['title' => trim($payload['display_name']), 'identifiers' => []];
        foreach ($payload['issn'] as $original) {
            $value = CatalogNormalizer::issn($original);
            if (isset($known[$value])) {
                $proposal['identifiers'][] = ['type' => $known[$value], 'value' => $value, 'original_value' => $original];
            }
        }
        // A Source ID can corroborate only an identity resolved by retained exact ISSNs.
        $previous = IngestionItem::whereNotNull('journal_id')
            ->whereHas('sourceRecord', fn ($query) => $query->where('external_id', substr($payload['id'], strlen('https://openalex.org/')))
                ->whereHas('journalSource', fn ($source) => $source->where('type', 'openalex')))->pluck('journal_id')->unique();
        if ($previous->isNotEmpty()) {
            try {
                $resolved = app(ResolveJournalIdentity::class)->resolve($proposal['identifiers']);
            } catch (DomainException) {
                throw new DomainException('openalex_identifier_conflict');
            }
            if ($previous->count() !== 1 || $resolved === null || $resolved !== $previous->first()) {
                throw new DomainException('openalex_identifier_conflict');
            }
        }
        // Institutions hosting repositories are not journal publishers; map only explicit P IDs.
        if (isset($payload['host_organization'])) {
            if (! preg_match('~^https://openalex\.org/[PI][1-9][0-9]{0,19}$~D', $payload['host_organization'])) {
                throw new DomainException('openalex_invalid_host');
            }
            if (str_starts_with($payload['host_organization'], 'https://openalex.org/P')
                && isset($payload['host_organization_name']) && trim($payload['host_organization_name']) !== '') {
                $proposal['publisher'] = trim($payload['host_organization_name']);
            }
        }
        $proposal['taxonomy'] = app(OpenAlexTaxonomy::class)->normalize($payload['topics'] ?? null);

        return $proposal;
    }
}
