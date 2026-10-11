# Phase 3B — DOAJ provider adapter V1

Status: bounded operator-selected DOAJ API v4 and official Journal CSV ingestion implemented, 2026-10-11. It feeds [Phase 3A](JOURNAL-INGESTION-V1.md); it is not a full-catalog synchronizer or a public journal API. Live API mapping remains unverified because its small network probe failed before any HTTP response. The CSV extension and its separate live result are recorded at the end of this report. Tests are deterministic, offline and PostgreSQL-only.

## Official sources verified

Access date: **2026-10-11**. Only DOAJ-owned documentation/source material was used:

- [Current API documentation](https://doaj.org/api/v4/docs) and [current alias](https://doaj.org/api/docs): direct access returned HTTP 403 through the browsing tool. This limitation is recorded rather than claiming the live Swagger was read.
- [Public outgoing journal schema](https://doaj.github.io/doaj-docs/master/data_models/OutgoingAPIJournal.html): public fields, publication-language meaning, publisher country, APC flags/maxima and provider timestamps.
- [Official v4 routes](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/view/api_v4.py), [discovery/pagination implementation](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/api/current/discovery.py), [public outgoing model](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/api/current/data_objects/journal.py) and [shared fields](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/models/v2/shared_structs.py).
- [Official API FAQ source](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/templates-v2/public/api/v4/includes/_extra_docs.html): two requests/second across API routes, bursts described, query constraints and operational guidance.
- [Official settings](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/settings.py): default page ceiling 100 and deep-paging offset ceiling 1000.
- [Terms and conditions](https://doaj.org/terms/), version 1.6 dated 2025-12-08: journal metadata is made available under CC0. This does not license arbitrary linked articles, templates, website content or DOAJ branding. Fikarohana retains source attribution regardless of the waiver. Production retention, field review and redistribution presentation remain operational decisions.

The official v4 route module identifies **4.0.0**; repository settings identify **4.0.1**. The adapter targets major **v4**, without asserting which patch is deployed. Official base is `https://doaj.org/api/`; the configured version-pinned root is `https://doaj.org/api/v4`. Public GET journal search and GET journal detail require no API key. Authenticated write/application APIs are outside this implementation, so no credential variable was added.

Capabilities: `GET /search/journals/{query}` with `page`, `pageSize`, `sort`; `GET /journals/{id}` is available for detail, but the client only implements search. Search supports field queries such as `issn:1932-6203`. No journal list, dump, scroll or article client is introduced. The default bounded sample query is `_exists_:bibjson.title`.

A checked-in legacy discovery Swagger example still describes older identifier arrays/string publishers/APC forms; the public outgoing schema and actual v4 mapping source expose `pissn`, `eissn`, object publishers and `apc.has_apc`/`apc.max`. V1 explicitly targets the latter. Old shapes are quarantined, never guessed into compatibility.

## Components and boundaries

`Providers/Doaj/DoajClient` owns native Laravel HTTP transport and bounded search pagination. `DoajResponse` validates JSON/page envelopes. `DoajProvider` implements the existing transport-free provider contract, translates raw records to Phase 3A envelopes, validates and maps proposals, and supplies raw field locators. The shared registry requires explicit `doaj` selection; synthetic remains the default.

The existing command obtains provider records before opening any staging/source transaction. Every observation goes through IngestJournalRecords, existing validation/ISSN resolution, ProcessIngestionItem, append-only Phase 2 assertions/reviews and PublishJournalRevision. Provider-specific code performs no catalog writes. Shared materialization adds publisher, language and qualified subject proposals using existing models, without a migration, duplicate evidence layer or new business API.

## Exact field mapping

| DOAJ public field | Fikarohana mapping / policy |
| --- | --- |
| id | external_id; required 32-character lowercase hexadecimal DOAJ ID |
| id | Constructed immutable source URL `https://doaj.org/api/v4/journals/{id}`; never fetched by mapping |
| Entire public record | Original bounded staging payload; provider dates and unmapped values retained |
| bibjson.title | Trimmed title assertion; original wording and `bibjson.title` locator retained |
| bibjson.pissn / eissn | Print/electronic identifier evidence, checksum normalization; original values retained |
| bibjson.publisher.name | Publisher assertion referencing a working normalized-name/country publisher identity; journal ownership is not reassigned |
| bibjson.publisher.country | Uppercase country on that publisher identity; never relabelled as journal country |
| bibjson.language[] | Explicit accepted-manuscript language codes, supported=true typed links and individual pending assertions; unlisted languages remain unknown |
| bibjson.subject[].scheme/code/term | Qualified flat subject name `DOAJ {scheme}: {code} — {term}`, typed membership and individual pending assertions; no cross-taxonomy equivalence/hierarchy or editorial scope inferred |
| bibjson.ref.journal | Website assertion, normalized URL; not a submission URL |
| bibjson.apc.has_apc=true and exactly one valid max[] | Known exact integer amount/currency; basis `doaj_reported_maximum`; explicitly unspecified article type/tax/waiver applicability |
| bibjson.apc.has_apc=false | Explicit no-APC evidence: basis `doaj_reported_no_apc`, explanatory applicability, unknown scalar amount/currency; never not_applicable or an invented currency/zero; other charges remain independent |
| Missing APC/flag, missing maximum, multiple currency maxima | Unknown scalar APC, null amount/currency; full original maxima remain staged; no selection/conversion of a currency |
| created_date / last_updated / last_manual_update | Provider revision metadata retained in payload/fingerprint; never used as retrieved_at or verified_at |
| admin.in_doaj | When explicitly false or malformed, quarantine; true is not a verification decision or universal trust score |
| boai, oa_start, licenses, other_charges, waiver, directory review metadata | Preserved source-attributed OA context only; current canonical schema has no clean equivalent for these full semantics |

V1 requires valid title and at least one ISSN/eISSN. Optional absent values create no invented claims. Raw language values must be lowercase 2–3 letter codes; names/unsupported representations are quarantined pending an approved vocabulary mapping. Publisher country validates syntax, not an ISO registry lookup. Qualified subjects preserve scheme/code/term identity without claiming normalized external-taxonomy integration. Changing a term creates a new vocabulary/evidence proposal.

Nested U+0000 is rejected before JSONB with hash-only quarantine. Structurally safe semantic failures retain raw observations and are quarantined by Phase 3A. Current policy (final URL correction): safe raw optional URL evidence is retained. Valid HTTP(S) metadata may contain query/fragment/percent encoding/path parameters; malformed, credential-bearing or control-bearing optional journal websites are omitted from mapping, not fetched. Raw U+0000/invalid UTF-8 still quarantines the record. Unmapped links remain untrusted source evidence, not authorized fetch targets. Unexpected private admin fields are also rejected. Invalid JSON/UTF-8 and invalid page envelopes fail the transport operation safely; they are not fabricated into per-record evidence.

## Provenance, reviews and idempotency

JournalSource uses type `doaj`, name `DOAJ`, base URL `https://doaj.org`, without credentials. Phase 3A's immutable source/provider binding and source observation unique key remain authoritative. Source URL plus original record content is fingerprinted; DOAJ dates participate in content identity. Current mapping version is `doaj-v4-csv-v3` and is separate from provider revision and retrieval time.

Run input identity now excludes envelope retrieved_at for all adapters, while retaining provider/mapping and ordered original content. An identical retry at a later fetch time reuses the run under the same key; first observation retrieval is preserved. Changed content under the same run key is rejected. A new caller key with changed payload creates a new auditable observation/assertions; no earlier claim/review is overwritten. Distinct sources/providers can reuse external IDs. ISSN collisions quarantine and title similarity never merges journals.

Assertions are field-level, with raw provider locators. Import creates pending evidence or conflicting reviews after comparison; it never creates verified evidence. Publisher changes and explicit contradictory language evidence enter conflict handling. Missing languages/categories are not deletions. Review and publication use the existing explicit operator flow and immutable revisions. Stage does not materialize canonical facts; process does not publish; dry-run creates no source, binding, run, item, assertion or revision.

DOAJ is suitable for attributed identity, descriptive, accepted-language, subject, directory/OA and explicitly contextual APC evidence. It is not the authority for complete author guidelines, word limits, reference styles, templates, detailed submission rules or review timelines. Neither DOAJ inclusion nor APC absence proves Scopus/Web of Science inclusion, journal trust or acceptance likelihood. Future official-source evidence must coexist and be reviewed for conflicts.

## HTTP configuration and failure policy

Safe defaults in `.env.example` and `config/journal_ingestion.php`:

| Environment variable | Default / permitted bound |
| --- | --- |
| DOAJ_API_BASE_URL | `https://doaj.org/api/v4`; only this or official `/api` alias allowed |
| DOAJ_API_TIMEOUT | 20 seconds, 1–60 |
| DOAJ_API_CONNECT_TIMEOUT | 5 seconds, 1–30 |
| DOAJ_API_ATTEMPTS | 3 attempts total, 1–3 |
| DOAJ_API_DELAY_MS | 1000 ms between pages/retries, 500–5000 |
| DOAJ_API_PAGE_SIZE | 10, 1–100; actual fixed page size may be smaller to divide limit |
| DOAJ_API_MAX_RESPONSE_BYTES | 1048576, 1024–1048576 |
| DOAJ_API_USER_AGENT | `Fikarohana/0.1 (controlled journal ingestion)`, bounded printable ASCII |

Only HTTPS on exact official roots is allowed; redirects are disabled. Provider `next`/journal/guideline/APC URLs never become request destinations. TLS verification remains enabled. HTTP has no generic URL fetcher, cookie/authentication mechanism or logging of unexpected bodies/exception messages. DNS resolution relies on the official hostname and ordinary trusted host/TLS/network configuration, not a general approved-journal crawler.

Connection/timeouts, 429 and 500/502/503/504 may retry. Other HTTP statuses, invalid MIME/JSON/schema and oversized responses do not retry. Retry-After numeric seconds or HTTP dates are respected within a five-second bound; longer/malformed instructions defer with nonzero failure rather than retry early. Default retries and page delay stay below the documented two requests/second in a single process; no cross-process quota coordinator exists. Concurrent operators must coordinate separately.

Content-Length and download progress guards bound declared/transferred bodies; a post-download size check also handles Laravel fakes and decompressed content. This is size awareness, not a proof against every compressed-body memory spike. JSON depth is 32. Existing per-payload/batch limits still apply after retrieval. Fixed DomainException codes are exposed internally; CLI failures retain the generic sanitized Phase 3A message and never print raw bodies/SQL/traces.

## Pagination and CLI

Search pages are 1-based. Response page/pageSize/total/results are validated. To preserve offsets and avoid unnecessary records, the fixed page size is the largest divisor of limit no greater than configured page_size. Example: limit 10/default page_size 10 → one page of 10; limit 3/page_size 2 → three pages of 1. This can cost more requests for prime limits, but retrieves at most the requested records and never more than 100. Server pagination URLs are ignored. Search uses `sort=id:asc`; pages are not an immutable upstream snapshot. No full sync, checkpoint cursor, withdrawal detection or deep paging exists. Any page failure aborts retrieval before database writes, even if earlier pages were received.

Use the **existing** command from apps/api:

```bash
php artisan journals:ingestion stage --provider=doaj --key=pilot-001 --limit=10 --dry-run
php artisan journals:ingestion stage --provider=doaj --key=pilot-001 --query='issn:1932-6203' --limit=1 --dry-run
php artisan journals:ingestion source --provider=doaj
php artisan journals:ingestion stage --provider=doaj --key=pilot-001 --limit=10
php artisan journals:ingestion process RUN_UUID
php artisan journals:ingestion status RUN_UUID
```

A source is reused/created only after successful nonempty retrieval during persisted stage; dry-run can use an unsaved source descriptor. `--source` can explicitly select an existing DOAJ source. A valid caller `--key` is required before network access; `--file` is synthetic-only. Empty search returns successful zero-record output without a run/source. Synthetic file commands remain supported and never retrieve DOAJ implicitly. Limits default to 100, validate 1–100 before requests, and apply to dry-run as well. Phase 3A counters/exit codes remain: 0 technical success including quarantined records; 1 operation/network failure; 2 invalid limit. Explicit review/publish commands are unchanged.

## Fixtures and executed verification

`tests/Fixtures/ingestion/doaj/journal.json` is **synthetic**, based on the current official outgoing schema, with reserved example domains and invented names/ID; it is not a recorded real journal or verified scientific fact. Fixture provenance and SHA-256 are in the adjacent README. Test variations supply malformed/missing/APC/identity/conflict cases. No recorded live fixture is claimed: the authorized small live fetch could not obtain a response. A permitted recorded fixture should be added when official access succeeds.

DoajProviderTest uses Http::fake/preventStrayRequests and fake sleeps. It covers mapping and raw locators, print/electronic/both identifiers, accepted languages/subjects, publisher country, APC missing/no-charge/zero/paid/multiple currencies, malformed/NUL neighbors, idempotent later-time retries and changed observations, identifier collisions/title separation/provider scope, pending→explicit review→existing publication, pagination/limit/dry-run, timeouts, bounded 429/5xx retries, no-retry failures, Retry-After, invalid JSON/MIME/page/size and official-origin restrictions. Transport runs outside additional database transactions.

Final targeted DOAJ run: **56 tests passed, 252 assertions, 4.840 seconds** (`php artisan test --filter=DoajProviderTest`). Final complete Laravel run: **162 tests passed, 1034 assertions, 22.443 seconds** (`php artisan test`), including the existing 106 Foundation/catalog/framework tests. PostgreSQL 17, isolated `fikarohana_test` at 127.0.0.1:5434, host PHP/pdo_pgsql, RefreshDatabase; no failures/skips or SQLite fallback. No development/production migration or real catalog writes.

Quality gates passed: `vendor/bin/pint --test`, `composer validate --no-check-publish`, `composer audit --format=json` (zero advisories/abandoned packages), root `npm run contracts:check`, `git diff --check` and Markdown target validation (112 local targets, zero missing). Contracts remain reproducible/current; public routes/contracts are unchanged. No dependencies were installed or changed.

## Small live verification

2026-10-11, one explicitly requested result, one attempt, no persistence/publication:

`GET https://doaj.org/api/v4/search/journals/issn%3A1932-6203?page=1&pageSize=1&sort=id%3Aasc`

Native Laravel client result: `doaj_connection_failed`; **no HTTP response**, **0 records observed**, **0 writes**. Mapping and deployed schema compatibility remain **unverified**; no schema mismatch can be determined from this failure. The separate browsing-tool API-doc requests returned 403. No challenge bypass, alternate proxy, credential creation or mass import was attempted. This optional live failure does not make deterministic PHPUnit depend on the network.

## Limitations and remaining work

No full DOAJ catalog coverage/production seed is claimed. Real access/deployed patch/schema, recorded fixture, production review owners/freshness/retention, approved vocabularies and publisher disambiguation need validation. The small normalized publisher-name/country identity is not a global publisher identifier. APC range/multiple-currency/OA waiver/tax semantics remain raw context. No generalized OA assertion model, taxonomy reconciliation or automatic indexation fact was introduced. Abrupt crashes/unavailable DB retain the Phase 3A recovery limitations.

Stop after 3B. Crossref, OpenAlex, HTML fetching/scraping, public journal routes, discovery/recommendations, projects/workspace, assistant/RAG/embeddings, Redis, FastAPI, billing and frontend work remain outside this phase. No dependency, branch or commit was created.

## Exact Phase 3B files changed

29 files changed/created during this phase. Previously staged Phase 3A changes are preserved; this inventory lists the Phase 3B additions and edits relative to that starting state.

```text
README.md
apps/api/.env.example
apps/api/app/Console/Commands/JournalIngestion.php
apps/api/app/Services/JournalIngestion/DetectJournalConflicts.php
apps/api/app/Services/JournalIngestion/IngestJournalRecords.php
apps/api/app/Services/JournalIngestion/ProcessIngestionItem.php
apps/api/app/Services/JournalIngestion/ProposalValidator.php
apps/api/app/Services/JournalIngestion/ProviderAdapter.php
apps/api/app/Services/JournalIngestion/ProviderRegistry.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajClient.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajProvider.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajResponse.php
apps/api/app/Services/JournalIngestion/SyntheticProvider.php
apps/api/config/journal_ingestion.php
apps/api/tests/Feature/DoajProviderTest.php
apps/api/tests/Feature/JournalIngestionTest.php
apps/api/tests/Fixtures/ingestion/doaj/README.md
apps/api/tests/Fixtures/ingestion/doaj/journal.json
docs/api/API.md
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/development/DOAJ-ADAPTER-V1.md
docs/development/JOURNAL-INGESTION-PLAN.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/product/ROADMAP.md
```

## Official Journal CSV extension — 2026-10-11

This section supersedes the API-only transport description and earlier mapper/test counts above. API transport remains the default when `--transport` is omitted; `--transport=csv` explicitly selects the official public download. Synthetic remains the default provider. No full-catalog synchronization is implemented.

### Verified official sources

Accessed **2026-10-11**:

- [Journal CSV documentation](https://doaj.org/docs/journal-csv): stable public `https://doaj.org/csv`, all public journals, freshness within 30 days; authenticated premium freshness within one hour is outside scope. Documentation displayed 24.45 MB and 2026-09-01; these are descriptive, not guaranteed constants.
- Official live public source: HTTP 307 to `doaj-live-journal-csv.s3.amazonaws.com` and the export filename pattern `doaj_journalcsv_YYYYMMDD_HHMM_utf8.csv`. Observed export filename dated 2026-09-10 23:20; response size 25,702,837 bytes and MIME `binary/octet-stream`. The signed download location is temporary and never stored in fixtures, config, logs or provenance.
- [Official export service](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/bll/services/export.py), [question crosswalk](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/crosswalks/journal_questions.py), [datasets](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/datasets.py), [LCC vocabulary](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/lccSubjects.xml) and [CSV route](https://github.com/DOAJ/doaj/blob/62dd0214cbdcb89cd41a075f840855f4c05d3c1b/portality/view/doaj.py): UTF-8/comma CSV, question-labelled headers, human-readable country/language names, Yes/No/blank flags, `amount CURRENCY` maxima separated by `; `, selected subject paths separated by ` | ` and ancestor-inclusive LCC Codes separated by `|`.
- [Terms](https://doaj.org/terms/): journal metadata CC0; other linked content/branding is not licensed by this metadata waiver.
- [Official v4 cutover history](https://blog.doaj.org/2024/07/16/were-cutting-over-to-the-new-api/): announced July 18, 2024 cutover, `/api` and `/api/v4` aliases. Supporting historical context only; technical schema/source remains canonical for mappings.
- [Official fee clarification](https://blog.doaj.org/2024/11/29/more-transparent-fee-information/): no APC and no other author charges are separate facts; waiver does not mean universal free publication.

Observed current header has these **52 columns**, in order:

```text
Journal title
Journal URL
URL in DOAJ
When did the journal start to publish all content using an open license?
Alternative title
Journal ISSN (print version)
Journal EISSN (online version)
Keywords
Languages in which the journal accepts manuscripts
Publisher
Country of publisher
Other organisation
Country of other organisation
Journal license
License attributes
URL for license terms
Machine-readable CC licensing information embedded or displayed in articles
Author holds copyright without restrictions
Copyright information URL
Review process
Review process information URL
Journal plagiarism screening policy
URL for journal's aims & scope
URL for the Editorial Board page
URL for journal's instructions for authors
Average number of weeks between article submission and publication
APC
APC information URL
APC amount
Journal waiver policy (for developing country authors etc)
Waiver policy information URL
Has other fees
Other fees information URL
Preservation Services
Preservation Service: national library
Preservation information URL
Deposit policy directory
URL for deposit policy
Persistent article identifiers
Does the journal comply to DOAJ's definition of open access?
Continues
Continued By
LCC Codes
Subscribe to Open
Mirror Journal
Open Journals Collective
Subjects
Added on Date
Last updated Date
Last Full Review Date
Number of Article Records
Most Recent Article Added
```

### Shared pipeline and strategy

`DoajCsvClient` acquires into a temporary bounded stream; `DoajCsvParser` selects at most the requested prefix; `DoajCsvNormalizer` converts rows into the same bibjson representation used by the existing `DoajProvider`. Both transports feed existing staging, validation, ISSN ownership, assertions, conflicts, review and immutable revisions. No migration, dependency or public route was added. The transport-free source-locator method now optionally accepts the retained payload to point to API fields or CSV columns.

CSV serves catalog bootstrap/bounded batches; API serves targeted lookup/refresh. CSV is not an API-query engine: a non-default `--query` is rejected before network access. Default limit is 100, valid range 1–100, including malformed rows in the selected prefix; no scanning ahead to find 100 valid records.

```bash
php artisan journals:ingestion stage --provider=doaj --transport=csv --key=doaj-csv-pilot-001 --limit=10 --dry-run
php artisan journals:ingestion stage --provider=doaj --transport=api --key=doaj-api-pilot-001 --query='issn:1932-6203' --limit=1 --dry-run
```

### Safety and normalization

CSV defaults: `DOAJ_CSV_TIMEOUT=60` (1–120 seconds), `DOAJ_CSV_CONNECT_TIMEOUT=5` (1–30), `DOAJ_CSV_MAX_DOWNLOAD_BYTES=33554432` (1024–33554432). One attempt per request, at most one redirect; TLS stays enabled. No caller-configurable source URL, generic fetcher, field URL retrieval, premium credentials or automatic retry is added. The exact observed S3 hostname and filename pattern are required; other redirects fail. Accepts CSV/plain/octet-stream response MIME including observed `binary/octet-stream`; HTML is rejected. Identity encoding avoids compressed-body expansion; declared and actual streamed sizes are checked. Temporary file is deleted on success/failure. Download/read deadlines and connection timeouts are explicit.

Headers require title and at least one identifier column, unique names, safe UTF-8/text, valid quoting, at most 100 columns and 60,000 bytes. Optional missing columns/values create no inferred claims. Unknown optional columns stay in the bounded raw row. RFC-style quoting is checked in addition to native `fgetcsv` with an explicitly empty escape parameter. Formula-looking cells remain inert strings, never executed or evaluated; this does not provide a future spreadsheet export sanitization feature.

Invalid UTF-8/U+0000 or malformed/oversized rows produce hash-only quarantine with sanitized codes. A missing closing quote can consume the remaining file; earlier valid records survive, and the remaining bytes form one quarantined record. No guessed recovery boundary is invented. Parsing only validates the selected prefix; unselected rows are not ingested or claimed valid. Whole download remains bounded by 32 MiB.

Exact country/language names map through the checked-in static ISO vocabulary, following DOAJ's documented pycountry export approach. Unknown names quarantine rather than fuzzy-map. LCC Codes include ancestors; selected subject-path leaf terms are matched uniquely against observed codes and pinned DOAJ LCC vocabulary, not zipped positionally. Ambiguous/changed taxonomies quarantine. Vocabulary license/source attribution is adjacent to the JSON; no runtime download or new package is required.

### Corrected APC semantics

The earlier `has_apc=false → not_applicable` mapping is superseded. False/CSV No explicitly states no APC charged. Existing canonical known money requires a currency, so no currency/zero is invented: the scalar availability is `unknown`, amount/currency null, and **basis `doaj_reported_no_apc` plus explicit applicability and the raw flag distinguish evidenced absence from unknown APC**. This does not imply no other fees. Positive/zero amounts require an explicit currency; missing maximum and multiple currencies stay scalar unknown with raw maxima preserved. A waiver is independent retained metadata, never a zero-price inference. Not-applicable is never emitted by this DOAJ mapper.

Explicit no-APC claims participate in ingestion conflict detection with positive APC claims in both directions, despite unknown scalar monetary availability. Existing publication rules still block unknown monetary claims; publishing a structured currency-free fee-presence fact would require a separately approved model extension. No canonical data-model change was made. The CSV extension initially advanced the mapper to `doaj-v4-csv-v2`; the final URL correction advances it to `doaj-v4-csv-v3`; old items/evidence/reviews are not rewritten.

### Provenance and idempotency

Both transports add controlled `_acquisition` metadata: provider DOAJ, transport and stable official source. API preserves the original public fields; CSV preserves `_csv_row` plus exact row-byte SHA-256 `_csv_fingerprint`. Existing source record fingerprints hash stable URL and retained payload; mapper version remains separate, retrieval is first-observed, never verification. CSV external ID comes from an exact official DOAJ `/toc/{32hex}` reference where available; otherwise `csv.{sha256(observed identifiers)}` is explicitly local, not an invented upstream ID. Row references are never fetched. Field locators point to retained CSV columns.

Identical rows, later-time retries and new runs reuse observation/item/claims. Changed row bytes create a new auditable observation; the same key with changed input fails. API and CSV observations coexist and resolve the journal through existing checksum-valid ISSN/eISSN policy; canonical identifiers/evidence are not overwritten. Provider/source binding and uniqueness prevent collisions with another provider using the same external ID.

### Failures encountered during this extension

- Source research: guessed repository paths returned 404; Python direct official download returned 403; one GitHub request reset its connection. Used correct official repository paths and inspected the stable official download. These were research/network failures, not reasons to change application logic.
- Test command invoked from repository root could not find `vendor/bin/pint`/`artisan`; rerun from apps/api.
- Six API payload equality assertions initially included added `_acquisition` metadata. Corrected assertions compare preserved original provider content separately and verify transport metadata.
- Initial CSV suite: 22 errors and one command failure traced to an alternation regex embedded in Laravel's pipe-delimited rule string. Changed that rule to an explicit rule array; reran affected CSV/APC tests, then the complete suite. Test code also corrected its normalized-field accessor before the next run.
- First live dry-run returned generic failure. Sanitized diagnostic showed 307/200 and `doaj_csv_invalid_format`; actual official MIME is `binary/octet-stream`. Added this observed MIME explicitly, retained all content/size/origin guards, and reran the specific redirect/dry-run test before the full suite. This was a genuine transport format mismatch, not a timeout workaround.

Final counts and live result are recorded below. No test was skipped/deleted/disabled; PostgreSQL remained mandatory.

### Final deterministic verification

PostgreSQL-only complete Laravel suite: **202 tests, 1234 assertions, all passed** (`php artisan test`, final 32.791 seconds). Combined DOAJ API/CSV suites: **96 tests, 452 assertions, all passed** before the observed-MIME adjustment; the affected redirect/dry-run regression passed again afterward (**1 test, 12 assertions**), followed by the complete suite above. `vendor/bin/pint --test`, `composer validate --no-check-publish`, `git diff --check` and local Markdown link checks (111 targets, zero missing) passed. No dependency installation, test skipping, SQLite fallback or development database migration.

### Files changed in this CSV continuation

The earlier 29-file Phase 3B inventory is historical and includes API work. This continuation changes/adds these 24 files; existing staged Phase 3A changes remain staged as provided, without a commit:

```text
README.md
apps/api/.env.example
apps/api/app/Console/Commands/JournalIngestion.php
apps/api/app/Services/JournalIngestion/DetectJournalConflicts.php
apps/api/app/Services/JournalIngestion/ProcessIngestionItem.php
apps/api/app/Services/JournalIngestion/ProviderAdapter.php
apps/api/app/Services/JournalIngestion/SyntheticProvider.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajProvider.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajCsvClient.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajCsvParser.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajCsvNormalizer.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/csv-vocabulary.json
apps/api/app/Services/JournalIngestion/Providers/Doaj/CSV-VOCABULARY-LICENSE.txt
apps/api/config/journal_ingestion.php
apps/api/tests/Feature/DoajProviderTest.php
apps/api/tests/Feature/DoajCsvTest.php
apps/api/tests/Fixtures/ingestion/doaj/journals.csv
apps/api/tests/Fixtures/ingestion/doaj/README.md
docs/architecture/DECISIONS.md
docs/data/DATA-SOURCES.md
docs/development/DOAJ-ADAPTER-V1.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
```

### Historical pre-correction live CSV verification and risks

2026-10-11, actual existing Artisan command against the isolated PostgreSQL test database:

```bash
php artisan journals:ingestion stage --provider=doaj --transport=csv --key=doaj-csv-live-final-20261011 --limit=3 --dry-run
```

**Exit 0**, `dry_run=true`, exactly **3 items**, all `quarantined` with `invalid_record`; **zero source/run/evidence/journal/revision writes** by the existing dry-run path. The official CSV acquisition now succeeds; this is not a successful verified-journal import. A separate one-record, no-write structural diagnostic returned fixed `doaj_unsafe_url`: the first row contains URL query/fragment values in license, copyright, review, aims/scope, author-instruction, APC, other-fee and preservation columns. No raw URL or signed location was logged. The pre-existing conservative rule rejects such URLs anywhere in retained provider input; it was not relaxed to make live data pass. All three selected rows were quarantined; only the first row received the separate detailed structural diagnosis, so no identical cause is asserted for the other two.

Network timing varied across attempts. Earlier Python 403/reset and API connection failures remain recorded; final CSV network acquisition is **verified**, while accepted real-record mapping, broader coverage and a permitted recorded fixture remain **unverified**. Unknown vocabulary and ambiguous subject paths can reduce coverage. The query/fragment rejection described here was subsequently corrected in the final URL pass below; no bypass or automatic publication was added.

The 32 MiB limit bounds downloading the whole export even for limit 1; this implementation does not offer streaming catalog sync or resumable download. The observed S3 host/file pattern can change upstream; changes fail closed. Country/language/LCC snapshots require explicit version maintenance. Explicit no-APC evidence remains distinguishable but cannot publish as known money under the unchanged canonical model; richer fee-presence publication requires separate approval.

CSV continuation verdict: **PASS** for the requested bounded ingestion extension and deterministic tests. Production catalog activation/real-record coverage is not claimed. Stop after Phase 3B: no Crossref, OpenAlex, scraping, public journal API, recommendation, project/workspace, assistant/RAG/embeddings, Redis, FastAPI, frontend or billing was introduced. No branch, dependency installation or commit.

## Final URL correction pass — 2026-10-11

User-authorized correction supersedes the earlier all-quarantine live result. Root cause: recursive DOAJ URL validation and the shared proposal validator conflated safe metadata storage with network-fetch authorization. Legitimate query/fragment links were rejected before any journal mapping.

Current storage policy accepts syntactically valid HTTP(S) metadata URLs with query strings, fragments, percent encoding and normal path parameters. Normalization changes only scheme/hostname/outer whitespace in mapped values; original provider strings remain unchanged in raw staging and original wording. Control characters, invalid UTF-8, unsupported schemes, malformed URLs and embedded credentials are not valid mapped websites. Optional invalid DOAJ journal websites are omitted from the proposal/assertion while raw source evidence, identity/title/ISSNs and other supported fields survive. Raw recursive U+0000/invalid UTF-8 remains a record-level hash-only quarantine boundary; it is never stripped or retained in PostgreSQL text. Other structural/identity/domain failures retain existing quarantine behavior.

Reviewed URL fields: API `bibjson.ref.journal` and CSV `Journal URL` are the only journal website fields currently mapped. All other DOAJ URL fields (guidelines, APC, waiver, license, copyright, review, preservation, scope, board, deposit policy) remain unmapped raw evidence and are never followed. No submission/template/requirement claim is inferred from their existence. Required DOAJ IDs/title/ISSN validation is unchanged.

Source-envelope provenance remains separate: CSV is always `https://doaj.org/csv`; API constructs the stable official journal source URL. Journal website metadata can never replace it. The existing source-envelope no-query/no-fragment validation remains conservative, independently of website metadata acceptance. No S3 signed URL enters provenance.

No API/CSV client was changed in this pass: fixed official API roots, stable official CSV origin, exact approved S3 redirect destination/file pattern, one bounded redirect, timeouts, TLS and byte/record limits are unchanged. Storing a link grants no fetching authority, including a syntactically valid private-address URL. No general URL fetcher was introduced. Raw metadata can contain provider-supplied invalid/credential-bearing links; it remains restricted evidence and is never logged, followed or used as authentication. Future presentation/export requires its own escaping/redaction policy.

Mapper advances to `doaj-v4-csv-v3`, allowing new staging/mapping of existing immutable observations; existing quarantined items, history and first-retrieval timestamps are not rewritten. APC model/semantics remain unchanged: explicit absence uses `doaj_reported_no_apc` basis/context and unknown scalar money; waiver is independent; structured publication of currency-free absence remains a future model decision.

Regression cases run through both API and CSV plus the real Phase 3A processor: query, fragment, both, percent encoding/path parameters, javascript/file/data, malformed URL, credentials and controls. Original evidence and stable provenance are asserted; optional invalid websites produce no website claim. Http::preventStrayRequests/Http::assertNothingSent proves mapping/materialization performs no HTTP. A fake acquisition test asserts exactly one request to the stable official CSV source even with a journal URL containing query/fragment. Existing U+0000 tests still pass.

Failure encountered: the optional-invalid-URL neighbor test originally counted three run positions as if they were two unique items. The valid repeated row intentionally appears twice; pivot metadata makes SQL DISTINCT retain all three positions. Corrected the assertion to count unique item IDs without changing the input, coverage or production logic. The already-started combined DOAJ run repeated that same failure once; after the fix, the affected URL/malformed-row tests were rerun first, then complete DOAJ and Laravel suites. No tests disabled/skipped/deleted and no SQLite/dependency installation.

Final correction-pass tests: focused affected URL/malformed-row regressions **19 tests / 151 assertions**, complete DOAJ suites **107 tests / 566 assertions**, complete Laravel PostgreSQL suite **213 tests / 1348 assertions**, all passed. Final Laravel run: 36.636 seconds. Pint, Composer validation, whitespace and Markdown link checks (**111 local targets, zero missing**) passed.

Correction-pass files (separate from the prior API/CSV inventory):

```text
apps/api/app/Support/CatalogNormalizer.php
apps/api/app/Services/JournalIngestion/IngestJournalRecords.php
apps/api/app/Services/JournalIngestion/ProposalValidator.php
apps/api/app/Services/JournalIngestion/Providers/Doaj/DoajProvider.php
apps/api/tests/Feature/DoajProviderTest.php
apps/api/tests/Feature/DoajCsvTest.php
apps/api/tests/Feature/JournalIngestionTest.php
docs/architecture/DECISIONS.md
docs/data/DATA-SOURCES.md
docs/development/DOAJ-ADAPTER-V1.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
```

### Second live CSV verification after URL correction

Executed the existing Artisan stage command through Laravel's console kernel against the isolated PostgreSQL `fikarohana_test` database, with `--provider=doaj --transport=csv --key=doaj-url-correction-live-20261011 --limit=3 --dry-run`. In-memory instrumentation only captured the three selected records for sanitized diagnostics if needed; no fixture or application file was generated.

Exact result: **HTTP 307 then 200; exit 0; 3 records observed; 3 valid/mapped; 0 conflicting; 0 quarantined; 0 persisted/staged records; 0 publications**. All three identity previews were `new`. Row counts across **all 37 public tables** were identical before/after. Dry-run executes no source/run/evidence/journal/revision writes. No journal URL was fetched. No remaining schema mismatch was observed in this three-row sample; the sanitized diagnostic list was empty.

This verifies bounded real CSV acquisition and mapping for the selected sample, not full-catalog coverage, automatic verified data or a persisted seed. Wider vocabulary/taxonomy coverage and live API compatibility remain to validate. Optional invalid website omission is intentionally conservative; raw evidence must remain restricted and future presentation must escape it. Structured publication of reported no-APC still requires a separately approved later model decision.

Final URL correction verdict: **PASS**. No network-fetch security policy changed, no dependencies installed, no branch/commit created, and scope stops at Phase 3B.
