<?php

namespace App\Services\JournalCatalog;

use App\Enums\FactAvailability;
use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\CatalogRevision;
use App\Models\CatalogRevisionJournal;
use App\Models\Journal;
use App\Models\JournalAssertion;
use App\Models\JournalRevision;
use App\Models\JournalRevisionAssertion;
use DomainException;
use Illuminate\Support\Facades\DB;

class PublishJournalRevision
{
    /** Explicit internal publication only: no fetching, source winner selection or API. */
    public function publish(Journal $journal, array $assertionIds): CatalogRevision
    {
        return DB::transaction(function () use ($journal, $assertionIds): CatalogRevision {
            // Serialize complete catalog snapshots and per-journal version assignment.
            DB::select('SELECT pg_advisory_xact_lock(170001)');
            Journal::whereKey($journal->id)->lockForUpdate()->firstOrFail();
            if ($assertionIds === [] || count($assertionIds) !== count(array_unique($assertionIds))) {
                throw new DomainException('Publication requires distinct evidence selections.');
            }
            $claims = JournalAssertion::where('journal_id', $journal->id)->whereIn('id', $assertionIds)->get();
            if ($claims->count() !== count($assertionIds)) {
                throw new DomainException('Every selected assertion must belong to this journal.');
            }
            if (! $claims->contains(fn ($claim) => $claim->field === JournalField::Title && $claim->availability === FactAvailability::Known)) {
                throw new DomainException('Publication requires sourced journal title.');
            }
            $revision = JournalRevision::create([
                'journal_id' => $journal->id,
                'version' => (JournalRevision::where('journal_id', $journal->id)->max('version') ?? 0) + 1,
            ]);
            foreach ($claims as $claim) {
                $review = $claim->latestReview()->first();
                if ($review?->status !== VerificationStatus::Verified || $claim->availability === FactAvailability::Unknown) {
                    throw new DomainException('Publication requires verified evidence with a known or explicitly not-applicable value.');
                }
                $key = $claim->selectionKey();
                foreach ($journal->assertions()->where('field', $claim->field->value)->where('id', '<>', $claim->id)->get() as $other) {
                    if ($other->hasConcreteClaim() && in_array($other->verificationStatus(), [VerificationStatus::Verified, VerificationStatus::Conflicting], true)
                        && $other->selectionKey() === $key) {
                        throw new DomainException('Competing current assertions require explicit review resolution.');
                    }
                }
                JournalRevisionAssertion::create([
                    'journal_revision_id' => $revision->id, 'journal_id' => $journal->id,
                    'assertion_id' => $claim->id, 'review_id' => $review->id, 'selection_key' => $key,
                ]);
            }
            $revision->update(['published_at' => now()]);
            $previous = CatalogRevision::whereNotNull('published_at')->orderByDesc('version')->first();
            $catalog = CatalogRevision::create([]);
            foreach ($previous?->journals ?? [] as $member) {
                if ($member->journal_id !== $journal->id) {
                    CatalogRevisionJournal::create([
                        'catalog_revision_id' => $catalog->id, 'journal_id' => $member->journal_id,
                        'journal_revision_id' => $member->journal_revision_id,
                    ]);
                }
            }
            CatalogRevisionJournal::create([
                'catalog_revision_id' => $catalog->id, 'journal_id' => $journal->id, 'journal_revision_id' => $revision->id,
            ]);
            $catalog->update(['published_at' => now()]);

            return $catalog->fresh();
        });
    }
}
