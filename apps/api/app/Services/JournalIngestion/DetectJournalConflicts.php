<?php

namespace App\Services\JournalIngestion;

use App\Enums\FactAvailability;
use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\JournalAssertion;

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
        return CanonicalJson::encode([$claim->availability->value, $claim->text_value,
            $claim->fee?->only(['fee_type', 'amount', 'currency', 'basis', 'applicability'])]);
    }
}
