# Verification strategy

Status: Foundation V1, Phase 2 journal/provenance and Phase 3A local ingestion verification are implemented; Phase 3B DOAJ, Phase 3C Crossref and Phase 3D OpenAlex transport/mapping tests are offline; other-provider and later domain gates remain planned. Test the affected layer and meaningful behavior; avoid tests that merely repeat an implementation.

## Current versus planned checks

Run commands from the indicated directory after the existing dependencies/environment are provisioned. Missing tools/configuration are foundation work, not a reason to install dependencies during documentation work.

| Layer | Current commands | Additional foundation/release work |
| --- | --- | --- |
| Laravel, `apps/api` | `php artisan test` (PHPUnit through Laravel); `vendor/bin/pint --test` for formatting | Isolated PostgreSQL Foundation, catalog and local ingestion tests implemented; DOAJ/Crossref/OpenAlex mapping/transport tests are offline; official-source/client tests remain future |
| Next.js, `apps/web` | `npm run lint`; `npx --no-install tsc --noEmit`; `npm run build` | Add justified journey/component test tools when tests are introduced |
| Expo, `apps/mobile` | `npx --no-install tsc --noEmit`; declared lint script needs tooling availability checked | On dependency changes, `npx expo-doctor` and `npx expo install --check`; pin/provision required validation tools deliberately |
| Python, `services/ai` | None: metadata/app/tests are empty placeholders | Add `pytest` and typed service checks when a real Python feature is introduced |
| OpenAPI/contracts, root | `npm run contracts:validate`; `npm run contracts:generate`; `npm run contracts:check` | OpenAPI 3.1 lint and exact generated-output check implemented; future CI compatibility checking |
| Documentation | `git diff --check`; local relative-link and required-file checks | Review design consistency and distinguish proposed/current behavior |

Commands are not proof they have been executed. Do not run `npx` in a way that silently installs tooling when installation is not authorized. Fikarohana uses npm workspaces and root package-lock.json; Yarn/pnpm are not introduced.

## Foundation execution

Start `docker compose -f infra/docker/compose.yml up -d --wait`. With PHP pdo_pgsql and provisioned Composer dependencies, run `php artisan test` and `vendor/bin/pint --test` in apps/api. phpunit.xml forces PostgreSQL and a dedicated fikarohana_test database, clears DB_URL and uses safe test-only key/credentials. Tests refuse non-PostgreSQL/non-*_test/DB_URL configurations before RefreshDatabase; failures never fall back to SQLite. Host/port/credentials can be configured for a different dedicated test instance.

Foundation tests cover readiness/vector, registration/validation/duplicates, session login/me/logout/regeneration, tokens/me/expiry/current-only revocation, cross-auth denial, request IDs, sanitized errors/logging, first-party CORS, explicit CSRF enforcement and all three rate limits. The obsolete welcome-page test now targets `/up`. A real multi-request HTTP smoke verified encrypted-cookie/X-XSRF-TOKEN and PostgreSQL database-session behavior.

The initial verification used the existing sail-8.4/app runtime because host PHP lacked pdo_pgsql. Host PHP now has both pdo_pgsql and pgsql; the correction suite runs directly on the host against the isolated PostgreSQL test container. Exact commands/results are in [FOUNDATION-V1](FOUNDATION-V1.md). Frontend/mobile UI and dependencies were not changed; no application UI builds are claimed.

## Behavioral matrix

| Capability | Required tests |
| --- | --- |
| Provenance/model | Missing vs verified zero; field/context evidence; stale/conflicting assertions; foreign keys; immutable revisions |
| Identity/ingestion | ISSN validation; duplicate/repeated import; same-title distinct journals; publisher/title changes; malformed record quarantine; partial provider failure; rollback/retry |
| Discovery | Valid/invalid bounded input; active catalog only; strict unknown exclusion; preferred unknown warning; cursor bounds; empty/partial results |
| Ranking | Determinism for fixed config/data; stable tie break; weight/missing-data policy; hard-filter invariants; mode/version disclosure; eligible category labels |
| Profiles/comparison | Fact evidence and dates; unknown fee display; no unsupported indexation; currencies/contexts not silently equated |
| Auth/saves | Web CSRF/session paths; mobile token issuance/revocation; anonymous boundaries; list/read/create/delete isolation; saved record deletion |
| Localization/web | French/English journey; source language labels; keyboard/form/status accessibility; responsive empty/error/loading states |
| External boundaries | Provider timeouts/rate limits; safe redirects/DNS targets; schema validation; unavailable optional AI fallback |
| MVP V1 projects/workspace | Minimum fields; owner-scoped CRUD/deletion; `discovery` → `journal_selected` → `preparation`; selection revision/refresh; reselection clears assistant context; five tabs and official links |
| MVP V1 contextual assistant | Seven supported question types; cited fact/passage support; explicit inability to verify; conflict/stale evidence; prompt injection; cross-user denial; out-of-scope writing; revision-change concurrency; provider outage/fallback |
| Later files | Upload validation/quarantine; download authorization; derivative deletion; future analyses invalidated on journal change |
| Later analysis | Permitted format fixtures; section/word/reference extraction; contextual rules; unsupported checks marked unknown; parser limits |

Use sanitized recorded source fixtures for repeatable ingestion tests, not paid/unreliable network calls on every CI run. Provider integration smoke checks are separate, use approved credentials and respect quotas. Mocking does not replace PostgreSQL integration tests for constraints/search/vector behavior.

## Scientific relevance evaluation

Curate permitted bilingual research inputs and a fixed journal revision snapshot with researcher judgments. Separate tuning/evaluation sets. Compare lexical baseline and semantic experiments on the same inputs. Track top-result relevance, coverage, strict-filter violations, missing facts, language differences, latency and cost. Human validation sets numeric relevance targets; do not invent success thresholds or acceptance outcomes.

Semantic/LLM changes require rerunning the relevant evaluation, not merely passing API schema tests. Grounding and privacy tests are additional gates for assistant release.

## Planned CI and future release gates

CI is not currently implemented: no GitHub Actions or other CI pipeline is supplied. Planned Foundation CI will validate the contract, PostgreSQL Laravel tests and applicable web checks; mobile TypeScript checks will run for affected mobile/shared-contract changes. AI checks will be added only once implemented. All affected consumers must pass when contracts change.

Before public release, run the full MVP V1 discovery → selection → project → five-tab workspace → contextual assistant journey on a reviewed catalog, test project isolation/deletion and grounded answers/abstention, verify source rights/freshness, inspect logs/errors for private data, test rate limits and demonstrate backup restoration. Agree a representative load fixture and targets first. Before confidential uploads, add object/parser/deletion tests and review external processing.

Documentation-only work uses whitespace/link/coverage checks and manual consistency review. Do not run unrelated production builds or claim application behavior was tested when only specifications changed. Once relevant checks pass, broaden only for unresolved failures/risks or new changes.

Security regression coverage includes Hash::check on both mobile failure paths without timing assertions, ASCII/UTF-8 bcrypt byte boundaries across registration/login/token issuance, confirmation, validated expiry configuration with a frozen clock, existing-token preservation after web logout, current-token-only deletion, TransientToken/session-plus-Bearer rejection, Referer-only stateful context, and case/trim email buckets across different IPs.

## Phase 2 catalog verification

`JournalCatalogTest` uses the same dedicated PostgreSQL test instance/RefreshDatabase as Foundation. It checks real SQL CHECK/UNIQUE/composite foreign-key constraints and triggers through direct database writes as well as Eloquent. Expected constraint failures run in nested transactions/savepoints so PostgreSQL transaction aborts cannot hide later assertions. Synthetic factories and JournalCatalogFixture provide journal/source/fee/language/indexation/template cases; no live provider calls or copied journal facts.

Coverage includes UUIDv7/non-unique titles, publisher and hierarchy relations, ISSN checksum/normalization/global ownership, accepted/unknown/explicitly unsupported languages, article types, exact zero/unknown/not-applicable/paid fees, currencies, typed requirements, template/indexation evidence, immutable observation idempotency, field/journal attribution, reviews/effective dates, conflict resolution without evidence deletion, historical/current usability, sealed catalog snapshots, deletion restrictions and transactional migration rollback preserving vector. [JOURNAL-DATA-MODEL-V1](JOURNAL-DATA-MODEL-V1.md) records exact executed counts. No CI pipeline, successful live-provider verification, recommendation evaluation or journal API is claimed; offline DOAJ provider tests are now implemented. Phase 3A synthetic ingestion tests are recorded in [JOURNAL-INGESTION-V1](JOURNAL-INGESTION-V1.md).

## Phase 3A correction-pass verification

PostgreSQL ingestion regressions cover valid/NUL/valid batches, nested values and keys, hash-only quarantine without unsafe summaries, zero-write dry-run, omitted/1/100 and invalid CLI limits, provider/source isolation enforced by SQL, unique-item counters across duplicate positions/replays/resume, completed-run guards, completion-failure recovery with preserved items and deterministic exit codes (including all-quarantine success).

The rollback test enters the real item processor and uses a temporary PostgreSQL trigger to fail fee insertion after actual journal/identifier/assertion/item writes. Assertions prove that item's writes vanish, earlier results remain, sanitized failed states persist and retry creates no duplicates. The trigger exists only in the test; no production failure seam. Exact executed counts and quality gates are recorded in [JOURNAL-INGESTION-V1](JOURNAL-INGESTION-V1.md). No live provider or SQLite is used.

## Phase 3B DOAJ verification

DoajProviderTest uses PostgreSQL/RefreshDatabase, synthetic current-schema fixtures and Laravel Http::fake with stray requests prohibited. No live network is a test requirement. Tests exercise actual 3A/Phase 2 staging, provenance, identity, conflicting evidence, explicit review/publication plus bounded native-HTTP pagination, retries, limits, dry-run, malformed pages and safe errors. Fake sleeps avoid real retry delays. The optional one-record live probe is separately reported as unverified, with zero writes, in [DOAJ-ADAPTER-V1](DOAJ-ADAPTER-V1.md).

## Phase 3B official CSV tests

`DoajCsvTest` uses the same isolated PostgreSQL database and prohibits stray HTTP requests. Synthetic fixtures follow the verified public CSV schema. Tests cover shared API/CSV mapping parity, raw row/transport/fingerprint/locators, identifiers and optional columns, languages/countries/selected LCC subjects, APC absence/unknown/paid/multiple currencies/waiver, duplicate and changed rows, API/CSV identity, provider namespace isolation, malformed quotes/headers/rows, UTF-8/U+0000 quarantine, formula-as-text behavior, bounded download/redirect/limit/timeout configuration, dry-run and staged/pending publication boundaries. Explicit no-APC versus positive-APC conflicts are tested in both directions. A tiny live CSV dry-run is separate from PHPUnit; network failure must not weaken deterministic logic. Exact final counts and all failures are recorded in [DOAJ-ADAPTER-V1](DOAJ-ADAPTER-V1.md).

Final metadata-URL regressions cover query, fragment, both, percent encoding/path parameters, malformed URLs, credentials, controls and javascript/file/data schemes through both API and CSV mapping. Invalid optional websites are omitted while raw evidence survives; U+0000 remains quarantined. HTTP request assertions prove only the official CSV source is requested and journal URLs are never fetched. Existing origin/redirect restrictions remain under the complete DOAJ suite. Final executed counts are in the DOAJ report.

## Phase 3C Crossref verification

`php artisan test --filter=CrossrefProviderTest` uses isolated PostgreSQL, synthetic fixtures, Http::fake/preventStrayRequests and fake sleeps. Tests exercise canonical/staged candidates, print/electronic/both exact lookup, declared/missing types, original observations, title/publisher conflicts, owned-identifier collisions including untyped incoming values, no title merge/bootstrap, pending review/publication and rollback, replay/changed input/first retrieval/provider namespaces, NUL/incompatible JSON, malformed optional/required data, size bounds, dry-run all-table counts, CLI limits/preflight, 404, bounded 429/5xx/timeout retries, origin/TLS/MIME/schema restrictions and no metadata-URL fetching. Network partial failure persists no batch; semantic partial failure retains valid results/counters. Full framework tests continue to cover real partial-write rollback and concurrent PostgreSQL locks. No live provider is required for deterministic tests. Exact final counts, quality gates and the separately reported single-ISSN probe appear in [Crossref report](CROSSREF-ADAPTER-V1.md).

## Phase 3D OpenAlex verification

`php artisan test --filter=OpenAlexProviderTest` uses PostgreSQL/RefreshDatabase, synthetic Source fixtures, Http::fake/preventStrayRequests and fake sleeps. Covers exact print/electronic/both lookup and bounded CLI, existing/staged candidates, all provider corroboration, source ID/type/publisher semantics, raw descriptive metrics, hierarchy/duplicate IDs/missing parents, invalid taxonomy/unknown levels, immutable versions/idempotency/history/conflicts, direct SQL qualification/parent/uniqueness guards, empty/populated rollback and reapplication, all-table dry-run/preview conflicts, malformed JSON/UTF-8/NUL/MIME/shape, size/aggregate bounds, 404/retries/auth/TLS/fixed origin/no URL fetching, partial-write rollback/retry and sealed publication receipts. No live credential/network is required. Final counts, encountered failures and separate small live probe are in [OpenAlex report](OPENALEX-ADAPTER-V1.md).
