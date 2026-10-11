# Phase 3C — Crossref identity enrichment V1

Status: implemented in Laravel, 2026-10-11. Crossref enriches existing canonical or staged DOAJ/synthetic candidates through Phase 3A; it is not a catalog bootstrap importer. All observations need explicit review before publication. No schema, dependency, public endpoint or client change.

## Official API verification

Official sources accessed **2026-10-11**, before implementation:

- [REST API documentation](https://www.crossref.org/documentation/retrieve-metadata/rest-api/): base `https://api.crossref.org`; journal singleton `GET /journals/{issn}`; public access without registration or credentials. Bibliographic metadata is generally reusable without restriction; abstracts may have publisher/author copyright. No works/abstracts or linked content are acquired here. This is not a blanket license for linked publisher content.
- [Current machine-readable Swagger](https://api.crossref.org/swagger-docs) and [Swagger UI](https://api.crossref.org/swagger-ui/index.html): direct schema retrieval reported API **4.3.4** (the indexed UI search result reported 4.1.0). No version inferred from search cache. Swagger declares `produces: application/json`; requests therefore send `Accept: application/json`, while response validation also accepts the vendor JSON MIME described in the prose. The implemented unversioned singleton route does not assume deployment patch stability. `JournalMessage` has `status`, `message-type`, `message-version` and journal `message`. The journal includes title, publisher, ISSNs, ISSN-type metadata and bibliographic counts/coverage. Swagger describes `issn-type` as one object; mapping accepts that object or a validated list, without guessing types. The actual live shape is reported separately below.
- [Access and identification](https://www.crossref.org/documentation/retrieve-metadata/): no invented credentials; optional real operator email in `mailto` identifies polite-pool requests. Email is also in the descriptive User-Agent, never the stored source URL or payload.
- [API tips](https://www.crossref.org/documentation/retrieve-metadata/rest-api/tips-for-using-the-crossref-rest-api/): cache/reuse observations, choose bounded endpoints, back off on 429. Pagination applies to list/works resources and is not needed for this singleton lookup; no cursors or catalog sync implemented.
- [December 2025 rate-limit change](https://www.crossref.org/blog/announcing-changes-to-rest-api-rate-limits/) and [2026 refinement](https://community.crossref.org/t/refining-rest-api-limits-for-improved-stability-and-reliability/16137): single-record limits public 5/s, polite 10/s; lists public 1/s, polite 3/s. Limits are communicated in `x-rate-limit-limit`, `x-rate-limit-interval`, `x-rate-limit-type`; polite limits may be shared by email. V1 stays serial, at least one second between lookups/retries, respects slower valid rate headers and bounded Retry-After, and defers waits over five seconds. It has no distributed quota coordinator; run one operator acquisition at a time.

Exact permitted request: `GET https://api.crossref.org/journals/{NORMALIZED_CHECKSUM_VALID_ISSN}`, optionally with `?mailto={CONFIGURED_OPERATOR_EMAIL}`. No `/journals` list, `/works`, `/members`, pagination, redirect or provider-supplied URL is fetched.

## Architecture

`CrossrefCandidates` checks existing exact identifiers, without title search. `CrossrefClient` performs bounded serial HTTP acquisition. `CrossrefResponse` validates JSON/singleton envelopes. `CrossrefNormalizer` validates/maps record semantics and consults read-only local identifier evidence. `CrossrefProvider` supplies the unchanged Phase 3A adapter contract, source envelopes, field locators and mapper `crossref-journal-v1`. `ProviderRegistry` registers synthetic, DOAJ and Crossref.

Existing command → read-only candidate/source/input preflight → HTTP outside added database transactions → existing `IngestJournalRecords` → `ProcessIngestionRun` / `ProcessIngestionItem` → explicit review → existing journal/catalog revision services. Network failures abort acquisition before source/run creation; semantic record failures enter normal staging quarantine. No duplicate provenance system or automatic verified/published state.

## Lookup and identity policy

Use repeatable explicit `--issn`; both print/electronic lookup keys use the same endpoint. Input count is 1–100 and cannot exceed `--limit`; invalid inputs fail before HTTP, rather than silently truncating. Normalize checksum-valid values and deduplicate exact equivalents before lookup. At least the requested identifier must occur in the returned `ISSN` list.

The command and mapper require an existing canonical identifier or a safe staged DOAJ/synthetic candidate. Canonical identifiers are selected directly. Unprocessed staging has no persisted normalized proposal, so the read-only fallback remaps at most 100 staged DOAJ/synthetic items with their existing adapter; more than 100 fails closed and requires processing/narrowing staging first. Failed/quarantined/Crossref-only items cannot bootstrap eligibility. No title/publisher/keyword similarity lookup exists. A staged candidate may be materialized by Crossref before its DOAJ run is processed; it remains inactive, pending and unpublished.

Declared `print` maps to `issn`, `electronic` to `eissn`. Missing type may reuse an exact existing local canonical/staged type, explicitly **local evidence**, not a claim that Crossref provided the type. A new untyped ISSN remains raw only. Existing ownership of every returned identifier is considered even without provider types; collisions/type disagreements are quarantined with evidence preserved, never reassigned. The existing maximum of two distinct typed identifiers remains enforced. Title mismatch creates disputed title evidence for the same exact identifier; it does not split/merge identities.

## Field mapping

| Crossref | Fikarohana | Boundary |
| --- | --- | --- |
| Request ISSN | Source external/resource key and `_lookup_issn` | Requested singleton resource identity, not an invented Crossref journal ID |
| `message.ISSN` | Normalized identifiers plus original values | Exact checksum match; unknown new types are not invented |
| `message.issn-type` | Declared print/electronic roles | Validate membership/types; disagreement is retained quarantine |
| `message.title` | Field-level title assertion | Locator `message.title`, original wording; pending/conflicting |
| `message.publisher` | Publisher-name assertion using existing model | Locator `message.publisher`; no invented member ID, country or ownership change |
| `message.last-status-check-time`, provider metadata, `message-version` | Immutable raw staging/fingerprint | Not `retrieved_at`, `verified_at` or editorial-policy freshness |
| Counts, coverage, subjects, flags, unknown fields | Restricted bounded raw evidence only | No quality, indexation, scope, fee or submission-rule inference |

Missing optional publisher/types remain missing. The journal resource schema does not expose a member identity suitable for this mapping; no `/members` or works lookup is added. An unresolved publisher location is not invented; existing normalized-name/country publisher identity may conservatively require review when Crossref reports only a name. Publisher/member semantics remain bibliographic, not current legal journal ownership.

## Provenance, idempotency and conflicts

A distinct source `type=crossref`, name `Crossref`, base official origin uses the existing immutable provider/source binding. Stable source URL has the singleton path with no mailto/query. Payload retains the response envelope/message and explicit local lookup key. Observation retains source/provider, request resource identifier, first UTC retrieval, SHA-256 payload/source fingerprint and mapper version on the staging item. Provider timestamps remain original raw observations.

Existing ISSN ownership/typed assertion is reused unchanged: repeat corroboration remains source-to-journal staging evidence, as designed in Phase 3A. It does not create a second globally unique identifier row or a pretend publishable identifier assertion. A new explicitly typed identifier gets its own field-level assertion/typed link. Title/publisher always use field-level assertions. Identifier collisions are preserved as quarantined evidence with fixed `crossref_identifier_conflict` diagnostics, without attaching disputed facts to an arbitrarily selected journal. No schema change is needed.

Same provider/source + requested external key + payload/source fingerprint reuses the observation/item/claims and first retrieval. New caller keys may link the same item; same caller key + changed input fails. Changed metadata creates new immutable observations/assertions. Print/electronic singleton paths have distinct auditable observations even if their response messages agree, while exact identity converges. Provider namespaces remain isolated from DOAJ. Nothing erases losing evidence/reviews.

Title/publisher disagreements use existing conflicting reviews/claim IDs; safe semantic/identifier disagreement uses retained quarantine. Unsafe NUL/UTF-8 or overlarge payload uses existing hash-only safeguards. Publication is explicit, selected, atomic and repeat-safe through Phase 2 revisions; no ingest changes sealed revisions or publishes a catalog snapshot. Dry-run writes **nothing**, including diagnostics/sources/runs/evidence/revisions; command diagnostics are returned only.

## HTTP/configuration/error policy

Native Laravel HTTP, TLS verification, exact official base, redirects disabled, JSON and MIME validation (`application/json` or `application/vnd.crossref-api-message+json`), depth 32, streamed transfer callbacks and final size guard of 1 MiB per response plus an aggregate 1 MiB response-body budget before collection. Phase 3A additionally enforces 60,000 encoded bytes per retained payload and 1 MiB per batch. Unmapped metadata links are never followed.

Configuration: `CROSSREF_API_BASE_URL=https://api.crossref.org` (any other origin/path rejected), `CROSSREF_API_MAILTO=` (blank uses public pool; supply your real operational email for polite access), `CROSSREF_API_TIMEOUT=20` (1–60 seconds), `CROSSREF_API_CONNECT_TIMEOUT=5` (1–30). Only `.env.example` changed; no real `.env` alteration or credentials required.

At most three attempts per distinct ISSN; retries only connection failures, 429, 500/502/503/504. Fixed sanitized exception codes, never raw bodies, URLs with contact details, exceptions or payloads in run summaries. Excessive Retry-After/slower rate hints defer instead of hammering. Invalid JSON/schema/MIME, 403/other statuses and redirects fail without retry. 404 is an explicit `not_found` diagnostic, no invented record and no deletion; all-404 yields exit 0 without persistence. Successful acquisition/staging (including quarantined records) exits 0; failures exit 1; existing invalid-limit handling exits 2. A later network failure discards the in-memory batch before writes. Successfully staged records survive later independent per-item processing failures through unchanged Phase 3A transactions/retry rules.

## CLI

```bash
# Existing candidate required; safe zero-write preview.
php artisan journals:ingestion stage --provider=crossref --issn=1234-5679 --key=crossref-check-001 --limit=1 --dry-run
# Several explicit identifiers (bounded before requests, deduplicated after normalization).
php artisan journals:ingestion stage --provider=crossref --issn=1234-5679 --issn=2049-3630 --key=crossref-check-002 --limit=2
php artisan journals:ingestion process RUN_UUID
php artisan journals:ingestion status RUN_UUID
```

Examples use synthetic identifiers, not real verified journals. `source --provider=crossref` explicitly creates only the source if desired; ordinary staging creates it only after successful non-empty acquisition. Existing `review` / `publish` select assertions explicitly; see [framework CLI](JOURNAL-INGESTION-V1.md). Crossref rejects `--file`, non-default `--query`, `--transport`; `--issn` is accepted only for Crossref stage. Omitted provider remains synthetic; DOAJ behavior is preserved.

## Tests and live verification

Deterministic tests use synthetic fixtures, `Http::fake()`, prohibited stray requests, fake sleeps, isolated PostgreSQL/RefreshDatabase and unchanged no-SQLite guard. Final executed quality gates and the separate live probe results are recorded below.

Live verification must use one known ISSN through the actual client/mapper with a temporary in-memory candidate, in a rollback-only PostgreSQL transaction **after** acquisition, or an existing eligible candidate. Never persist a seed merely to make the probe pass. No contact address may be invented. Report endpoint/status/observed/mapping/schema and table counts independently from deterministic test results. The temporary diagnostic candidate must remain rollback-only, never a persisted seed.

## Source roles and limits

DOAJ provides OA-directory identity, declared languages/subjects and explicit APC evidence; it remains source-attributed and needs review. Crossref corroborates bibliographic identity/title/publisher only, not APC, accepted languages, author guidelines, templates, submission rules, review duration, acceptance/quality scores or Scopus/WoS claims. Official journal/publisher sources later support mutable requirements, fees, templates and editorial rules; no site fetching is introduced here.

No catalog sync, scheduled crawler, cursors, background queue, distributed rate limiter, identifier transfer/merge resolution, canonical member model, freshness automation, recorded real fixture or production catalog activation. Review/retention owners and real publisher disambiguation need operational decisions. No OpenAlex, scraping, public journal API, recommendation, projects/workspace, assistant/RAG/embeddings, Redis/FastAPI, frontend, billing, reference manager or acceptance prediction.

## Failures encountered and corrected

1. Initial test command was launched at repository root: `Could not open input file: artisan`. Reran from `apps/api`; no application change.
2. First Crossref suite: 37 tests, 29 passed, seven failed and one error. Seven assertions incorrectly assumed PostgreSQL JSONB key-order preservation; changed comparisons to canonical JSON SHA-256, which preserves original values/list order without requiring object key order. One counter assertion used `seen` instead of the existing `records_seen`. The specific 11 affected cases passed first (75 assertions), then complete relevant suites passed. No test was deleted, skipped or disabled.
3. First live lookup returned HTTP 406, zero observations. Root cause: the client requested the vendor response MIME in `Accept`, while current official Swagger declares `produces: application/json`. Changed only request negotiation to Laravel `acceptJson()`; still validate both documented JSON response MIME types. The focused regression passed (1 test/2 assertions), followed by Crossref, DOAJ and full PostgreSQL suites. The same ISSN then returned 200. This was a task-caused client error, not an external network outage or a reason to weaken security.

Final review made identifier collision diagnostics explicitly `crossref_identifier_conflict` through the existing framework and bounded aggregate response acquisition to 1 MiB. Focused collision cases passed (4 tests/29 assertions) and aggregate-budget regression passed (1 test/3 assertions) before final complete suites. No further test/command failure occurred.

## Live network result — 2026-10-11

Actual Laravel HTTP client, one known identifier **1932-6203**, public pool with no fabricated email:

- Endpoint: `https://api.crossref.org/journals/1932-6203`.
- Initial request: **406**, **0 observations**, mapping unverified; corrected negotiation as above.
- Repeated same identifier: **200**, **1 observation**, Phase 3A **dry-run mapping valid**, **no schema mismatch observed**; `issn-type` was a **list**, differing from Swagger's object declaration but covered by explicit validation.
- **0 persisted Crossref observations**, **0 journal/catalog publications**; counts across all **37 public PostgreSQL tables unchanged**.

No persisted real catalog candidate existed. After acquisition completed outside a database transaction, a temporary synthetic-labelled staging scaffold for the exact identifier/type was used solely to exercise candidate eligibility and the actual mapper/dry-run; the entire scaffold transaction was rolled back. It did not create a canonical journal or a real seed. No raw live fixture, linked journal URL, works or member resource was saved/fetched. This is one-sample transport/mapping evidence, not production catalog activation or exhaustive real-data coverage. Deterministic tests separately exercise actual command preflight/stage/process/review/publication.

## Final executed quality gates

DOAJ command: `php artisan test --filter='DoajCsvTest|DoajProviderTest'`.

PostgreSQL 17 only: isolated `fikarohana_test` at 127.0.0.1:5434, host PHP/pdo_pgsql, RefreshDatabase and existing unsafe-database/SQLite rejection. No development/production migration or dependency installation.

| Gate | Final result |
| --- | --- |
| `php artisan test --filter=CrossrefProviderTest` | **49 tests / 267 assertions passed**, 3.581 seconds |
| Focused DOAJ API/CSV suite | **107 tests / 566 assertions passed**, 6.554 seconds |
| `php artisan test` | **262 tests / 1614 assertions passed**, 29.837 seconds; no remaining failures/skips |
| `vendor/bin/pint --test` | Passed; formatting applied beforehand |
| `composer validate --no-check-publish` | Valid |
| `composer audit --format=json` | Zero advisories, zero abandoned packages |
| `npm run contracts:validate` | OpenAPI 3.1 valid |
| `npm run contracts:check` | Generated contracts reproducible/current; unchanged contract |
| `git diff --check` | Passed |
| Markdown local-link validation | **124 local targets, zero missing** |

Coverage includes exact ISSN/eISSN/both, title/publisher mapping and absence, object/list/missing types, retained field provenance, identical/changed payloads and first-retrieval preservation, provider scoping, title/publisher/identifier conflicts, untyped owned collisions, staged-only eligibility and no catalog bootstrap/title merges, dry-run all-table invariance, semantic partial failure/counters, network partial failure, explicit review/publication and transactional rollback, JSON/UTF-8/NUL/schema/MIME/size/aggregate bounds, 404/429/5xx/timeout, fixed-origin/TLS/contact identification, CLI bounds/preflight and no arbitrary URL fetching. Existing PostgreSQL ingestion tests still exercise actual partial-write rollback, retry and advisory-lock concurrency.

Phase verdict: **PASS** within bounded identity-enrichment V1. Existing history is untouched; HEAD remains `6195589`. All Phase 3C changes are uncommitted on master. No branch/commit/push or dependency/model migration.

## Exact Phase 3C files changed

26 files:

```text
README.md
apps/api/.env.example
apps/api/app/Console/Commands/JournalIngestion.php
apps/api/app/Services/JournalIngestion/IngestJournalRecords.php
apps/api/app/Services/JournalIngestion/ProcessIngestionItem.php
apps/api/app/Services/JournalIngestion/ProviderRegistry.php
apps/api/app/Services/JournalIngestion/Providers/Crossref/CrossrefCandidates.php
apps/api/app/Services/JournalIngestion/Providers/Crossref/CrossrefClient.php
apps/api/app/Services/JournalIngestion/Providers/Crossref/CrossrefNormalizer.php
apps/api/app/Services/JournalIngestion/Providers/Crossref/CrossrefProvider.php
apps/api/app/Services/JournalIngestion/Providers/Crossref/CrossrefResponse.php
apps/api/config/journal_ingestion.php
apps/api/tests/Feature/CrossrefProviderTest.php
apps/api/tests/Feature/JournalIngestionTest.php
apps/api/tests/Fixtures/ingestion/crossref/README.md
apps/api/tests/Fixtures/ingestion/crossref/journal.json
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/development/CROSSREF-ADAPTER-V1.md
docs/development/JOURNAL-INGESTION-PLAN.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/product/ROADMAP.md
```
