# Phase 3D — OpenAlex enrichment V1

Status: implemented internally in Laravel, 2026-10-12. Enrich existing identifier-qualified candidates only, through Phase 3A and Phase 2 evidence/revisions. No public API, catalog crawler, dependency, frontend or AI change. All Phase 3D files remain uncommitted.

## Current official API verification

Official sources accessed **2026-10-12**, before coding. Current developer URLs redirect to the official Help Center; older GitBook/GitHub copies and early-2026 announcements disagree on authentication/quotas and are not used as current operational authority.

- [Authentication](https://help.openalex.org/api/authentication/): anonymous basic queries are allowed; an optional free account key increases budget. Query `api_key` and Bearer authentication are supported. V1 uses only the Authorization header; no key in URLs, payloads, provenance or logs. No key or email is invented. Current docs do not require a mailto/polite-pool configuration here.
- [Current costs](https://help.openalex.org/access/example-costs/): single-entity requests are free; anonymous daily budget $0.10 and free-account budget $1 concern chargeable API usage. Authentication docs cap throughput at 100 requests/second and describe 429 for exhausted budget/throughput. V1 uses serial requests at least one second apart, not those maximum rates. Response quota headers are not universal per-second pacing hints. No billing implementation or distributed quota coordinator.
- [Singleton retrieval](https://help.openalex.org/api/get-single-entities/): unversioned base `https://api.openalex.org`; supported direct `GET /sources/issn:{issn}`. Source IDs are `S...`, canonical entity URLs `https://openalex.org/S...`. Merged resources may redirect; V1 rejects redirects for explicit operator review rather than follow them.
- [Source attributes](https://help.openalex.org/data/sources/attributes/) and [Sources](https://help.openalex.org/data/sources/): Source identity/title, linking/list ISSNs, publisher/institution host semantics, type, provider dates, explicit ranked topics with hierarchy, and descriptive counts. `issn_l` is not print/electronic type. Source topics describe published works, not authoritative aims/scope or current submission rules.
- [Topics](https://help.openalex.org/data/topics/), [Subfields](https://help.openalex.org/data/subfields/), [Fields](https://help.openalex.org/data/fields/), [Domains](https://help.openalex.org/data/domains/): explicit four-level hierarchy. Topic IDs `T...`; other levels use qualified numeric paths. Assignments are provider-derived scholarly signals. Deprecated concepts are not mapped. No Fikarohana LLM runs or generates taxonomy.
- [Filtering](https://help.openalex.org/api/filtering/): exact source `issn` filter exists; OR values capped at 100. List pagination has per_page at most 100 and basic paging up to 10,000, then cursors. V1 uses singleton lookup only, so no list pagination or filter crawler is needed.
- [Reuse](https://help.openalex.org/data/how-its-built/): OpenAlex dataset is CC0; source attribution is retained. This does not authorize downloading linked articles, templates or official-site content.

Guessed `/api/get-singleton/` and `/access/licensing/` documentation paths were unavailable. Used the linked official singleton page and documented data-build/reuse policy instead; no provider behavior was inferred from those failures.

## Components and workflow

`OpenAlexClient` handles native HTTP; `OpenAlexResponse` checks JSON singleton shape/Source ID; `OpenAlexNormalizer` maps journal identity/publisher and explicit taxonomy; `OpenAlexTaxonomy` preserves qualified paths. `OpenAlexProvider` implements the unchanged adapter interface with mapper **openalex-source-topics-v1**. Shared `ExistingJournalCandidates` is extracted from Crossref's bounded read-only resolver; `CrossrefCandidates` retains its prior policy as a wrapper.

Existing Artisan command → input/source/existing-candidate preflight → HTTP outside transactions → source/envelopes → IngestJournalRecords staging → ProcessIngestionRun/item → explicit review → existing atomic journal/catalog revisions. No HTTP in normalizers/materialization. Sources use existing `type=openalex`, name OpenAlex; no new source/provenance subsystem.

## Lookup policy and CLI

Repeatable `--issn` accepts 1–100 requested identifiers, no more than `--limit`; omission of limit means 100. Entire input validates before HTTP, then normalized exact equivalents deduplicate. Print/electronic lookup is identical; types come only from exact existing local evidence. OpenAlex's ISSN list/ISSN-L never guesses a new print/electronic role. Unknown alternate identifiers remain raw, but owned alternate identifiers still participate in collision checks.

Candidates must exist canonically or as staged synthetic/DOAJ/Crossref evidence. Remapping fallback reads at most 100 staged items, fails closed above that bound and excludes OpenAlex-only bootstrap. Crossref staging itself retains its original DOAJ/synthetic eligibility requirements. Unknown candidate fails before network/writes; no title/publisher/topic similarity merge. Returned identifier list must contain the lookup; linking ISSN must belong to that list. Only `type=journal` maps into the journal catalog; other source types quarantine rather than imply peer review.

```bash
php artisan journals:ingestion stage --provider=openalex --issn=1932-6203 --key=openalex-check-001 --limit=1 --dry-run
php artisan journals:ingestion stage --provider=openalex --issn=1234-5679 --issn=2049-3630 --key=openalex-check-002 --limit=2
php artisan journals:ingestion process RUN_UUID
php artisan journals:ingestion status RUN_UUID
```

1932-6203 requires an eligible local candidate; other example identifiers are synthetic. `source --provider=openalex` explicitly creates only a source. Ordinary stage creates a source only after nonempty successful acquisition, never during dry-run. `--file`, `--transport` and non-default `--query` are rejected for enrichment. Synthetic remains default; DOAJ transports are unchanged. Stage success including quarantine/all-404 exits 0, operation/network failure 1, invalid limit 2.

## Exact Source mapping

| Input | Mapping | Boundary |
| --- | --- | --- |
| `id` | External key `S...`, stable constructed API `/sources/S...` reference | No fetched provider-returned URL; full original ID retained |
| `display_name` | Title assertion, locator/display wording | Pending/conflicting; working title not overwritten |
| `issn[]` | Exact known local ISSN/eISSN types for identity corroboration | New untyped alternates raw only; all owned values checked |
| `issn_l` | Validated linking identifier in raw evidence | Never equated with print role or used to transfer ownership |
| `host_organization`, `host_organization_name` | Publisher-name evidence only for explicit `P...` publisher ID | Institution `I...` stays raw; absent host/name produces no invented publisher; country not inferred |
| `type` | Journal eligibility check, retained raw descriptive type | No peer-review/quality/editorial inference or new canonical type field |
| `topics[]` ID/name/path | Source-qualified Subject versions and field assertions | Provider-derived discovery evidence; explicit parents only |
| `updated_date`, `created_date` | Original raw observation/fingerprint | Separate from retrieval/review dates |
| Counts, topic count/share, summary_stats, alternate titles, homepage, IDs, OA/index/APC metadata | Raw bounded provider evidence | No ranking, fee, requirements, quality or acceptance claim |

## Taxonomy and schema extension

The existing Subject/JournalSubject model lacks external IDs and semantic levels. A single additive PostgreSQL migration extends **subjects**, without a second taxonomy system, with nullable `provider`, `external_id`, `taxonomy_level`, `revision_fingerprint`. The raw synthetic proposal contract stays unchanged and rejects the new taxonomy field; provider normalization supplies the internal extension. Existing native/DOAJ subjects remain unqualified; their uniqueness/selection keys stay unchanged. Provider nodes identify their exact ID, display name and domain/field/subfield/topic level. Root/parent relationships use existing `parent_id` foreign keys and cycle guards. Missing adjacent levels produce no invented edge; a topic with no parents remains a qualified root with level topic.

Provider uniqueness is `(provider, external_id, revision_fingerprint)`. Fingerprint includes ID, level, display name and parent's revision fingerprint, so a renamed/reparented ancestor creates new descendant paths. Normalized names are never provider identity. Native `(parent_id, normalized_name)` uniqueness becomes a partial index for provider-null rows; distinct provider IDs/versions may legitimately share a name.

SQL checks enforce complete qualification, current valid ID/level shapes and 64-character hashes. Provider vocabulary versions are immutable even before linkage, including direct SQL; parent provider/adjacent level must agree. Existing history-restricting FKs remain. Every distinct observed node has a subject assertion plus typed journal link, source record and raw locator. Repeated nodes within a response deduplicate; contradictory repeated IDs/parents quarantine the record, preserving safe original evidence. Bounded maximum: 30 topics, at most 120 distinct nodes.

Subject selection keys for provider nodes use provider + external ID, consistently in PHP and PostgreSQL. Different versions of the same provider node therefore compete through the existing review/publication checks, rather than appear as unrelated simultaneous canonical facts. Native subject keys retain their prior UUID semantics. Nothing rewrites sealed selections. Migration rollback refuses a populated provider vocabulary to avoid dropping history; on empty provider vocabulary it restores old columns, uniqueness and SQL key behavior atomically. Tests exercise both paths and reapplication. No development/production migration was run.

## Provenance, idempotency and conflicts

Stable OpenAlex Source ID is source-record external identity, not ISSN. Raw Source payload remains intact with explicit local `_lookup_issn` context. Source/provider linkage, first UTC retrieval, content/source fingerprint and mapper remain separate. All material claims reference their observation; source IDs and provider timestamps remain auditable. Print/electronic resource requests that return one Source ID can have distinct request-context observations, converging to the same journal and shared vocabulary versions. Repeating an identical lookup reuses observation/item/claims; later retrieval does not renew freshness. A changed payload under a new key creates evidence, while the same key with changed payload fails.

Identifier ownership checks include all supplied identifiers. If an OpenAlex Source already has a processed journal linkage, the existing ResolveJournalIdentity service must resolve the identifiers actually retained in the proposal to that same journal. An unresolved retained identity, a different journal or multiple prior journal linkages yields openalex_identifier_conflict, including candidates that exist only in staging. Candidate sibling identifiers omitted from the returned proposal cannot satisfy this check. Source IDs never resolve or merge journal identity themselves. No source ID/ISSN silently transfers; incompatible responses retain quarantine with `openalex_identifier_conflict`. Title/publisher contradictions create conflicting claims and preserve earlier evidence/working identity. Taxonomy name/parent changes append versions and conflicting reviews for the same provider ID; reject/stale alternatives explicitly before verified publication. Missing topics are not deletions. No automatic verified/published/winner status.

Dry-run is zero-write, including sources, bindings, runs, items, assertions, reviews, vocabularies and revisions; diagnostics exist only in returned results. Taxonomy conflicts are previewed read-only and rechecked on materialization. Item transactions roll back all taxonomy/claim writes on partial failure; retained staged evidence survives and retry is safe. Publication retains existing atomic/repeat-safe Phase 2 receipts and sealed snapshots.

## HTTP and configuration

`OPENALEX_API_BASE_URL=https://api.openalex.org` only; any alias, port/path or alternate origin fails. `OPENALEX_API_KEY=` optional real key, printable token shape up to 512 characters; sent only as Bearer header. `OPENALEX_API_TIMEOUT=20` (1–60), `OPENALEX_API_CONNECT_TIMEOUT=5` (1–30). `.env.example` only; real `.env` untouched. No mailto setting, dependency or generic fetch abstraction.

TLS enabled, redirects disabled, JSON MIME/depth 32, transfer Content-Length/progress and final size checks, 1 MiB per response and total acquisition. Existing staging retains at most 60,000 encoded payload bytes and 1 MiB per batch. Oversize/invalid JSON/schema/MIME never retries. Up to three attempts for connection/timeouts, 429 and 500/502/503/504 with exponential 1/2-second backoff; Retry-After respected up to five seconds, otherwise defer. 401/403/409 fail sanitized, no anonymous downgrade after a configured key fails. 404 reports not_found with no observation/deletion; all-404 writes nothing. Network failure after an earlier response discards acquisition before persistence. Raw exception/body/header/key/SQL content is never command output or run summary. Single operator serial acquisition only; distributed quotas are later work.

## Provider roles and descriptive metrics

DOAJ: attributed OA/directory, languages, subjects and declared APC. Crossref: bibliographic identity/title/publisher. OpenAlex: explicit provider-derived scholarly taxonomy/discovery and identity corroboration. Official journal/publisher sources later: current submission rules/guidelines/APC/templates. Claims coexist with field-specific review; provider presence never proves quality, trust, accepted language or Scopus/WoS membership.

works_count/cited_by_count/topic counts and bibliometric indicators remain raw descriptive observations only; no canonical ranking column, journal quality score, acceptance probability or recommendation computation. No LLM inference.

## Tests, failures and live result

Final executed results and the separate live probe are recorded below. PHPUnit uses PostgreSQL, synthetic fixtures, Http::fake, preventStrayRequests and fake sleeps; no Internet/key required. Real SQL guards, rollback/retry, staged/pending/review/publication, idempotency, taxonomy updates and all-table dry-run counts are exercised.

Failures encountered: guessed official doc paths unavailable (correct linked official pages used); first suite 47/48 passed because an earlier HTTP fake intercepted a later fake sequence (split isolated scenarios, two focused tests passed); correction script used repository-relative paths from apps/api and failed before edits (reran from root); subsequent migrate:fresh found an existing owned PL/pgSQL function (CREATE OR REPLACE fixes repeatability; same two focused tests passed); injected rollback test initially failed on expected processing_interrupted (assert exception and reload persisted failed state, focused test 1/11 passed). No application behavior changed to mask test failures; no test disabled/skipped/deleted or SQLite fallback.

## Limits and remaining decisions

No production activation, full sync, all-sources download, list pagination, classifier call, taxonomy reconciliation into native disciplines or recorded real fixture. Taxonomy/current API schemas can change; bounded malformed/unknown shapes fail closed. Native publisher matching is normalized-name/country, not global organization-ID identity; OpenAlex institution hosts remain raw. Source changes, taxonomy deletions, identifier transfers and distributed quotas need explicit policies. Operational retention, freshness/review owners, credentials provisioning and broader permitted live coverage remain open. Populated rollback intentionally blocks rather than discard taxonomy history. After an intentional rollback refusal, prefer a forward correction/migration; controlled restoration from a validated backup is the alternative. Never delete provider taxonomy evidence merely to force rollback.

No scraping, official requirements extraction, recommendations, public journal APIs, projects/workspace, assistant/RAG/embeddings, Redis/FastAPI, clients, billing, manuscript analysis, formatting, reference management or acceptance prediction. Laravel remains canonical; Next.js/Expo are unchanged API clients. No branch/commit/push; Phase 3A–3C history untouched.

## Final executed verification — 2026-10-12

PostgreSQL 17 only: existing isolated fikarohana_test at 127.0.0.1:5434, host PHP/pdo_pgsql, RefreshDatabase and the unchanged dedicated-database/SQLite rejection. No development/production migration, dependency installation, branch, staging, commit or push. HEAD stays `701e9a4`; Phase 3A–3C history and real .env remain unchanged.

| Gate | Final result |
| --- | --- |
| `php artisan test --filter=OpenAlexProviderTest` | **58 tests / 300 assertions passed**, 6.432 seconds |
| `php artisan test --filter=CrossrefProviderTest` | **49 tests / 267 assertions passed**, 3.892 seconds |
| Focused DOAJ API/CSV suite | **107 tests / 566 assertions passed**, 7.202 seconds |
| `php artisan test` | **320 tests / 1913 assertions passed**, 31.035 seconds |
| `vendor/bin/pint --test` | Passed; formatting applied beforehand |
| `composer validate --no-check-publish` | Valid |
| `composer audit --format=json` | Zero advisories/abandoned packages |
| `npm run contracts:validate` | OpenAPI 3.1 valid |
| `npm run contracts:check` | Generated contracts reproducible/current |
| `git diff --check` | Passed |
| Markdown local-link check | **136 targets, zero missing** |

After the rollback test correction, OpenAlex 57/296, Crossref 49/267, DOAJ 107/566 and full Laravel 319/1909 passed. Final review kept raw synthetic validation unchanged (taxonomy accepted only in the internal normalized shape); the new impersonation-refusal regression passed first (1/4), followed by every complete provider/full suite above. No further failures, skips or disabled tests.

Live verification used the actual native client/response validator/provider/normalizer/Phase 3A dry-run with one exact ISSN:

- Endpoint: `https://api.openalex.org/sources/issn:1932-6203`, **HTTP 200**, **1 Source**.
- Source ID: `https://openalex.org/S202381698`, title **PLoS ONE**, identifiers **1932-6203**.
- **25 topics**, explicit **domain, field, subfield, topic** levels; **valid** dry-run, no mapping errors or schema mismatch observed.
- **0 persistent writes**, **0 canonical publications**; all **37 public-table row counts unchanged**.

No eligible persisted candidate existed in the isolated test database. Acquisition completed before any DB transaction; a temporary synthetic-labelled staging candidate with a diagnostic electronic-ISSN role was then created inside a rollback-only transaction to exercise actual mapping eligibility. The scaffold was fully rolled back, not a real seed or a claim that OpenAlex identifies print/electronic type. The dry-run itself wrote nothing. No raw live fixture was saved and no provider-returned URL was fetched. This one-sample compatibility result does not establish broad catalog coverage/production authority. No live DOAJ/Crossref request was made in Phase 3D.

Phase verdict: **PASS** for bounded Phase 3D enrichment. Production activation and Phase 3E remain outside this delivery.

## Exact Phase 3D files changed

35 files, all uncommitted:

```text
README.md
apps/api/.env.example
apps/api/app/Console/Commands/JournalIngestion.php
apps/api/app/Models/JournalAssertion.php
apps/api/app/Models/Subject.php
apps/api/app/Services/JournalIngestion/DetectJournalConflicts.php
apps/api/app/Services/JournalIngestion/ExistingJournalCandidates.php
apps/api/app/Services/JournalIngestion/IngestJournalRecords.php
apps/api/app/Services/JournalIngestion/ProcessIngestionItem.php
apps/api/app/Services/JournalIngestion/ProposalValidator.php
apps/api/app/Services/JournalIngestion/ProviderRegistry.php
apps/api/app/Services/JournalIngestion/Providers/Crossref/CrossrefCandidates.php
apps/api/app/Services/JournalIngestion/Providers/OpenAlex/OpenAlexClient.php
apps/api/app/Services/JournalIngestion/Providers/OpenAlex/OpenAlexNormalizer.php
apps/api/app/Services/JournalIngestion/Providers/OpenAlex/OpenAlexProvider.php
apps/api/app/Services/JournalIngestion/Providers/OpenAlex/OpenAlexResponse.php
apps/api/app/Services/JournalIngestion/Providers/OpenAlex/OpenAlexTaxonomy.php
apps/api/config/journal_ingestion.php
apps/api/database/migrations/2026_10_12_090000_add_provider_taxonomy_to_subjects.php
apps/api/tests/Feature/JournalCatalogTest.php
apps/api/tests/Feature/JournalIngestionTest.php
apps/api/tests/Feature/OpenAlexProviderTest.php
apps/api/tests/Fixtures/ingestion/openalex/README.md
apps/api/tests/Fixtures/ingestion/openalex/source.json
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/data/JOURNAL-SCHEMA.md
docs/development/JOURNAL-INGESTION-PLAN.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/OPENALEX-ADAPTER-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/product/ROADMAP.md
```

## Final Source-ID correction pass — 2026-10-12

The final read-only review found a prior-Source-linkage guard that accepted a staged-only different ISSN: previous journal A plus zero canonical owners produced a one-element union, then processing could create B for the same Source ID. This pass replaces that union with existing exact identity resolution over the proposal identifiers actually retained. Prior linkage requires exactly one matching resolved journal; null/different resolution quarantines safe original evidence with openalex_identifier_conflict. No Source-based merge, ISSN transfer, schema/taxonomy redesign or shared resolver modification.

Two PostgreSQL regressions cover dry-run and real staging/processing: independent staged B, plus staged B whose candidate includes an owned sibling omitted from the response. Both retain raw observation/conflict diagnostics, leave A and its identifiers unchanged, leave B unmaterialized and attach no claims/journal to the quarantined item. Dry-run checks all-table row-count invariance. Existing same-journal replay and canonical-owner conflict coverage remain.

Additional real-pipeline tests move the same Topic ID from subfield 1702 to 1703 while preserving the old parent/evidence/version, remove topics without deleting old Subjects/claims/reviews, and check configured-key 401/403: exactly one authenticated request, no anonymous fallback or sleep, fixed sanitized failure and no ingestion writes.

Failures encountered: the new data-provider cases initially used named inner arguments that did not match the test parameter (two PHPUnit errors); corrected case labels to outer dataset keys and reran the affected tests. Then both new cases reproduced the actual bug (valid instead of quarantined); after the normalizer fix the focused identity tests passed (3 tests / 45 assertions). Additional regressions passed (4 tests / 36 assertions). No tests disabled/deleted/skipped; no live provider called again. Final complete counts follow below.

### Final correction-pass verification

These counts supersede the earlier implementation totals. PostgreSQL 17 / isolated fikarohana_test only; no SQLite, dependency installation or repeated live request.

| Gate | Result |
| --- | --- |
| Focused Source-ID conflicts | 3 tests / 45 assertions passed |
| Additional move/removal/configured-auth regressions | 4 tests / 36 assertions passed |
| OpenAlexProviderTest | **64 tests / 376 assertions passed**, 7.468 seconds |
| CrossrefProviderTest | **49 tests / 267 assertions passed**, 4.327 seconds |
| DOAJ API/CSV suites | **107 tests / 566 assertions passed**, 7.014 seconds |
| Complete Laravel suite | **326 tests / 1989 assertions passed**, 31.716 seconds |
| Pint --test | Passed |
| Composer validate/audit | Valid; zero advisories/abandoned packages |
| OpenAPI validate/contracts check | Valid; generated contracts reproducible/current |
| git diff --check | Passed |
| Markdown local links | **136 targets, zero missing** |

Exactly four files changed during this correction: OpenAlexNormalizer.php, OpenAlexProviderTest.php, this report and TODO.md. Existing taxonomy classes/migration and shared identity resolver are byte-for-byte unchanged from the start of this pass. Real .env and HEAD 701e9a4 are unchanged; all Phase 3D work remains uncommitted on master, with no branch or push. No new live OpenAlex/DOAJ/Crossref call. Final correction verdict: **PASS**, no remaining identity blocker.
