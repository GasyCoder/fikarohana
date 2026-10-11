<?php

namespace App\Services\JournalIngestion;

use App\Models\JournalIdentifier;
use DomainException;

class ResolveJournalIdentity
{
    /** Read-only decision; caller serializes resolution and identifier reservation. */
    public function resolve(array $identifiers): ?string
    {
        $owners = JournalIdentifier::whereIn('normalized_value', array_column($identifiers, 'value'))->get();
        if ($owners->pluck('journal_id')->unique()->count() > 1) {
            throw new DomainException('identifier_owner_conflict');
        }
        foreach ($identifiers as $identifier) {
            $existing = $owners->firstWhere('normalized_value', $identifier['value']);
            if ($existing !== null && $existing->type !== $identifier['type']) {
                throw new DomainException('identifier_type_conflict');
            }
        }

        return $owners->first()?->journal_id;
    }
}
