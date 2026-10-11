# Verification strategy

Status: planned gates, with current command inventory. No new test tooling is installed by this design task. Test the affected layer and meaningful behavior; avoid tests that merely repeat an implementation.

## Current versus planned checks

Run commands from the indicated directory after the existing dependencies/environment are provisioned. Missing tools/configuration are foundation work, not a reason to install dependencies during documentation work.

| Layer | Current commands | Additional foundation/release work |
| --- | --- | --- |
| Laravel, `apps/api` | `php artisan test` (PHPUnit through Laravel); `vendor/bin/pint --test` for formatting | Configure isolated PostgreSQL test DB; versioned API/auth/domain tests |
| Next.js, `apps/web` | `npm run lint`; `npx --no-install tsc --noEmit`; `npm run build` | Add justified journey/component test tools when tests are introduced |
| Expo, `apps/mobile` | `npx --no-install tsc --noEmit`; declared lint script needs tooling availability checked | On dependency changes, `npx expo-doctor` and `npx expo install --check`; pin/provision required validation tools deliberately |
| Python, `services/ai` | None: metadata/app/tests are empty placeholders | Add `pytest` and typed service checks when a real Python feature is introduced |
| OpenAPI/contracts | None: spec/generator/validator not selected | Validate specification, detect breaking changes and verify reproducible client generation |
| Documentation | `git diff --check`; local relative-link and required-file checks | Review design consistency and distinguish proposed/current behavior |

Commands are not proof they have been executed. Do not run `npx` in a way that silently installs tooling when installation is not authorized. The root manifest declares Yarn while example npm scripts also exist; resolve package-manager conventions before dependency changes.

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

## CI and release scope

Foundation CI validates the contract, PostgreSQL Laravel tests and applicable web checks; mobile TypeScript checks run for affected mobile/shared-contract changes. AI checks are added only once implemented. All affected consumers must pass when contracts change.

Before public release, run the full MVP V1 discovery → selection → project → five-tab workspace → contextual assistant journey on a reviewed catalog, test project isolation/deletion and grounded answers/abstention, verify source rights/freshness, inspect logs/errors for private data, test rate limits and demonstrate backup restoration. Agree a representative load fixture and targets first. Before confidential uploads, add object/parser/deletion tests and review external processing.

Documentation-only work uses whitespace/link/coverage checks and manual consistency review. Do not run unrelated production builds or claim application behavior was tested when only specifications changed. Once relevant checks pass, broaden only for unresolved failures/risks or new changes.
