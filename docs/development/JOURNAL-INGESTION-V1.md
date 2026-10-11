# Phase 3A — Controlled journal ingestion framework

Status: implemented locally with synthetic fixtures, 2026-10-11. No real provider, external HTTP call or live catalog is introduced. Stop before DOAJ (Phase 3B). [Phase 3 plan](JOURNAL-INGESTION-PLAN.md) describes later providers.

## Architecture

Local approved batch → provider mapping → PostgreSQL staging/source observations → canonical validation → ISSN/eISSN ownership resolution → pending/conflicting field assertions → explicit operator review → selected immutable journal/catalog revisions.

Laravel owns the complete flow. ProviderAdapter has key, mapping version, source compatibility, input validation and normalization methods; it has no transport. ProviderRegistry registers only SyntheticProvider. A future DOAJ adapter will need approved transport, mappings and fixtures separately. No queue/service/repository framework, dependency or public API was added.

Five tables extend the Phase 2 schema:

| Table | Responsibility |
| --- | --- |
| ingestion_source_providers | Immutable one-provider-per-source binding, enforced for runs in PostgreSQL |
| ingestion_runs | UUIDv7, source/provider/mapping, caller idempotency key, canonical input hash, staged/failed/completed state, fixed interruption diagnostics and timestamps |
| ingestion_items | UUIDv7, immutable source/mapping/item key and bounded payload; normalized proposal, sanitized errors/conflict IDs, resolved journal, status and publication receipt |
| ingestion_run_items | Immutable ordered membership and whether the item already existed; duplicate inputs may reference the same item |
| ingestion_claims | Exact assertion IDs and normalized claim hashes; foreign-key/trigger checks bind source and resolved journal |

Run.completed means every item was processed or quarantined; it does not mean verified or published. Item states are staged, failed, quarantined, pending, conflicting and published. A completed run cannot contain staged or failed items, enforced by a database trigger. Independent transactions commit each processed item so a later failure can resume without erasing earlier results. Unexpected item-processing failures roll back that item, then a separate transaction marks the run/item failed with only `processing_interrupted`. Failure while marking a run completed marks only the run failed; successfully processed items remain intact and a retry can finalize the run. Successful earlier items survive. Retrying process resumes failed/staged items, clears the recovered item error and completes the run; the run diagnostic remains as historical evidence. Publication failures roll back the publication transaction and preserve existing evidence/receipts.

PostgreSQL advisory lock 170003 serializes observation reuse and identity reservation; 170001 serializes reviews/publication and materialization's conflict comparisons. Lock order is 170003 then 170001 where both are needed. Row locks protect item and run transitions. Database unique constraints enforce run/item keys; foreign keys restrict history deletion. Staged inputs, run identity, membership, assertion links and sealed receipts cannot be rewritten or deleted. These locks favor correctness for small batches; distributed workers/large-volume scheduling are not implemented.

## Data and provenance boundary

Input is a JSON array of 1–100 envelopes, at most 1 MiB. Each envelope contains external_id, source_url, RFC3339 retrieved_at and payload. The synthetic payload allows title, checksum-valid issn/eissn identifiers, optional scope/country/home/submission URL and controlled fees. Unknown/unsupported keys are rejected. Title/field/list sizes are bounded; each retained payload is at most 60,000 encoded bytes, with a 65,536-byte JSONB guard. Normalized JSONB has the same database bound.

Canonical V1 materialization supports identifiers, title, scope, country, website, submission URL and fees. Other Phase 2 fact types remain available in the catalog model but need explicit mapping/validation/materialization extensions in later slices; arbitrary payload keys cannot create them. The synthetic adapter never infers requirements, publication languages, indexation or accepted article types.

Names retain display text and use existing matching normalization. ISSNs are checksum-validated/formatted while original values remain in staging and identifier evidence. URLs normalize scheme/host without altering case-sensitive paths. In this ingestion slice, URL credentials, query strings and fragments are rejected before payload retention; syntactically valid URLs remain untrusted and are never fetched. Syntax checks do not establish official ownership or SSRF safety.

Structurally safe observations use immutable Phase 2 source_records, even when a mapped identifier/value is invalid and the staging item is quarantined. Before JSONB persistence, recursive validation checks strings and object/array keys for U+0000 and invalid UTF-8. U+0000 in any envelope or nested payload quarantines that record with `postgres_incompatible_text`; valid neighbors remain staged. Nothing is stripped or replaced. Unsafe envelopes retain only a SHA-256 input identity and fixed error code, without their incompatible payload/source record. The local CLI rejects invalid JSON/UTF-8 before accepting a batch. A pre-existing quarantined source observation cannot be promoted by this pipeline. Normalization/retrieval/structural validation never create a verified review.

Missing facts create no inferred claim. Explicit null scalar values/unknown fees stay unknown. Known zero needs currency and explicit evidence. Money accepts nonnegative decimal strings fitting numeric(14,4), never floating-point guesses, NaN/infinity or exponent notation; currency shape is uppercase three letters. Not-applicable fees retain evidenced availability and null amount/currency. Basis/applicability are required; no tax, waiver, universal context or accepted vocabulary is inferred.

New valid identifiers reserve a journal identity, inactive by default, with pending identifier evidence. A record linking identifiers owned by different journals or reassigning print/electronic type enters quarantine. Similar titles never merge journals; existing titles/ownership are not overwritten. For an already-owned identifier from a new observation, its observed value and source-to-journal association remain in staging without duplicating the immutable canonical identifier row. Mapping replay of the original observation can reuse its original identifier assertion. Ownership transfers/mergers require a separate policy.

Different values for the same field/context are detected against retained pending/verified/conflicting evidence; rejected/stale/unknown alternatives do not create a new value conflict. Incoming contradictions receive an automatic conflicting review with comparison method, never verification. Prior assertions/reviews remain intact; source precedence never chooses a winner. Equal corroborating claims remain subject to the existing publisher's conservative explicit-selection/review policy.

## Idempotency and publication

- Source plus caller run key identifies a batch. Canonically sorted object keys make equivalent JSON object ordering stable; different input/provider/mapping under an existing key fails. Array order remains significant.
- Observation fingerprint is SHA-256 of normalized source URL and original permitted payload. Mapping version and retrieval timestamp are separate. Repeat observations reuse the first retrieved_at rather than refresh evidence.
- Each source is bound immutably to one provider on its first persisted run; dry-run creates no binding. PostgreSQL rejects mismatched run providers. Distinct provider/source pairs can reuse external IDs without colliding. Changing provider requires a distinct source.
- Source/item key plus mapping version identifies staging. A new mapping can replay an observation without pretending it was retrieved again. Same observation/journal/field/value reuses identical claims and reviews; changed mappings/values produce distinct proposals/evidence.
- Publication requires explicit distinct assertion IDs belonging to the staged item, a sourced known title and current verified applicable selections. Unknowns and unresolved competing verified/conflicting evidence block it. Operators append review decisions to resolve conflicts; no evidence is deleted.
- Existing PublishJournalRevision creates the journal revision and complete catalog snapshot inside the same transaction as the item receipt. Repeat selection returns the same catalog ID, including across mapping replays of the same observation/claims. A changed selection on a published item fails. Replay returns a historical receipt; it does not renew verification or imply current usability.

Publication does not activate journals or implement public discovery eligibility. Freshness windows, production review permissions and real-source authority remain separately governed. Working identity is not a verified public projection.

## Artisan usage

Run from apps/api against an intentionally configured local database with migrations applied. No development/production migration was executed during this task; tests use the isolated test database. Commands require trusted operator access to the host, not public application access. Use non-sensitive operator references/reasons and never put secrets/private research in local journal metadata.

```bash
php artisan journals:ingestion source pilot
php artisan journals:ingestion stage --provider=synthetic --source=SOURCE_UUID --key=pilot-001 --file=tests/Fixtures/ingestion/synthetic.json --dry-run
php artisan journals:ingestion stage --provider=synthetic --source=SOURCE_UUID --key=pilot-001 --file=tests/Fixtures/ingestion/synthetic.json --limit=100
php artisan journals:ingestion process RUN_UUID
php artisan journals:ingestion status RUN_UUID
php artisan journals:ingestion review ASSERTION_UUID --status=verified --actor=reviewer-001 --method=manual_synthetic --reason='Reviewed synthetic fixture only.'
php artisan journals:ingestion publish ITEM_UUID --assertions=TITLE_ASSERTION_UUID,OTHER_ASSERTION_UUID
```

source returns the synthetic source UUID; stage returns a run UUID; status/process return item/assertion IDs, statuses and sanitized diagnostics. Review each selected claim explicitly. No automatic verification or default publication selection exists. For conflict resolution, append rejected/stale decisions to alternatives as justified, then verify selected evidence; publisher checks remain authoritative. No admin UI or institutional tenancy is introduced.

Dry-run is available only for stage and performs zero writes, including runs, staging and source observations. It normalizes, validates, resolves current identity and previews current value conflicts; its read-only result is advisory and is rechecked during processing. It does not simulate a reviewed publication or reserve identities between records in the preview batch. The file option accepts regular local files only, with bounded reads; remote/stream URLs are rejected. Failed commands print a fixed message without paths, payloads, SQL or exception text. Status exposes operational IDs/codes, not raw provider content.

## Limits, counters and exit codes

`--limit` is supported only for stage (including dry-run). Omitted means 100; explicit values must be decimal integers from 1 through 100. Zero, negative, fractional, textual, empty, exponent-form and excessive values fail before writes. The local file is still read/decoded as a complete bounded JSON array (1 MiB, depth 32); the selected prefix is sliced before provider validation, normalization or persistence. Remaining records are ignored. Batch identity hashes the selected prefix; changing that prefix under the same run key fails. Internal ingestion callers still have the hard 1–100 record ceiling.

Stage results and status/process responses expose counters derived from immutable membership and current item outcomes, once per unique item ID, never per loop position. They are mutually exclusive outcomes summing to records_seen:

| Counter | Meaning |
| --- | --- |
| records_seen | Unique items referenced by the run |
| records_staged | Newly introduced items without a current quarantine/technical failure, including pending/conflicting/published items |
| records_quarantined | Current quarantined items, including reused quarantined items |
| records_unchanged | Previously existing items reused by this run without current quarantine/failure |
| records_failed | Items currently marked as a technical processing failure |

Duplicates within a batch count once; any first creation makes that item new for the run. Idempotent replay and resume never add memberships or increment counts. After recovery, an item's failed count moves back to its accepted/reused outcome; this is a current-state summary, not a count of historical attempts. The fixed run interruption diagnostic retains the handled failure history. Dry-run returns preview decisions only and persists no counters or diagnostics.

Exit 0 means technical execution completed, including mixed or entirely quarantined domain records. Exit 1 means technical/operation failure, including invalid inputs not handled as limit options. Exit 2 identifies invalid `--limit` values or use on another action (argument-parser errors also return nonzero). No SQL, stack trace or payload fragment is printed. A failed process exits 1; a successful resume exits 0.

Item materialization and publication each have their own database transaction. Provider validation/mapping for staging runs outside the staging transaction; no network transport exists. A PostgreSQL-only regression installs a temporary test trigger that raises a constraint error on the second item's fee insertion after journal, identifier, assertion and normalized-item writes. It proves all second-item writes roll back, the first item remains intact, the handled failure is sanitized, and retry creates exactly one set of evidence. No production failure hook was added.

## Verification and limits

JournalIngestionTest uses PostgreSQL and synthetic JSON fixtures, with Http::preventStrayRequests plus no-network adapter/command implementations. Coverage includes staging versus facts, malformed-record quarantine, dry-run with no writes, replay/input changes, first retrieval preservation, mapping replay, same-title separation, ISSN/eISSN ownership/type collisions, zero/unknown/not-applicable fees, normalization/original values, current value conflicts, explicit review/publication, failed-publication rollback, immutable receipts/SQL attribution, retry/resume after an interrupted run, recursive incompatible-text quarantine with valid neighbors, provider/source database isolation, unique counters, CLI limits/exit codes, real partial-write rollback, local-file and batch bounds, real-provider absence, and migration rollback.

A second PostgreSQL connection verifies that ingestion's advisory lock denies concurrent acquisition; unique/attribution guards are also exercised directly through SQL. This is not a load benchmark or a full multiprocess worker test. Normal tests require no provider credentials or network calls. Existing Foundation and catalog tests remain part of the full regression suite.

The new migration fixes a previously untested Phase 2 PL/pgSQL ambiguity between the argument named value and journal_identifiers.value. It uses positional references without changing the helper signature or rewriting the old migration. Rolling back ingestion drops its tables/functions while preserving catalog/vector and the corrected catalog helper. The catalog rollback test now removes dependent ingestion tables first.

Correction-pass ingestion suite: **38 tests passed, 315 assertions, 3.334 seconds** (`php artisan test --filter=JournalIngestionTest`). Final full Laravel suite: **106 tests passed, 782 assertions, 13.049 seconds** (`php artisan test`), including the existing 68 Foundation/catalog tests and 467 assertions. No failures or skips. Both use PostgreSQL 17, isolated `fikarohana_test` at 127.0.0.1:5434 through host PHP/pdo_pgsql and RefreshDatabase; no SQLite fallback or development/production migration.

Correction-pass quality gates: `vendor/bin/pint --test`, `composer validate --no-check-publish`, `git diff --check` and local Markdown target validation (96 targets, zero missing) passed. Pint formatting was applied before final checks. The preceding Phase 3A implementation also passed `npm run contracts:check`; this correction pass changes no contract or routes and did not rerun client/contract builds. No CI, real-provider pagination, network retries/rate limits, scheduled freshness or public review authorization system is claimed.

## Remaining decisions and stop boundary

DOAJ API permissions/access, mappings, quotas and permitted recorded fixtures belong to 3B. Crossref, OpenAlex and official-source fetching remain 3C–3E. Real payload retention/purge, provider taxonomy/currency vocabularies, identity transfers and field-specific freshness/review responsibilities require explicit policies before real-data activation/production publication.

An abrupt OS/process kill or unavailable database can prevent persistence of a handled interruption diagnostic. No watchdog or worker lease recovery exists; staged items remain recoverable by rerunning process. Operational crash detection must be decided before scheduled/distributed ingestion.

No dependencies, public journal endpoints, recommendation, RAG/assistant, Redis, FastAPI, scraping, clients or billing were added. No real provider was called. No branch or commit was created. The implementation stops before DOAJ.

## Exact files changed

31 modified/new files, including the Phase 3A–3E planning changes already present when implementation began.

```text
README.md
apps/api/app/Console/Commands/JournalIngestion.php
apps/api/app/Models/IngestionItem.php
apps/api/app/Models/IngestionRun.php
apps/api/app/Services/JournalIngestion/CanonicalJson.php
apps/api/app/Services/JournalIngestion/DetectJournalConflicts.php
apps/api/app/Services/JournalIngestion/IngestJournalRecords.php
apps/api/app/Services/JournalIngestion/ProcessIngestionItem.php
apps/api/app/Services/JournalIngestion/ProcessIngestionRun.php
apps/api/app/Services/JournalIngestion/PostgresText.php
apps/api/app/Services/JournalIngestion/ProposalValidator.php
apps/api/app/Services/JournalIngestion/ProviderAdapter.php
apps/api/app/Services/JournalIngestion/ProviderRegistry.php
apps/api/app/Services/JournalIngestion/PublishIngestionItem.php
apps/api/app/Services/JournalIngestion/ResolveJournalIdentity.php
apps/api/app/Services/JournalIngestion/ReviewIngestionAssertion.php
apps/api/app/Services/JournalIngestion/SyntheticProvider.php
apps/api/database/migrations/2026_10_11_180000_create_journal_ingestion.php
apps/api/tests/Feature/JournalCatalogTest.php
apps/api/tests/Feature/JournalIngestionTest.php
apps/api/tests/Fixtures/ingestion/synthetic.json
docs/api/API.md
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/development/JOURNAL-INGESTION-PLAN.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/product/ROADMAP.md
```
