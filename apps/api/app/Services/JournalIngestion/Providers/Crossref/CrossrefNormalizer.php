<?php

namespace App\Services\JournalIngestion\Providers\Crossref;

use App\Models\JournalIdentifier;
use App\Services\JournalIngestion\PostgresText;
use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Support\Facades\Validator;

class CrossrefNormalizer
{
    public function normalize(array $payload): array
    {
        PostgresText::validate($payload);
        Validator::make($payload, [
            '_lookup_issn' => 'required|string|max:32',
            'message' => 'required|array',
            'message.title' => 'required|string|max:255',
            'message.ISSN' => 'required|array|list|min:1|max:2',
            'message.ISSN.*' => 'required|string|max:32',
            'message.publisher' => 'sometimes|nullable|string|max:255',
        ])->validate();
        $lookup = CatalogNormalizer::issn($payload['_lookup_issn']);
        $candidates = app(CrossrefCandidates::class)->identifiers($lookup);
        $knownTypes = array_column($candidates, 'type', 'value');
        $message = $payload['message'];
        $values = array_map(CatalogNormalizer::issn(...), $message['ISSN']);
        if (count(array_unique($values)) !== count($values) || ! in_array($lookup, $values, true)) {
            throw new DomainException('crossref_identifier_conflict');
        }
        $owners = JournalIdentifier::whereIn('normalized_value', $values)->get();
        if ($owners->pluck('journal_id')->unique()->count() > 1) {
            throw new DomainException('crossref_identifier_conflict');
        }
        foreach ($owners as $existing) {
            $knownTypes[$existing->normalized_value] = $existing->type;
        }
        $types = [];
        $reported = $message['issn-type'] ?? [];
        // Swagger describes a singleton object; the deployed resource may use a list.
        if (is_array($reported) && isset($reported['value'], $reported['type'])) {
            $reported = [$reported];
        }
        if (! is_array($reported) || ! array_is_list($reported) || count($reported) > 2) {
            throw new DomainException('crossref_invalid_issn_types');
        }
        foreach ($reported as $entry) {
            if (! is_array($entry) || ! is_string($entry['value'] ?? null)
                || ! in_array($entry['type'] ?? null, ['print', 'electronic'], true)) {
                throw new DomainException('crossref_invalid_issn_types');
            }
            $value = CatalogNormalizer::issn($entry['value']);
            if (! in_array($value, $values, true) || isset($types[$value])) {
                throw new DomainException('crossref_identifier_conflict');
            }
            $type = $entry['type'] === 'print' ? 'issn' : 'eissn';
            if (isset($knownTypes[$value]) && $knownTypes[$value] !== $type) {
                throw new DomainException('crossref_identifier_conflict');
            }
            $types[$value] = $type;
        }
        $proposal = ['title' => trim($message['title']), 'identifiers' => []];
        foreach ($message['ISSN'] as $original) {
            $value = CatalogNormalizer::issn($original);
            // Untyped new identifiers stay raw; existing type is explicitly local evidence.
            $type = $types[$value] ?? $knownTypes[$value] ?? null;
            if ($type !== null) {
                $proposal['identifiers'][] = ['type' => $type, 'value' => $value, 'original_value' => $original];
            }
        }
        if (isset($message['publisher']) && trim($message['publisher']) !== '') {
            $proposal['publisher'] = trim($message['publisher']);
        }

        return $proposal;
    }
}
