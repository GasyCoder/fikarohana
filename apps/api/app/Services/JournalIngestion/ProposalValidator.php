<?php

namespace App\Services\JournalIngestion;

use App\Support\CatalogNormalizer;
use DomainException;
use Illuminate\Support\Facades\Validator;

class ProposalValidator
{
    public function validateShape(array $payload): void
    {
        PostgresText::validate($payload);
        Validator::make(['payload' => $payload], [
            'payload' => 'required|array:title,identifiers,scope,country,website,submission_url,fees,publisher,publisher_country,languages,subjects',
            'payload.title' => 'required|string|max:255',
            'payload.identifiers' => 'required|array|min:1|max:2',
            'payload.identifiers.*' => 'required|array:type,value',
            'payload.identifiers.*.type' => 'required|in:issn,eissn',
            'payload.identifiers.*.value' => 'required|string|max:32',
            'payload.scope' => 'sometimes|nullable|string|max:4096',
            'payload.country' => 'sometimes|nullable|string|max:2',
            'payload.website' => 'sometimes|nullable|string|max:2048',
            'payload.submission_url' => 'sometimes|nullable|string|max:2048',
            'payload.fees' => 'sometimes|array|max:5',
            'payload.fees.*' => 'required|array:fee_type,availability,amount,currency,basis,applicability',
            'payload.fees.*.fee_type' => 'required|in:apc,submission,page,color,other',
            'payload.fees.*.availability' => 'required|in:known,unknown,not_applicable',
            'payload.fees.*.amount' => 'present|nullable|string|max:20',
            'payload.fees.*.currency' => 'present|nullable|string|max:3',
            'payload.fees.*.basis' => 'required|string|max:64',
            'payload.fees.*.applicability' => 'required|string|max:2048',
            'payload.publisher' => 'sometimes|required|string|max:255',
            'payload.publisher_country' => 'sometimes|required|string|regex:/^[A-Z]{2}$/D',
            'payload.languages' => 'sometimes|array|max:30',
            'payload.languages.*' => 'required|string|distinct|regex:/^[a-z]{2,3}$/D',
            'payload.subjects' => 'sometimes|array|max:30',
            'payload.subjects.*' => 'required|string|distinct|max:255',
        ])->validate();
    }

    public function validate(array $proposal): void
    {
        PostgresText::validate($proposal);
        // The canonical V1 shape is also validated for future adapters, independently of raw input.
        $raw = $proposal;
        if (isset($raw['identifiers']) && is_array($raw['identifiers'])) {
            foreach ($raw['identifiers'] as &$identifier) {
                unset($identifier['original_value']);
            }
            unset($identifier);
        }
        $this->validateShape($raw);
        if (trim($proposal['title']) === '') {
            throw new DomainException('empty_title');
        }
        foreach ([$proposal['publisher'] ?? null, ...($proposal['subjects'] ?? [])] as $name) {
            if ($name !== null && trim($name) === '') {
                throw new DomainException('empty_vocabulary_name');
            }
        }
        if (isset($proposal['publisher_country']) && ! isset($proposal['publisher'])) {
            throw new DomainException('publisher_country_without_publisher');
        }
        $seen = [];
        foreach ($proposal['identifiers'] as $identifier) {
            $value = CatalogNormalizer::issn($identifier['value']);
            if (isset($seen[$value]) || isset($seen[$identifier['type']])) {
                throw new DomainException('duplicate_identifier');
            }
            $seen[$value] = $seen[$identifier['type']] = true;
        }
        foreach (['website', 'submission_url'] as $field) {
            if (isset($proposal[$field])) {
                CatalogNormalizer::url($proposal[$field]);
            }
        }
        if (isset($proposal['country']) && ! preg_match('/^[A-Z]{2}$/D', $proposal['country'])) {
            throw new DomainException('invalid_country');
        }
        if (isset($proposal['scope']) && trim($proposal['scope']) === '') {
            throw new DomainException('empty_scope');
        }
        $types = [];
        foreach ($proposal['fees'] ?? [] as $fee) {
            if (isset($types[$fee['fee_type']])) {
                throw new DomainException('duplicate_fee_context');
            }
            $types[$fee['fee_type']] = true;
            if (trim($fee['basis']) === '' || trim($fee['applicability']) === '') {
                throw new DomainException('empty_fee_context');
            }
            if ($fee['availability'] === 'known') {
                if (! is_string($fee['amount']) || ! preg_match('/^(0|[1-9][0-9]{0,9})(\.[0-9]{1,4})?$/D', $fee['amount'])
                    || ! is_string($fee['currency']) || ! preg_match('/^[A-Z]{3}$/D', $fee['currency'])) {
                    throw new DomainException('invalid_fee_amount_currency');
                }
            } elseif ($fee['amount'] !== null || $fee['currency'] !== null) {
                throw new DomainException('unknown_fee_has_value');
            }
        }
    }
}
