<?php

namespace App\Services\JournalIngestion;

use App\Enums\FactAvailability;
use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\JournalAssertion;
use App\Models\Language;
use App\Support\CatalogNormalizer;

class DetectJournalConflicts
{
    public function assertion(JournalAssertion $claim): array
    {
        if ($claim->availability === FactAvailability::Unknown) {
            return [];
        }

        return $this->compare($claim->journal_id, $claim->field, $claim->selectionKey(), $this->value($claim), $claim->id);
    }

    public function proposal(string $journalId, array $proposal): array
    {
        $ids = [];
        foreach (['title', 'scope', 'country', 'website', 'submission_url'] as $field) {
            if (isset($proposal[$field])) {
                $ids = array_merge($ids, $this->compare($journalId, JournalField::from($field), $field.':::',
                    CanonicalJson::encode(['known', $proposal[$field], null])));
            }
        }
        foreach ($proposal['fees'] ?? [] as $fee) {
            if ($fee['availability'] === 'unknown') {
                continue;
            }
            $availability = $fee['availability'];
            unset($fee['availability']);
            if ($fee['amount'] !== null) {
                $parts = explode('.', $fee['amount']);
                $fee['amount'] = $parts[0].'.'.str_pad($parts[1] ?? '', 4, '0');
            }
            $ids = array_merge($ids, $this->compare($journalId, JournalField::Fee, 'fee:'.$fee['fee_type'].'::',
                CanonicalJson::encode([$availability, null, $fee])));
        }
        if (isset($proposal['publisher'])) {
            $ids = array_merge($ids, $this->compare($journalId, JournalField::Publisher, 'publisher:::',
                CanonicalJson::encode(['known', null, null, ['normalized_name' => CatalogNormalizer::name($proposal['publisher']), 'country_code' => $proposal['publisher_country'] ?? null], null])));
        }
        foreach ($proposal['languages'] ?? [] as $code) {
            $language = Language::where('code', $code)->first();
            if ($language !== null) {
                $ids = array_merge($ids, $this->compare($journalId, JournalField::Language, 'language:'.$language->id.'::',
                    CanonicalJson::encode(['known', null, null, null, ['language_id' => $language->id, 'supported' => true]])));
            }
        }

        return array_values(array_unique($ids));
    }

    private function compare(string $journalId, JournalField $field, string $key, string $value, ?string $exclude = null): array
    {
        $ids = [];
        foreach (JournalAssertion::where('journal_id', $journalId)->where('field', $field->value)->get() as $other) {
            if ($other->id === $exclude || $other->availability === FactAvailability::Unknown
                || in_array($other->verificationStatus(), [VerificationStatus::Rejected, VerificationStatus::Stale], true)) {
                continue;
            }
            if ($other->selectionKey() === $key && $this->value($other) !== $value) {
                $ids[] = $other->id;
            }
        }

        return $ids;
    }

    private function value(JournalAssertion $claim): string
    {
        $value = [$claim->availability->value, $claim->text_value,
            $claim->fee?->only(['fee_type', 'amount', 'currency', 'basis', 'applicability'])];
        if (in_array($claim->field, [JournalField::Publisher, JournalField::Language], true)) {
            $value[] = $claim->publisher?->only(['normalized_name', 'country_code']);
            $value[] = $claim->languageLink?->only(['language_id', 'supported']);
        }

        return CanonicalJson::encode($value);
    }
}
