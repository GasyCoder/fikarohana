# Phase 4C — Research Projects & Journal Workspace V1

Baseline: `020325f` on master. Laravel owns private projects, authorization and canonical journal projection. Next.js remains a presentation client. No new dependencies, mobile changes, AI calls, contextual assistant, manuscript processing or submission workflow.

Independent editorial-v5 holdout validation pending. Editorial implementation, historical artifacts, fingerprints and benchmark/protocol remain unchanged. The workspace never reads raw extraction proposals.

## Ownership and data model

`research_projects` uses Laravel's existing UUIDv7 strategy and a `user_id` foreign key. It stores a bounded title (500 characters), optional abstract (10000), up to twenty unique keywords (100 characters each), up to ten validated canonical subject UUIDs, optional validated language/article-type codes, explicit no-APC preference and up to ten validated indexation-service codes, one nullable canonical journal UUID, selection timestamp and creation/update timestamps. JSONB stores research intent arrays, not copied catalogue metadata. Research preferences, subject IDs and codes express the researcher's intent; they do not claim journal membership. Catalogue option lists remain bounded samples of published metadata.

Owner is assigned from authentication, never request input. The selected journal and its timestamp cannot be mass-assigned. User deletion cascades to projects; project deletion removes only that project. Journal deletion is restricted while referenced. Selection may be replaced and retains only the latest timestamp, without a workflow history. No recommendation scores, response bodies, result lists, recommendation limit or transient UI state are persisted. No-APC/indexation preferences are researcher intent; selection does not certify their satisfaction.

## API

All operations require `auth:sanctum`, accepting the existing first-party session or a valid bearer token. SPA writes retain CSRF protection. The policy checks owner identity for view, update, delete and journal selection. Collection queries are owner-scoped. Cross-user object access returns 403; nonexistent objects return 404. Responses inherit no-store and sanitized errors.

| Method | Endpoint | Behavior |
| --- | --- | --- |
| GET | `/api/v1/projects` | Own projects, newest update first; page and per_page (default 20, maximum 50) |
| POST | `/api/v1/projects` | Create private research intent; 201 |
| GET | `/api/v1/projects/{project}` | Own project and current safe journal workspace |
| PATCH | `/api/v1/projects/{project}` | Update validated research fields |
| DELETE | `/api/v1/projects/{project}` | Delete project only; 204 |
| PUT | `/api/v1/projects/{project}/journal` | `{journal_id}` selects/replaces an active, currently usable published journal |

Selecting a journal records a researcher's choice. It does not mean accepted, submitted, endorsed or guaranteed suitable. Unpublished, inactive or unusable canonical titles cannot be selected. Read/selection transactions use repeatable-read catalogue consistency. Each workspace reload resolves the current published catalogue; no old metadata is copied into projects. If a selection later becomes unavailable, its ID remains saved while `journal` is null and the UI explains the limitation.

OpenAPI 3.1 defines the input, response and workspace types; TypeScript uses generated aliases. Phase 4A public discovery contracts/scoring remain unchanged.

## Canonical boundary and workspace

The existing Phase 4A query and payload services supply usable reviewed/published selections, with current verification/conflict/applicability checks. A separate project projection adds canonical typed requirements and templates without changing discovery behavior. Project language/article type constrain scoped facts; absent context does not generalize a scoped requirement. Qualifiers, notes, operators, amounts, currency, basis and applicability are retained.

- **Overview:** private research title/summary/keywords/language/article type; selected journal title, publisher, identifiers, subjects, languages, article types and indexation. Change journal returns to discovery without deleting the project.
- **Requirements:** published typed values with exact operator, article/language scope and notes. Missing requirements say Not available; no inferred requirements.
- **Fees:** existing canonical APC/fee semantics. Unknown remains Unknown; numeric zero remains a known amount, not unconditional no APC. Waivers/promotions do not imply universal no APC. Explicit absence retains its stated context and other charges may apply.
- **Templates:** canonical reviewed links only; safe HTTP(S) links without embedded credentials. No file download or publisher proxy.
- **Sources:** field, source hostname/link and retrieval/verification dates. No assertion IDs, SourceRecord IDs, snapshots, reviewer/security internals or raw editorial proposals.

External URLs are links opened only by user action, with noopener/noreferrer. Unsafe URLs are omitted. Text is escaped by React.

## Web UX, authentication and privacy

Routes `/projects`, `/projects/new`, `/projects/[id]` reuse the green/neutral discovery design. The list supports pagination, opening, creation, deletion with explicit confirmation and sign-out. The workspace supports research editing and journal replacement. Recommendation/profile actions lead to a project chooser or project creation. Project chooser pagination exposes existing projects beyond the first page. If project creation succeeds but journal selection fails, the user is taken to the saved workspace with a clear warning instead of creating duplicates on retry.

Anonymous browsing/recommendation/comparison remain available. Saving from the research form or recommendation retains validated research in the root client memory while in-place login/registration occurs; project API responses are not part of anonymous discovery state. Unsaved research resets on reload/tab close or explicit sign-out. No localStorage/sessionStorage, bearer-token persistence or research content in URLs. Keywords/title/abstract are not logged by this feature. Existing generic API exception reporting omits SQL, exception text and traces. Owner/session authentication is enforced on the server, not just through UI hiding.

Browser authentication uses credentialed same-origin requests and readable XSRF cookie/header with the existing Sanctum first-party session. Next rewrites `/sanctum/csrf-cookie` as well as `/api/v1`. Public discovery still omits credentials. Protected screens are keyed by authenticated user and workspace ID so cached client state is discarded across account/project changes. 401 clears the client authentication state and unmounts protected screens; 419 refreshes CSRF for a later explicit retry without replaying writes. Production still requires the existing HTTPS, first-party origin/stateful-domain and secure cookie deployment checks. Do not mix localhost and 127.0.0.1 browser origins without configuring the existing Sanctum allowlist.

## Validation and operational state

New PostgreSQL tests cover authentication, owner CRUD/list isolation, cross-user denial, selection/replacement, catalogue-safe deletion, unavailable journals, canonical requirement/template projection, current review/scope, unknown versus explicit no APC versus numeric zero, input bounds, mass-assignment protection, bearer auth and SPA CSRF. Existing catalogue rollback verification now removes the dependent project table first; historical migrations and editorial files are unchanged.

Focused project/catalogue/discovery/auth checks: **97 tests / 767 assertions passed**. Web: **22 tests passed**, including project mapping, list/editor/workspace rendering, qualifiers/unknown values, safe links, deletion confirmation, auth-required copy and credentialed CSRF request mapping. Typecheck, lint and production build passed. OpenAPI validation and contract regeneration checks passed. Complete final PostgreSQL suite: **725 tests / 4606 assertions passed**; Pint passed.

The project migrations were applied to the existing local development PostgreSQL database. All migrations are current. Live anonymous proxy checks returned projects 401, public journals 200 and CSRF bootstrap 204 with the XSRF cookie. No fake catalogue records or development accounts were created. Frontend tests use static rendering and mocked requests, not a complete browser interaction suite. The empty local catalogue does not support a live published-journal selection exercise.

## Phase 4D handoff

An authorized project and a selected canonical journal can be a future assistant context boundary. Phase 4D must independently enforce ownership and selected-journal isolation, grounded answers, abstention and prompt-injection resistance. No assistant endpoint, chat, LLM/provider configuration or streaming exists here. Independent Editorial V5 holdout remains a separate pending validation gate; Phase 4C does not satisfy it.

Final integrity: **14/14 frozen V5 SHA-256 fingerprints match**; all 42 tracked editorial files are byte-identical to the pre-task snapshot. `git diff --check` passes. No dependencies, branch, staging, commit or push.
