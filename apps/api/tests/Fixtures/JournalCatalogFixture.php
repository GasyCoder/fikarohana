<?php

namespace Tests\Fixtures;

use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\AssertionReview;
use App\Models\Journal;
use App\Models\JournalAssertion;
use App\Models\JournalFee;
use App\Models\SourceRecord;

class JournalCatalogFixture
{
    public static function assertion(Journal $journal, JournalField $field, array $values = [], ?VerificationStatus $status = null, ?SourceRecord $source = null): JournalAssertion
    {
        $assertion = JournalAssertion::create(array_replace([
            'journal_id' => $journal->id, 'field' => $field,
            'source_record_id' => ($source ?? SourceRecord::factory()->create())->id,
            'text_value' => $field === JournalField::Title ? $journal->title : null,
        ], $values));
        if ($status !== null) {
            self::review($assertion, $status);
        }

        return $assertion;
    }

    public static function review(JournalAssertion $assertion, VerificationStatus $status): AssertionReview
    {
        return AssertionReview::create([
            'assertion_id' => $assertion->id, 'status' => $status, 'method' => 'manual',
            'reviewer_reference' => 'synthetic-reviewer', 'reviewed_at' => now(),
            'verified_at' => $status === VerificationStatus::Verified ? now() : null,
            'reason' => 'Synthetic test evidence only.',
        ]);
    }

    public static function fee(Journal $journal, ?string $amount, ?VerificationStatus $status = null, array $assertionValues = []): JournalFee
    {
        $assertion = self::assertion($journal, JournalField::Fee, array_replace([
            'availability' => $amount === null ? 'unknown' : 'known',
        ], $assertionValues), $status);

        return JournalFee::create([
            'journal_id' => $journal->id, 'assertion_id' => $assertion->id,
            'fee_type' => 'apc', 'amount' => $amount, 'currency' => $amount === null ? null : 'USD',
            'basis' => 'per_article', 'applicability' => 'Synthetic research article context; no taxes or waiver inferred.',
        ]);
    }
}
