# Phase 3E-A — Official journal / publisher source foundation V1

Status: implemented internally in Laravel, 2026-10-12; uncommitted. Acquisition only: no semantic extraction, generated assertion, verification, journal/catalog publication or public endpoint. Existing Phase 2 evidence/revision and Phase 3A–3D provider behavior remain canonical. This is an operator-only page checker, with no crawler or frontend.

## Authority and classification

Applicable official journal/publisher pages generally precede aggregators for mutable editorial rules: languages, requirements, article types, reference styles, fees, templates and submission URLs. DOAJ remains attributed OA-directory/declared-fee evidence; Crossref bibliographic identity; OpenAlex derived discovery taxonomy. Existing SourcePrecedence remains review guidance, never a destructive winner selector. Conflicts/history remain intact.

**Source authority** concerns who states a rule and whether it applies to this journal/article/language/date. **Extraction confidence** concerns whether a proposed interpretation accurately represents that statement. Official ownership and a successful fetch establish neither interpretation accuracy nor verified requirements. Phase 3E-A extracts no such rules. Missing information remains unknown; no LLM fills gaps.

Retain existing JournalSourceType official_journal / official_publisher. Separate official_purpose is website, guidelines, submission, fees, template, article_types or editorial. This avoids multiplying source authority enums for page roles. Publisher-wide pages need journal-specific applicability review; one registered page belongs to one explicit journal. Existing official labels without the new journal-bound approval fields are not automatically fetchable.

## Registry and approval

Extend existing journal_sources, without a parallel provenance system: official_journal_id (RESTRICT FK), official_url, official_origin, official_purpose, approval_status, first_observed_at, approved_at/by/basis and operational check metadata. Unique journal + canonical URL makes registration idempotent; conflicting type/purpose fails. Source identity, journal association and first observation cannot change after registration, even before acquisition. Generic model fill/create/update cannot set approval_status, approved_by, approval_basis or approved_at; the registration service assigns candidate explicitly and only controlled approve/deactivate use internal forceFill. Registered identity changes are rejected at model and SQL layers; a new URL needs a new candidate and its own approval. Existing non-official source immutability is preserved.

Workflow: candidate registration → explicit operator approval → controlled fetch → immutable observation → later proposed assertions → separate review → existing revision publication. Approval records a non-sensitive operator and ownership/applicability/access/robots/retention review reference. No title similarity, aggregator-returned domain or LLM can approve a source. Approval checks current DNS but makes no page request. Approved sources can be deactivated; their original approval cannot be edited/reset. Reactivation/replacement governance remains future work. Trusted host/CLI access is the operator authorization boundary; no public roles/admin API.

## Fetch URL trust and SSRF

OfficialUrl is distinct from CatalogNormalizer. Stored provider/journal links remain untrusted metadata and may retain valid HTTP(S) queries/fragments; they never become approved fetch destinations. The new fetch subset requires HTTPS/443, ASCII fully qualified hostname, no userinfo/control characters/backslashes, IP literals, local/reserved names, query or fragment. IDNs and query-dependent official pages need a later separately reviewed extension; metadata acceptance is unchanged.

PublicDns validates every A/AAAA answer, rejecting mixed public/private answers, empty or over-32 answer sets. It denies loopback, RFC1918, link-local/metadata, shared carrier space, reserved/documentation/benchmark/multicast IPv4 and non-global IPv6, mapped IPv4, NAT64, 6to4/Teredo and designated special-use IPv6. Native DNS runs in a shell-free argv subprocess with a five-second timeout, using Laravel Process / its existing Symfony dependency. No dependency added, executable/path/command selected from page input, or resolver output logged.

OfficialSourceClient accepts only a persisted approved source, not an arbitrary URL. It reloads approval/identity after each cadence wait and before every GET, revalidates DNS on every hop, and pins cURL to one validated address through CURLOPT_RESOLVE while retaining hostname TLS/SNI verification. A dedicated CurlHandler prevents a stream-handler fallback bypass. Proxy/auth are disabled through supported Guzzle options; netrc remains at libcurl’s default ignored state ([netrc default](https://curl.se/libcurl/c/CURLOPT_NETRC.html)), connections are fresh and not reused, TLS verification stays enabled. See [libcurl resolve semantics](https://curl.se/libcurl/c/CURLOPT_RESOLVE.html), [Guzzle request controls](https://docs.guzzlephp.org/en/stable/request-options.html) and [OWASP SSRF guidance](https://cheatsheetseries.owasp.org/cheatsheets/Server_Side_Request_Forgery_Prevention_Cheat_Sheet.html), reviewed 2026-10-12.

Automatic redirects are disabled. At most two same-origin redirects are followed manually; each URL and DNS answer is checked again, loops fail. Cross-origin redirects fail even when public: register/approve the new page separately. No provider-returned or discovered link becomes permission to fetch.

GET only, one explicit page operation, 15-second request / five-second connect timeout per hop, 30-second delay before each request (including redirects), descriptive User-Agent, no automatic retries or recursive/link/asset acquisition. 403/404/429/transient 5xx fail safely; retries require a separate explicit operator check. Approval must review any longer site-specific delay; defer approval if it cannot be honored by the operation. The delay is per outbound request in one source operation. A session-level PostgreSQL lock serializes fetches for the same source UUID, but different source URLs/UUIDs on one origin can run concurrently. It is not a persisted per-origin quota or distributed rate limiter. Coordinate operators per origin; no Redis/distributed quota scheduler. Session PostgreSQL advisory lock serializes each source across workers without holding a transaction during DNS/HTTP; process exit releases it. Persisted writes are in a short transaction which reloads approval under a row lock. Revocation during acquisition prevents evidence persistence.

## Body and snapshot policy

HTML or plain UTF-8/ASCII text only. Content-Length, transfer progress and post-download byte checks enforce 262,144-byte retained-body ceiling. Accept-Encoding identity, decompression disabled and compressed responses rejected; no PDF/DOCX/template downloads. Transfer callbacks may observe a final libcurl chunk past the threshold before abort; the stored body ceiling is strict. Unsupported status/MIME/encoding is rejected from headers where native HTTP permits. Fakes also pass final guards. A bounded temporary stream closes on all outcomes. No oversized/error body is persisted or logged.

Invalid UTF-8 and U+0000 fail without stripping original text. Minimal HTML parse uses LIBXML_NONET without entity expansion/DTD loading; recoverable HTML syntax is allowed, plain garbage with HTML MIME fails. It produces no visible-text/title/link interpretation. Scripts and links remain inert raw evidence; no JS, browser, form, cookie session, external-resource load or execution.

Existing source_records gains nullable raw_content text and snapshot_metadata JSONB, only for approved official snapshots. PostgreSQL enforces body/metadata ceilings, source/canonical URL attribution, successful status/MIME/parser version, byte count, deterministic SHA-256 hashes and journal/source association. Existing immutability triggers prevent updates/deletes. RESTRICT FKs and composite current-record/source FK preserve evidence attribution. Future assertions cannot use a registered official page belonging to another journal. Other-provider records leave these additions null.

Snapshot stores requested/final validated URLs, HTTP 200, MIME, body bytes, first retrieved_at, content hash, revision fingerprint, existing source identity, optional bounded ETag/Last-Modified and parser version official-page-raw-v1. Whitelisted response metadata only; no Set-Cookie, authentication, raw headers or tracking headers. raw_content / snapshot_metadata are hidden from default model serialization. No endpoint or HTML preview exists. Internal tools must never render raw HTML directly; public profiles later link to the official source and expose only reviewed permitted facts/passages.

Raw public pages may themselves contain volatile markup/identifiers. No stripping/canonicalization guesses modify retained evidence. Operators must approve public unauthenticated pages and retention rights; never pass secrets, researcher input, login/session pages or signed links. Larger permitted artifacts should later move to private object storage with a reviewed lifecycle; no S3/R2 implementation here.

## Change detection, conditionals and freshness

External key is SHA-256 of registered URL, within existing source namespace. Content hash is SHA-256 of original UTF-8 bytes; revision fingerprint hashes canonical requested URL + MIME + raw content. Identical rechecks reuse evidence and first retrieval; changed content creates a new record. Reverting to a previously seen body reuses that historical record and advances operational last_changed_at because the current content changed. Volatile timestamps, random IDs, tokens or whitespace can create extra versions: conservative hashing is intentional, no aggressive semantic canonicalizer.

Snapshot retains first acquisition metadata; later same-content checks update only operational metadata, including current final URL and validators. ETag/Last-Modified are ASCII/control-free and at most 512 bytes. Conditional headers are sent only at the URL from which those validators were observed, including after same-origin redirects. 304 requires existing evidence and an actual conditional request, updates last_checked_at/outcome, and creates no fake snapshot. It does not refresh retrieved_at or verify any claim. No response body or new fact follows from 304.

journal_sources last_checked_at, last_changed_at, last_outcome, last_final_url, last validators and current source_record provide freshness inputs. They represent observation/change, not verified_at, guaranteed currency or an arbitrary global 30-day truth. Latest diagnostics are deliberately operational, not a complete attempt/approval-transition event ledger. Immutable approvals and content observations remain retained; detailed attempt audit and source-specific refresh policy are production decisions.

Dry-run register/approve/deactivate/fetch writes zero rows or diagnostic changes. Fetch dry-run still requires a registered approved source and performs the controlled network request; approval dry-run only checks inputs/DNS. It returns sanitized metadata/fingerprint, never raw content/validators. Successful persisted fetch atomically writes/reuses the record and updates check metadata. Failed fetch intentionally updates only last_checked_at and fixed last_outcome; old evidence, last successful validators and publication history remain unchanged. A DB persistence failure rolls back the snapshot and operational updates; the CLI emits a generic code, and manual retry is safe.

## Robots, terms and licensing

No automatic robots.txt retrieval/parser: an extra derived URL fetch would enlarge this narrow operation and requires separately approved bounded policy. Operator approval must review current access/robots restrictions and site terms for the exact page/use, using a documented external/manual review reference. Do not bypass restrictions, challenges or disallowed access. robots alone is not legal permission. A user-authorized single transient live transport probe does not approve retention or redistribution rights.

Official page content is not assumed CC0 because an aggregator dataset is CC0. Retain bounded copies only for authorized internal verification, under restricted database/backup access; no copied full pages or copyrighted templates are published. Template links remain in raw untrusted page evidence only, never auto-downloaded. Retention deadlines, takedown/purge and larger private storage need human/legal/operations decisions before production. Existing append-only history does not grant unlimited copyright retention.

## CLI

From apps/api with an intentionally configured PostgreSQL database and migrated schema:

```bash
php artisan journals:sources register --journal=JOURNAL_UUID --type=official_journal --purpose=guidelines --url=https://journal.example.org/guidelines
php artisan journals:sources approve --source=SOURCE_UUID --actor=reviewer-001 --basis='Ownership/applicability/access/robots/retention review reference'
php artisan journals:sources fetch --source=SOURCE_UUID --dry-run
php artisan journals:sources fetch --source=SOURCE_UUID
php artisan journals:sources status --source=SOURCE_UUID
php artisan journals:sources deactivate --source=SOURCE_UUID
```

Example host/text are synthetic. No auto-discovery or batch/fetch-by-URL action. Optional --journal on source operations must match the registered journal. --url on fetch is rejected. Exit 0 success, 1 safe input/access/network/operation failure; Symfony option-parser errors are nonzero. Diagnostic codes/IDs only; no exception SQL/path/body/secret text. Use non-sensitive approval references, never actual credentials. Existing journals:ingestion adapters/commands are unchanged.

## Explicit security review — 2026-10-12

Implementation self-review performed against the actual code and deterministic tests; no independent third-party review is claimed.

| Boundary | Review evidence |
| --- | --- |
| Approval / arbitrary URL | Stored approved journal-bound source only; inactive/unapproved/legacy sources cannot fetch; ownership classification never auto-trusted |
| DNS/IP / rebinding | All-address validation; five-second DNS subprocess; curl address pin; fresh connections; explicit proxy/auth disable; netrc not enabled (default only); no stream fallback |
| Redirect | Exact approved origin, HTTPS/443, every target/DNS checked, two-hop cap, loop detection, no cross-origin permission inheritance |
| Time/body/parser | Fixed deadlines, identity transfer, three byte guards, bounded UTF-8 HTML/plain evidence, no entity/resource execution |
| Credentials / diagnostics | No auth/cookies/query or private research in requests; fixed errors and whitelisted metadata; no raw logs/header copies |
| Evidence / review | PostgreSQL immutability/attribution/RESTRICT; snapshot plus operational update atomic; no assertion/review/publication generation |
| Execution / scope | GET only, no browser/JS/linked resources/crawl; trusted operator CLI only |

Production still needs egress firewall restrictions, audited operator access, DNS/runtime/cURL provisioning, origin-wide coordination, licensed retention/purge and source-specific refresh/review ownership. Application checks do not replace those deployment controls. A forward migration is preferred over destructive rollback: populated registry/snapshot rollback refuses before dropping any history. Rollback requires the migration transaction, acquires ACCESS EXCLUSIVE locks in deterministic order (journal_sources, then source_records) before either emptiness check, and holds them through DDL and transaction end. Concurrent registrations/snapshots cannot commit between the checks and DDL. Empty rollback restores the original source guard and is tested with reapplication plus a real independent PostgreSQL writer. Populated rollback refuses before DDL; prefer a forward correction or reviewed backup recovery, never erase evidence to force rollback.

## Executed verification

Final results, failures and live verification are recorded below. All PHPUnit tests use PostgreSQL/RefreshDatabase, Http::fake/preventStrayRequests, fake sleeps and fake native resolver processes. No live Internet or real credential in ordinary tests. The existing dedicated *_test/SQLite refusal stays unchanged. No development/production migration, dependency installation, branch, commit or push.

## Out of scope

Full-site/recursive crawling, search engines, arbitrary URL ingestion, browser automation/JS/session scraping/CAPTCHA bypass, linked file/template acquisition, full guideline/LLM extraction, RAG/embeddings/recommendation, public journal API, projects/workspace/assistant implementation, frontend, Redis/FastAPI, billing, manuscript analysis/formatting/submission automation. These retain their established later/MVP product scope; no architecture change.

## Failures encountered and corrected

- First official suite: 90 tests, 77 passed, 12 failures and one error. Laravel accumulated HTTP fake callbacks, so earlier successful/failed responses intercepted later scenarios. Reset the fake factory between independent scenarios, preserving all cases; 13 affected tests / 52 assertions passed before the complete suite. One genuine diagnostic bug rejected digits in official_unexpected_304: allow the existing fixed numeric-status code, without exposing exception text.
- Seven added regressions initially had one failure and one error: comparing unrefreshed Eloquent timestamp strings with PostgreSQL UTC-offset strings, and constructing a CRLF header which Guzzle rejects before transport. Reload the initial row for comparison and test a transport-valid non-ASCII/oversized validator; the two affected tests / nine assertions passed first. No production relaxation.
- First live attempt failed before HTTP: installed Guzzle disallows raw CURLOPT_NETRC and manages CURLOPT_PROXY / CURLOPT_PROTOCOLS itself. Actual local CurlFactory validation exposed the complete static option error. A new offline native-handle regression reproduced the failure, then passed (one test / three assertions) after using supported Guzzle proxy/auth/protocols options and libcurl’s default ignored netrc. DNS pinning, TLS, fresh connections and URL/redirect guards were preserved. This was a task-caused configuration error, not an external timeout workaround. All relevant suites were rerun.
- Successful robots-policy check reported Crawl-delay: 30. Raised the fixed inter-request delay from one to 30 seconds, including redirects; the focused snapshot/cadence regression passed first, then relevant suites were rerun. No third live operation and no robots parser/recursive acquisition added. Longer restrictions must defer approval; origin-wide operator coordination remains required.

No failing test was disabled, skipped or deleted. No dependency change, force option, SQLite fallback or security weakening.

## Bounded live verification — 2026-10-12

Two attempts total, same explicit official PLOS host policy page associated with a temporary PLoS ONE / ISSN 1932-6203 test journal. First failed before HTTP due to the client option issue above; second used the corrected actual approved-source client.

- Requested/final URL: https://journals.plos.org/robots.txt; no redirect or linked-resource request.
- HTTP **200**, MIME **text/plain**, **158 bytes**, parser official-page-raw-v1.
- SHA-256 observation fingerprint: 12ec9e8b35c8c05a0067118da65b946e421f381fe6f02f5698979627ef2fa705.
- Explicit journal-bound registration and approval were required. Fetch was a transient zero-write acquisition; all table counts during fetch unchanged. Temporary registration/approval/journal setup occurred outside acquisition transactions in the isolated test database and was deleted afterward; all 37 table counts returned to their initial values.
- **Zero persisted snapshots/assertions/publications**. No live page body or recorded fixture saved; raw response existed only in bounded transient memory. No live DOAJ/Crossref/OpenAlex call.

This verifies the real DNS/cURL-pinned HTTPS/plain-text path, not a live editorial HTML mapping or retained official requirements. Robots directives were manually inspected; access policy reports a 30-second cadence and restricted search/metrics/user paths. No guideline page was requested after the two-attempt cap, no legal/retention permission is inferred and broad live editorial coverage remains unverified.

## Exact files changed

26 files, uncommitted:

```text
README.md
apps/api/app/Console/Commands/JournalSources.php
apps/api/app/Models/JournalSource.php
apps/api/app/Models/SourceRecord.php
apps/api/app/Services/OfficialSources/OfficialSourceClient.php
apps/api/app/Services/OfficialSources/OfficialSources.php
apps/api/app/Services/OfficialSources/OfficialUrl.php
apps/api/app/Services/OfficialSources/PublicDns.php
apps/api/database/migrations/2026_10_12_100000_add_official_source_foundation.php
apps/api/tests/Feature/JournalCatalogTest.php
apps/api/tests/Feature/OfficialSourceTest.php
apps/api/tests/Fixtures/official-sources/README.md
apps/api/tests/Fixtures/official-sources/guidelines.html
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/data/JOURNAL-SCHEMA.md
docs/development/JOURNAL-INGESTION-PLAN.md
docs/development/JOURNAL-INGESTION-V1.md
docs/development/OFFICIAL-SOURCES-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/product/ROADMAP.md
docs/security/PRIVACY.md
docs/security/SECURITY.md
```

## Final executed gates

Final tests after native transport/cadence corrections, PostgreSQL 17 / isolated fikarohana_test only:

| Gate | Result |
| --- | --- |
| OfficialSourceTest | **101 tests / 340 assertions passed** |
| OpenAlexProviderTest | **64 tests / 376 assertions passed** |
| CrossrefProviderTest | **49 tests / 267 assertions passed** |
| DoajCsvTest + DoajProviderTest | **107 tests / 566 assertions passed** |
| Full Laravel | **427 tests / 2329 assertions passed**, 37.070 seconds |
| Pint --test | Passed |
| Composer validate --no-check-publish | Valid |
| Composer audit --format=json | Zero advisories / abandoned packages |
| OpenAPI validate / generated contracts check | Valid / reproducible and current |
| git diff --check | Passed |
| Local Markdown links | **148 targets, zero missing** |

No failures/skips remain. No test required live Internet; provider suites were rerun after corrections. HEAD/master and prior Phase 3A–3D commits are unchanged, index empty; all 26 Phase 3E-A files remain uncommitted. No branch, commit, push, dependency installation or real .env change.

Phase verdict: **PASS** for the controlled source acquisition foundation. Broad live editorial HTML coverage, retained real source rights, extraction/verification/publication and production activation remain outside this delivery.

## Final security correction pass — 2026-10-12

This section supersedes the earlier self-review PASS and test totals. The final read-only review required three corrections, all now implemented without changing architecture, provider mappings, URL/DNS/IP/TLS policy or published revision behavior.

1. Guard approval fields from generic mass assignment. Controlled registration assigns candidate directly, serializing duplicate registration through the existing journal row; controlled approve/deactivate assign protected state internally. Generic updates cannot reassign registered ownership, canonical URL or approved origin. SQL identity/history guards remain authoritative against bypassing Eloquent. SourceRecord creation fields remain available to existing controlled ingestion/factories; persisted records are SQL-immutable against generic model updates and direct SQL, with regressions for source/fingerprint/URL/retrieval/body/metadata mutations. No user-facing snapshot write path exists and no broad model rewrite was needed.
2. Every GET now follows cadence wait → fresh database approval check → source/URL check → DNS validation/pin → request. Vanished/inactive sources fail before transport. Redirects repeat this sequence; once revocation is observed no next GET occurs. Persistence still checks approval under lock. This minimizes stale-state exposure but cannot cancel an already dispatched HTTP request or eliminate the small interval after the last check; no long transaction or polling added.
3. Rollback obtains both PostgreSQL ACCESS EXCLUSIVE table locks before checking emptiness. A native asynchronous PostgreSQL writer test queues an official-source insert while the actual migration holds both locks, proves its wait in pg_locks, commits the empty rollback, then observes deterministic undefined-column failure (42703) against the restored old schema. No evidence commits in the critical interval. The test recreates the extension in a transaction and cleans up; ordinary populated-refusal and reapplication tests remain.

Additional security coverage:

- Native local-only transport harness: exact client transfer callbacks/options are captured, then exercised through real Guzzle CurlHandler against a finite loopback HTTP server. Missing Content-Length and chunked four-MiB responses abort near the 256-KiB threshold; compressed response is rejected at headers with zero retained body. The low-level test permits local HTTP solely in its separate harness, retains verify=true, and never changes the production HTTPS/DNS policy or exposes a loopback-capable source client. No Internet provider/website request or custom TLS stack. This proves transfer callbacks rather than merely invoking them under Http::fake; final persisted body ceiling stays strict.
- CRLF validators are rejected by PostgreSQL and the reuse validator, never reflected into conditional headers.
- Hostile HTML with external HTTP DTD/file entity/image invokes no external entity loader and remains inert original evidence; no external/local resource read.
- Temporary HTTP_PROXY/HTTPS_PROXY/ALL_PROXY (and lowercase variants) do not override explicit proxy disable in the installed Guzzle resolver/native handle. Environment restored in finally. verify=true, original hostname URL and CURLOPT_RESOLVE retained; this is option/handler proof, not a new live certificate test.

Failure encountered: the three native transfer cases initially passed Laravel’s captured handler as a per-request Guzzle option, which installed Guzzle rejects. Removed that harness-only option and configured a separate CurlHandler client, preserving production callbacks and security options. The exact three failing cases then passed (3 tests / 28 assertions), followed by relevant full suites. No production security setting weakened, test removed/skipped or dependency installed.

No new live request. Historical robots.txt verified only controlled text/plain transport; real editorial HTML compatibility remains live-unverified, with deterministic HTML fixtures/parser/snapshot coverage. This limitation is non-blocking for the acquisition foundation.

### Final correction-pass verification

PostgreSQL 17 / isolated fikarohana_test only. These numbers supersede the earlier delivery totals:

| Gate | Result |
| --- | --- |
| Focused approval/revocation/CRLF/entity/proxy | 12 tests / 68 assertions passed |
| Real rollback concurrency | 1 test / 14 assertions passed |
| Native transfer cases after harness correction | 3 tests / 28 assertions passed |
| Persisted generic snapshot immutability | 1 test / 13 assertions passed |
| Complete official suite (OfficialSourceTest + OfficialSourceRollbackTest) | **118 tests / 463 assertions passed** |
| OpenAlexProviderTest | **64 tests / 376 assertions passed** |
| CrossrefProviderTest | **49 tests / 267 assertions passed** |
| DOAJ API/CSV | **107 tests / 566 assertions passed** |
| Full Laravel | **444 tests / 2452 assertions passed**, 40.045 seconds |
| Pint --test | Passed |
| Composer validate/audit | Valid; zero advisories/abandoned packages |
| OpenAPI validate/contracts check | Valid; reproducible and current |
| git diff --check | Passed |
| Markdown local links | **150 targets, zero missing** |

No failures/skips remain. Exactly 12 files changed in this correction pass, listed below. SourceRecord and all pre-existing files outside this correction scope remain byte-for-byte unchanged from its start. Original 26-file Phase 3E-A inventory above describes the earlier delivery; the two new test files bring the full uncommitted Phase 3E-A set to 28 files. HEAD/master and prior history remain unchanged, index empty, no branch/commit/push/dependency installation/development migration or new live external request.

```text
apps/api/app/Models/JournalSource.php
apps/api/app/Services/OfficialSources/OfficialSourceClient.php
apps/api/app/Services/OfficialSources/OfficialSources.php
apps/api/database/migrations/2026_10_12_100000_add_official_source_foundation.php
apps/api/tests/Feature/OfficialSourceRollbackTest.php
apps/api/tests/Feature/OfficialSourceTest.php
apps/api/tests/Fixtures/official-sources/README.md
apps/api/tests/Fixtures/official-sources/stream-server.php
docs/development/OFFICIAL-SOURCES-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/security/SECURITY.md
```

Final correction verdict: **PASS**. No required review blocker remains. Operational rights/retention/purge, per-origin coordination, egress controls and real editorial HTML live coverage remain documented production/coverage limitations; cancellation of an already dispatched request is not claimed.

## Phase 3E-B extension (current behavior)

The Phase 3E-A acquisition-only statements and inventories above record that delivery. Stored approved observations now support [deterministic editorial extraction](EDITORIAL-EXTRACTION-V1.md) through journals:sources extract --record=SNAPSHOT_UUID [--dry-run]. Fetch itself still generates no assertion/review/publication. Extraction performs no DNS, HTTP or resource acquisition, creates only pending/conflicting proposals, and does not renew observation or verification dates. Historical robots verification is not editorial extraction verification; no real retained editorial HTML is claimed.
