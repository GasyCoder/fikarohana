<?php

namespace App\Services\EditorialExtraction;

class FeeRule implements EditorialRule
{
    public function id(): string
    {
        return 'explicit_fee';
    }

    public function field(): string
    {
        return 'fee';
    }

    public function extract(EvidenceUnit $unit, PreparedDocument $document): array
    {
        if ($unit->href !== null || ! RuleContext::allows($unit)) {
            return [];
        }
        $text = RuleContext::labeledText($unit, ['apc', 'article processing charge', 'publication fee', 'submission fee', 'page charge', 'color charge']);
        if (preg_match('/^(?:No article processing charges|There is no APC|Authors are not charged publication fees)\.?$/iD', $unit->text)) {
            return [['availability' => 'unknown', 'fee_type' => 'apc', 'amount' => null, 'currency' => null,
                'basis' => 'official_reported_no_apc', 'applicability' => 'Explicit absence of APC; other charges and applicability require review.']];
        }
        // Full unit match excludes waivers, discounts, promotions, ranges and conditional prose.
        if (! preg_match('/^(APC|Article Processing Charge|Publication fee|Submission fee|Page charge|Color charge):\s*(?:(USD|EUR|GBP|€)\s*([0-9][0-9., ]{0,20})|([0-9][0-9., ]{0,20})\s*(USD|EUR|GBP))\s*\.?$/iD', $text, $m)) {
            return [];
        }
        $currency = strtoupper($m[2] ?: $m[5]);
        $currency = $currency === '€' ? 'EUR' : $currency;
        $amount = trim(isset($m[3]) && $m[3] !== '' ? $m[3] : $m[4]);
        // Explicit formats only: 1500, 1,500.25, 1 500.25, 1500.25, 1500,25.
        // A lone comma + three digits is a thousands separator, not a guessed decimal.
        if (preg_match('/^[0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,4})?$/D', $amount)) {
            $amount = str_replace(',', '', $amount);
        } elseif (preg_match('/^[0-9]{1,3}(?: [0-9]{3})+(?:\.[0-9]{1,4})?$/D', $amount)) {
            $amount = str_replace(' ', '', $amount);
        } elseif (preg_match('/^[0-9]+,[0-9]{1,2}$/D', $amount)) {
            $amount = str_replace(',', '.', $amount);
        } elseif (! preg_match('/^[0-9]{1,10}(?:\.[0-9]{1,4})?$/D', $amount)) {
            return [];
        }
        $parts = explode('.', $amount);
        $integer = ltrim($parts[0], '0') ?: '0';
        if (strlen($integer) > 10) {
            return [];
        }
        $amount = $integer.'.'.str_pad($parts[1] ?? '', 4, '0');
        $kind = match (strtolower($m[1])) {
            'submission fee' => 'submission', 'page charge' => 'page', 'color charge' => 'color', 'publication fee' => 'other', default => 'apc',
        };

        return [['availability' => 'known', 'fee_type' => $kind, 'amount' => $amount, 'currency' => $currency,
            'basis' => 'official_explicit_amount', 'applicability' => 'Explicit labeled fee; article, language, taxes and waivers unspecified.']];
    }
}
