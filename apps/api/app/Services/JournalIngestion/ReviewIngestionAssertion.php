<?php

namespace App\Services\JournalIngestion;

use App\Enums\VerificationStatus;
use App\Models\AssertionReview;
use App\Models\JournalAssertion;
use DomainException;
use Illuminate\Support\Facades\DB;

class ReviewIngestionAssertion
{
    public function review(JournalAssertion $claim, VerificationStatus $status, string $actor, string $method, string $reason): AssertionReview
    {
        if (! preg_match('/^[A-Za-z0-9_.:-]{1,128}$/D', $actor) || ! preg_match('/^[A-Za-z0-9_.:-]{1,64}$/D', $method)
            || trim($reason) === '' || strlen($reason) > 1024) {
            throw new DomainException('invalid_review_metadata');
        }

        return DB::transaction(function () use ($claim, $status, $actor, $method, $reason): AssertionReview {
            DB::select('SELECT pg_advisory_xact_lock(170001)');
            JournalAssertion::whereKey($claim->id)->lockForUpdate()->firstOrFail();
            if (! DB::table('ingestion_claims')->where('assertion_id', $claim->id)->exists()) {
                throw new DomainException('not_an_ingestion_assertion');
            }
            if ($claim->sourceRecord->status !== 'captured') {
                throw new DomainException('quarantined_source');
            }

            return AssertionReview::create([
                'assertion_id' => $claim->id, 'status' => $status, 'method' => $method, 'reviewer_reference' => $actor,
                'reviewed_at' => now(), 'verified_at' => $status === VerificationStatus::Verified ? now() : null, 'reason' => $reason,
            ]);
        });
    }
}
