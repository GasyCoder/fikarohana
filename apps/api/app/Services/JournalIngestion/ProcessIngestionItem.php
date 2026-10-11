<?php

namespace App\Services\JournalIngestion;

use App\Enums\FactAvailability;
use App\Enums\JournalField;
use App\Enums\VerificationStatus;
use App\Models\AssertionReview;
use App\Models\IngestionItem;
use App\Models\Journal;
use App\Models\JournalAssertion;
use App\Models\JournalFee;
use App\Models\JournalIdentifier;
use App\Models\JournalSource;
use DomainException;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use InvalidArgumentException;

class ProcessIngestionItem
{
    public function __construct(private ProposalValidator $validator, private ResolveJournalIdentity $identity, private DetectJournalConflicts $detector) {}

    public function process(IngestionItem $item, ProviderAdapter $provider): IngestionItem
    {
        if ($item->mapping_version !== $provider->mappingVersion() || ! $provider->accepts($item->sourceRecord?->journalSource ?? JournalSource::findOrFail($item->journal_source_id))) {
            throw new DomainException('mapping_or_source_mismatch');
        }
        if (DB::table('ingestion_source_providers')->where('journal_source_id', $item->journal_source_id)->value('provider') !== $provider->key()) {
            throw new DomainException('provider_source_mismatch');
        }

        return DB::transaction(function () use ($item, $provider): IngestionItem {
            DB::select('SELECT pg_advisory_xact_lock(170003)');
            DB::select('SELECT pg_advisory_xact_lock(170001)');
            $item = IngestionItem::whereKey($item->id)->lockForUpdate()->firstOrFail();
            if (! in_array($item->status, ['staged', 'failed'], true)) {
                return $item;
            }
            if ($item->sourceRecord?->status !== 'captured') {
                $item->update(['status' => 'quarantined', 'errors' => ['quarantined_source']]);

                return $item->fresh();
            }
            try {
                $proposal = $provider->normalize($item->payload);
                $this->validator->validate($proposal);
            } catch (ValidationException|DomainException|InvalidArgumentException) {
                $item->update(['status' => 'quarantined', 'errors' => ['invalid_proposal']]);

                return $item->fresh();
            }
            try {
                $journalId = $this->identity->resolve($proposal['identifiers']);
            } catch (DomainException $exception) {
                $code = in_array($exception->getMessage(), ['identifier_owner_conflict', 'identifier_type_conflict'], true)
                    ? $exception->getMessage() : 'identity_conflict';
                $item->update(['status' => 'quarantined', 'errors' => [$code]]);

                return $item->fresh();
            }
            $journal = $journalId === null ? Journal::create(['title' => $proposal['title']]) : Journal::findOrFail($journalId);
            $item->update(['journal_id' => $journal->id, 'normalized' => $proposal]);
            foreach ($proposal['identifiers'] as $identifier) {
                // Existing immutable ownership is reused; this observation remains on the staging item.
                $existingIdentifier = JournalIdentifier::where('normalized_value', $identifier['value'])->first();
                if ($existingIdentifier === null) {
                    $claim = $this->claim($item, JournalField::Identifier, ['type' => $identifier['type'], 'value' => $identifier['value']], 'identifiers');
                    JournalIdentifier::create([
                        'journal_id' => $journal->id, 'assertion_id' => $claim->id, 'type' => $identifier['type'],
                        'value' => $identifier['original_value'] ?? $identifier['value'],
                    ]);
                } elseif ($existingIdentifier->assertion->source_record_id === $item->source_record_id) {
                    $this->claim($item, JournalField::Identifier, ['type' => $identifier['type'], 'value' => $identifier['value']], 'identifiers');
                }
            }
            $conflicts = [];
            foreach (['title' => JournalField::Title, 'scope' => JournalField::Scope, 'country' => JournalField::Country,
                'website' => JournalField::Website, 'submission_url' => JournalField::SubmissionUrl] as $key => $field) {
                if (array_key_exists($key, $proposal)) {
                    $claim = $this->claim($item, $field, ['text_value' => $proposal[$key]], $key,
                        $proposal[$key] === null ? FactAvailability::Unknown : FactAvailability::Known);
                    $conflicts = array_merge($conflicts, $this->conflicts($claim));
                }
            }
            foreach ($proposal['fees'] ?? [] as $index => $fee) {
                $claim = $this->claim($item, JournalField::Fee, $fee, 'fees.'.$index, FactAvailability::from($fee['availability']));
                if ($claim->fee === null) {
                    JournalFee::create([
                        'journal_id' => $journal->id, 'assertion_id' => $claim->id,
                        ...array_diff_key($fee, ['availability' => true]),
                    ]);
                    $claim->unsetRelation('fee');
                }
                $conflicts = array_merge($conflicts, $this->conflicts($claim));
            }
            $item->update(['status' => $conflicts === [] ? 'pending' : 'conflicting', 'conflict_ids' => array_values(array_unique($conflicts)), 'errors' => []]);

            return $item->fresh();
        });
    }

    private function claim(IngestionItem $item, JournalField $field, array $value, string $locator, FactAvailability $availability = FactAvailability::Known): JournalAssertion
    {
        $hash = CanonicalJson::hash([$item->journal_id, $field->value, $availability->value, $value]);
        // A mapping replay of the same observation/value reuses the original claim and its review state.
        $existing = DB::table('ingestion_claims')->join('ingestion_items', 'ingestion_items.id', '=', 'ingestion_claims.ingestion_item_id')
            ->where('ingestion_items.source_record_id', $item->source_record_id)->where('claim_hash', $hash)->value('assertion_id');
        $claim = $existing === null ? JournalAssertion::create([
            'journal_id' => $item->journal_id, 'source_record_id' => $item->source_record_id, 'field' => $field,
            'availability' => $availability, 'text_value' => $value['text_value'] ?? null, 'source_locator' => $locator,
            'original_wording' => is_string($item->payload[$locator] ?? null) ? $item->payload[$locator] : null,
        ]) : JournalAssertion::findOrFail($existing);
        DB::table('ingestion_claims')->insertOrIgnore(['ingestion_item_id' => $item->id, 'assertion_id' => $claim->id, 'claim_hash' => $hash]);

        return $claim;
    }

    private function conflicts(JournalAssertion $claim): array
    {
        $ids = $this->detector->assertion($claim);
        if ($ids !== [] && $claim->verificationStatus() === VerificationStatus::Pending) {
            AssertionReview::create([
                'assertion_id' => $claim->id, 'status' => VerificationStatus::Conflicting, 'method' => 'ingestion_comparison',
                'reviewed_at' => now(), 'reason' => 'Different values for the same journal field/context; explicit review required.',
            ]);
        }

        return $ids;
    }
}
