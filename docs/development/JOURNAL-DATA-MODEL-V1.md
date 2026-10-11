# Phase 2 — Journal data model and provenance V1

Status: implemented data-model phase, 2026-10-11. Foundation V1 authentication/API behavior remains intact. No external ingestion, public journal API, discovery UI or live journal data exists. All exercised journal facts are synthetic test fixtures.

## Physical schema

One Laravel PostgreSQL-only migration creates 22 tables. Twenty-two concrete Eloquent models plus CatalogModel expose explicit relationships and casts. New domain IDs use Laravel 13 HasUuids, which generates UUIDv7; existing user/token IDs remain integers. Serial review sequences/catalog versions are ordering metadata, not domain primary keys.

| Tables | Implemented data |
| --- | --- |
| publishers | Name/normalized matching name, optional website/country, timestamps |
| journals | Publisher reference, title/normalized title/short title, optional country/home URL, inactive-by-default flag, timestamps |
| journal_identifiers | Explicit journal/identifier assertion, issn/eissn type, original display value and canonical checksum-valid value |
| subjects / journal_subjects | Optional parent hierarchy, normalized name, evidenced journal membership |
| languages / journal_languages | Stable code/name, evidenced explicit supported boolean |
| article_types / journal_article_types | Canonical code/name, evidenced explicit supported boolean |
| indexation_services / journal_indexations | Registry identity and specifically attributed membership/support claim |
| journal_sources | Controlled provider type/name/base URL; no credentials or universal authority score |
| source_records | Provider/external ID/revision fingerprint, URL, retrieval timestamp, optional SHA-256 hash/metadata reference, captured/quarantined state |
| journal_assertions | Journal/source record/field, availability, scalar text or publisher value when applicable, optional article/language context, locator/original wording, effective dates, predecessor |
| assertion_reviews | Assertion, monotonic sequence, controlled status, method, optional non-sensitive reviewer reference/reason, reviewed/verified dates |
| journal_fees | Assertion, controlled fee kind, exact nullable numeric(14,4) amount/currency, required basis/applicability, optional notes |
| journal_requirements | Assertion, controlled kind/operator, numeric/text/list value of the appropriate type, optional notes |
| journal_templates | Assertion, Word/LaTeX/PDF-guide/other format, official URL and label; no copied file |
| journal_revisions / journal_revision_assertions | Journal version and exact assertion/review/context selections; assemble then seal once |
| catalog_revisions / catalog_revision_journals | Global UUID/monotonic version and complete immutable journal-version map |

Working journal/publisher columns are identity records, not implicitly verified public fact envelopes. A material published title, country, publisher, home/submission URL or scope uses an assertion. Submission URL is deliberately not a mutable journal column. Guideline evidence is represented by source URLs/locators; no document extraction/corpus exists. Public DTOs/eligibility are later work.

Only file-format requirement lists use JSONB. Other material values are queryable relational columns, with explicit foreign keys. Numeric count requirements use min/max/exact; reference style, structure/declarations and figure/table guidance use sourced text; file formats use non-empty strings in a non-empty list. Unknown/new complex requirement kinds need an explicit schema/enum decision rather than arbitrary JSON.

## Provenance and state

Each material relationship/value has its own assertion and source observation. Composite foreign keys bind assertion ID, journal ID and field; one journal-level source cannot pretend to back every field. Observation/source URL/retrieved_at is separate from review/verified_at. Source observations, assertions, typed facts and reviews are append-only, enforced against direct SQL updates/deletions.

- pending: unreviewed/imported proposition; no review also means pending.
- verified: reviewed under a recorded method/date; not equivalent to mere retrieval.
- stale: retained evidence that must not be used as current verified guidance.
- conflicting: explicitly disputed competing evidence; no silent winner.
- rejected: retained assertion rejected by review, not erased.

Availability is known, unknown or not_applicable. Missing relationship means unknown, never false/support. Explicit supported=false requires evidence just like true. Known zero APC is amount=0 plus currency and applicable evidence. Unknown fee has amount/currency NULL; explicitly not-applicable fee also has NULL amounts but different evidenced availability. Unknown never becomes free. No total-cost guarantee or currency conversion is inferred.

Article/language context is optional, but NULL means unspecified, not universal acceptance. Fee applicability/basis remains required. Effective dates with NULL bounds are unknown bounds, not proof of permanent validity. Currency/country/language syntax is checked; externally approved vocabularies and field-specific freshness policies remain open.

SourcePrecedence is a documented preference for review: official journal/publisher for mutable publication rules; relevant registry for indexation; official/directory/bibliographic corroboration for identity. It never assigns confidence percentages, resolves conflicts automatically or turns one provider's presence into universal trust. Official template records require official journal/publisher source identity; actual ownership/applicability still needs review. No live site checks are made.

## Publication and history

The internal PublishJournalRevision service accepts explicit selected assertion IDs. A PostgreSQL transaction/advisory lock serializes complete catalog snapshots and journal version assignment. It requires sourced title and verified selected evidence; unknowns, missing typed values, expired/future applicability and unresolved competing verified/conflicting claims are rejected. Duplicate corroborating claims also require explicit selection/review resolution in this conservative V1 primitive. Source preference never silently decides a winner.

A journal revision starts unsealed. Each selection records its canonical field/value/article/language key and exact review ID. PostgreSQL enforces typed-key agreement, matching journal/review references, current verification and exclusion of quarantined observations. Sealing rechecks verification and applicability; published revisions and membership cannot change or accept later entries. Catalog snapshots reference published journal revisions and copy the prior map with this journal replaced. Later recommendation runs can retain the catalog UUID/version and journal revision IDs; no recommendation tables or runs exist yet.

Assertions can name same-journal/same-field predecessors via supersedes_id; old values and source observations remain. The latest review is determined by its serial sequence, not ambiguous timestamp ties. currentRevision finds the newest published version. usableSelections checks current reviews, competing claims and effective dates without rewriting the as-published snapshot/review. Historical evidence and current usability are deliberately separate.

Changing a fee therefore creates new assertion/value/review records and a new journal/catalog revision. Mark prior evidence stale/rejected as appropriate; do not update its amount or delete a losing claim. A new source fingerprint creates a new observation; repeat creation with the same provider/key/fingerprint reuses the original retrieval time and never silently resets freshness.

## Database invariants and deletion

CHECK/UNIQUE/FK constraints and owned PostgreSQL triggers protect:

- Global normalized ISSN/eISSN ownership, format and checksum, even when bypassing Eloquent; similar titles may remain distinct journals.
- Subject parent foreign keys, sibling/root normalized-name uniqueness and cycle prevention.
- Controlled fields, provider/status/fee/template/requirement types and appropriately typed values.
- Nonnegative exact money; currency required for any amount, including zero; unknown/not-applicable cannot carry amounts.
- Required non-empty names/context, safe URL syntax without userinfo, review dates not preceding retrieval, ordered effective dates.
- Field/journal attribution, same-field/journal predecessors and exact review-to-assertion selection references.
- Immutable evidence, protected source/vocabulary semantics once referenced, sealed revision membership and catalog version mappings.

Every history-bearing foreign key uses RESTRICT; no cascading evidence deletion exists. Deleting a subject/language/type/indexation/source/publisher referenced by evidence is rejected. Evidence-bearing link deletion is itself rejected, rather than deleting its assertion/source. Removal from current guidance uses a review/new published revision. An entirely unreferenced working identity/vocabulary can be physically deleted. Withdraw journals with active=false while retaining evidence; full merge/transfer lifecycle is not implemented.

Migration down intentionally drops this phase's tables/functions for developer rollback, in reverse dependency order. It does not drop vector. The rollback test performs DDL inside a nested test transaction and restores the schema after checking extension preservation. Ordinary application operations do not provide a history-purge capability.

## Security boundary

CatalogNormalizer normalizes matching names without modifying display strings, checks ISSN checksum and normalizes URL scheme/host while retaining path/query/fragment. URLs remain untrusted strings. Only HTTP(S) without userinfo is admitted; no fetching, redirects, DNS/SSRF adapter or object storage is implemented. Store no secret query parameters, executable source payload, provider credentials or private researcher content. Optional metadata references do not introduce a storage integration.

Review references are optional non-sensitive identifiers, not a new admin/user workflow. No public route exposes these internal models or accepts catalog writes. Laravel remains canonical; clients and optional Python services are unchanged.

## Executed verification

PostgreSQL 17 test database: fikarohana_test at 127.0.0.1:5434, existing dedicated pgvector test container. Host PHP runs PHPUnit with pdo_pgsql; phpunit.xml forces the test database/connection and clears DB_URL. RefreshDatabase isolates records; expected database failures use savepoints. No SQLite fallback or development/production migration was run.

Final complete suite: **68 tests passed, 467 assertions, 11.170 seconds**; 25 journal catalog tests contribute 158 assertions, and 43 Foundation tests contribute 309 assertions. No failures or skips remain. Pint --test and composer validate --no-check-publish passed. npm run contracts:check passed with the unchanged specification/generated declarations. The route list still contains exactly the seven Foundation API routes. git diff --check and the local documentation link check passed. No new dependency, branch or commit was created. Tests exercise synthetic zero/unknown/paid/conflicting APC, French/unknown/explicitly unsupported language, registry membership, stale evidence, official template URL, invalid/duplicate ISSN, typed requirements, field attribution, observation idempotency, review resolution, revision snapshots/usability, foreign keys/deletion and rollback. Foundation authentication regressions run in the same complete suite.

## Remaining data decisions and next ingestion phase

1. Pilot catalog, disciplines/languages, review owners and source/document access/reuse rights.
2. Provider adapters, payload permissions, external identifiers, checkpoints/retries/quarantine, batch publication and ingestion metrics.
3. Freshness windows, verification methods, review permissions/workflow and provider-specific conflict handling.
4. Approved taxonomy/locale/currency/index registries; country semantics; additional requirement/identifier kinds and fee ranges/OA/waiver/tax contexts.
5. ISSN ownership correction/transfer/merger policy: V1 prevents reassignment/duplicate ownership; name similarity never auto-merges identities.
6. Evidence retention/correction/purge procedures, production database roles and bounded catalog snapshot scale/backup policy.

These are real integration/release decisions, not permission to invent missing facts. No provider is enabled, no reviewed real seed catalog imported, and no public discovery eligibility/API is claimed. Ingestion should build on these primitives after rights/mappings/review policy are approved.

## Exact files changed in Phase 2

52 files; existing Foundation implementation/locks/clients/infrastructure remain unchanged.

```text
README.md
apps/api/app/Enums/FactAvailability.php
apps/api/app/Enums/FeeType.php
apps/api/app/Enums/JournalField.php
apps/api/app/Enums/JournalSourceType.php
apps/api/app/Enums/RequirementType.php
apps/api/app/Enums/TemplateFormat.php
apps/api/app/Enums/VerificationStatus.php
apps/api/app/Models/ArticleType.php
apps/api/app/Models/AssertionReview.php
apps/api/app/Models/CatalogModel.php
apps/api/app/Models/CatalogRevision.php
apps/api/app/Models/CatalogRevisionJournal.php
apps/api/app/Models/IndexationService.php
apps/api/app/Models/Journal.php
apps/api/app/Models/JournalArticleType.php
apps/api/app/Models/JournalAssertion.php
apps/api/app/Models/JournalFee.php
apps/api/app/Models/JournalIdentifier.php
apps/api/app/Models/JournalIndexation.php
apps/api/app/Models/JournalLanguage.php
apps/api/app/Models/JournalRequirement.php
apps/api/app/Models/JournalRevision.php
apps/api/app/Models/JournalRevisionAssertion.php
apps/api/app/Models/JournalSource.php
apps/api/app/Models/JournalSubject.php
apps/api/app/Models/JournalTemplate.php
apps/api/app/Models/Language.php
apps/api/app/Models/Publisher.php
apps/api/app/Models/SourceRecord.php
apps/api/app/Models/Subject.php
apps/api/app/Rules/Issn.php
apps/api/app/Services/JournalCatalog/PublishJournalRevision.php
apps/api/app/Services/JournalCatalog/SourcePrecedence.php
apps/api/app/Support/CatalogNormalizer.php
apps/api/database/factories/JournalFactory.php
apps/api/database/factories/JournalSourceFactory.php
apps/api/database/factories/PublisherFactory.php
apps/api/database/factories/SourceRecordFactory.php
apps/api/database/migrations/2026_10_11_170000_create_journal_catalog.php
apps/api/tests/Feature/JournalCatalogTest.php
apps/api/tests/Fixtures/JournalCatalogFixture.php
docs/api/API.md
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/data/JOURNAL-SCHEMA.md
docs/development/CONVENTIONS.md
docs/development/JOURNAL-DATA-MODEL-V1.md
docs/development/TESTING.md
docs/development/TODO.md
```
