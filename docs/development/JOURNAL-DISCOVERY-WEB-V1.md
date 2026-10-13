# Phase 4B — Web Journal Discovery UI V1

Baseline: `5a8622f` on master. Public Next.js/TypeScript interface in `apps/web`; all Phase 4A Laravel/catalogue/scoring/publication code and contracts remain unchanged. No authentication, mobile, manuscript upload, projects, assistant, LLM, billing, branch, commit or push.

Editorial-v5 independent holdout validation remains pending. Editorial implementation, fingerprints, protocol, holdout and historical artifacts are unchanged; Phase 4B displays only the canonical Phase 4A responses, never raw assertions or extraction proposals.

## User flow and routes

| Route | Screen |
| --- | --- |
| `/` | Restrained landing: describe research or browse published journals |
| `/research` | Required research title; optional abstract/keywords and collapsed preferences |
| `/recommendations` | Ranked shortlist, numerical relevance, deterministic reasons, inspect/compare actions |
| `/journals` | Search/filter catalogue, responsive collapsible filter panel, pagination |
| `/journals/{id}` | Current canonical detail, fees/applicability, identifiers and subtle source references |
| `/compare` | Client-side table for up to five selected journals; remove/clear controls |

Root client provider retains response metadata and selected journals across normal Next navigation. No complex persistence: hard reload/new tab clears results and comparison; the recommendations screen then invites a new research description. Browse query and pagination use URL parameters, preserving browser back/forward and shareable public catalogue filters. No title/abstract/keyword payload enters a URL.

Research title has a 500-character limit; abstract 10000; up to twenty comma-separated unique keywords of up to 100 characters; one selected subject; up to ten required indexations. Request mapping uses generated request field names, canonical values behind human-readable labels and default limit five. Advanced options are optional. Frontend validation improves feedback; Laravel remains the final validator and policy owner.

## API integration and available filter options

Central `src/lib/api.ts` calls only:

- `GET /api/v1/journals` for browse and a bounded 100-journal first-page option sample.
- `GET /api/v1/journals/{id}` for current detail and source summary.
- `POST /api/v1/journal-recommendations` with the mapped transient form.

Types are aliases of `packages/contracts/generated/api.d.ts`, not duplicated business schemas. Same-origin requests rewrite to the configured Laravel `FIKAROHANA_API_ORIGIN` (default localhost:8000). It must be a plain HTTP(S) origin without credentials, query, fragment or path. This trusted server configuration is not an arbitrary user URL. Build/run configuration must agree. Browser requests omit credentials and disable fetch cache. No direct database or DOAJ/OpenAlex/Crossref calls. No backend changes or new metadata endpoint.

Phase 4A has no complete public vocabulary endpoint. Selectors derive names/IDs/codes only from returned published journals: the initial first-page sample, subsequent browse pages, recommendation results and inspected detail. In-memory observed metadata is bounded to 500 journals. Unsupported memberships do not become supported choices; duplicate entries are deduplicated. A unavailable selector says no options available rather than requesting a technical ID or inventing values. API option failure is visible and retryable; title-only search remains possible. This is explicitly a partial option source, not a complete vocabulary catalogue. A complete vocabulary endpoint is a future separately scoped improvement.

Browser filter query includes q, exact subject, language, article type, indexation, publisher and explicit no-APC boolean only when chosen. It does not relax filters to fill a page. Pagination uses server totals. No client ranking or score calculation.

## Score, fees and unknown semantics

Every recommendation says **Relevance score** with the message:

> The relevance score measures metadata compatibility with your research. It does not predict manuscript acceptance.

No probability bars, AI prose, acceptance/quality scores, fake metrics or journal alternatives. Reasons display only returned subject/taxonomy/keyword matches. A zero-match eligible result explains absence of a direct metadata match. Missing publisher/subjects/languages/types/indexation display Not available. Unknown APC remains Unknown; context-dependent APC remains Depends on context. Numeric zero stays a known currency amount, never a no-APC label. Explicit no-APC says No APC reported and warns that other charges may apply. Fees retain their canonical applicability/context. This preserves the Phase 4A semantic correction.

Detail shows source hostname plus retrieval/verification dates and safe HTTP(S) links. It never renders raw HTML or shows assertion/SourceRecord IDs, original editorial excerpts or ingestion diagnostics. External source links open only on explicit user action with noopener/noreferrer; they are not automatically fetched. Unsupported/unsafe link schemes and embedded credentials are rejected.

Comparison uses the captured canonical journal summaries. Relevance appears only for an entry selected from recommendation results; a browse-only entry has Not available. Captured recommendation scores belong to that original result batch; no score is computed for newly browsed journals. Comparison is not a live revision synchronizer; inspect current detail before deciding. Results may carry request-specific scope while the detail API exposes its unscoped canonical view; preserve individual fee context and do not assume summary no-APC covers every manuscript. No unknown value is interpreted as No.

## Loading, empty/error states and privacy

Abort previous route requests on unmount/change and bound requests with a 20-second client timeout. Loading is announced using status/aria-busy. Empty results show no fake journals and invite explicit filter/research changes. Partial recommendations state that requirements were not relaxed. Validation field messages stay with the form; generic server/rate-limit/404/unavailable errors use safe copy and optional correlation reference. Retry is explicit. Missing options never block a title-only query.

Title/abstract/keywords live only in form/request memory and are discarded on leaving the form. The root provider stores returned journal metadata/reasons and selection, not raw input. No localStorage/sessionStorage, browser console logging, research analytics or saved manuscripts. No account or server business write. Fonts use local system stacks, avoiding font downloads and allowing offline production builds. Source bodies are never loaded by the web client.

## Design and accessibility

Warm neutral background, dark ink typography, forest-green primary actions, restrained borders and spacing. Serif emphasis is limited to editorial headings. Reusable cards/facts, form selectors, relevance/empty/error/loading components, detail and comparison table. Tailwind/Next remains the styling foundation; no shadcn or third-party UI framework added.

Semantic headings/forms/labels, required title, associated inline errors, focus outlines, skip link, keyboard-native selectors/details/checkboxes, pressed-state comparison actions, semantic table headers/caption and reduced-motion support. Main navigation runtime hooks and dynamic journal parameters use Suspense boundaries compatible with existing Next cache components/partial prerendering. Tablet/mobile stack the research/sidebar layouts; filters can collapse and large comparisons scroll within an accessible region.

## Tests and checks

`npm --workspace apps/web test` uses Node's built-in runner and React static rendering: form validation/mapping, browse query state, canonical option extraction, relevance/reasons, empty/partial/reset states, loading/errors/retry, detail/provenance, comparison limits/unknowns and central API success/failure mapping. All fetches are mocked. It is not a browser interaction framework and does not claim complete keyboard/screen-reader or live API integration verification.

Encountered/resolved: a comparison test fixture reused an already selected journal ID (correctly toggling removal); corrected the synthetic fixture. TypeScript rejected unknown error values used directly as JSX children; converted condition checks to booleans. Production prerendering required Suspense around runtime pathname/parameter hooks; added narrow boundaries without disabling cache components. During initial UI implementation: no dependency installation, external research, provider acquisition or Laravel suite rerun.

Final gates:

| Check | Result |
| --- | --- |
| Focused web tests | **13 passed**, zero failed/skipped |
| TypeScript (`npm --workspace apps/web run typecheck`) | Passed |
| ESLint (`npm --workspace apps/web run lint`) | Passed |
| Next production build | Passed; all six routes generated, dynamic detail partially prerendered |
| Generated-contract consistency | Current and reproducible; no contract edits |
| Local desktop/mobile landing visual inspection | Passed; Firefox screenshots outside Git |
| `git diff --check` / local documentation links | Passed |
| Backend/editorial integrity | All tracked Laravel files, contracts and editorial/protocol/holdout artifacts byte-identical to baseline; fourteen V5 fingerprints match |

The initial UI implementation changed only the web client, its example configuration/tests and this documentation, without dependency/lockfile changes, database operations or a Laravel suite rerun. Local Firefox screenshots of the landing at 1440×1080 and 390×844 were visually inspected; screenshot/profile assets remain outside Git under `/tmp`.

## Phase 4C handoff and limits

Phase 4B supplies a usable anonymous journal discovery flow for follow-on work; Phase 4C is not started. Any project/selection persistence, API vocabulary endpoint, richer scoped detail or account workflow requires a separate task. Options are partial, comparisons/results reset on reload, metadata is captured at retrieval, and the production catalogue must contain reviewed published records to yield results. No live production-data end-to-end exercise or general ranking-quality claim. Keep the independent editorial holdout pending gate separate.

## Targeted CSS pipeline follow-up

Replaced the custom `@tailwindcss/turbopack` CSS rule with the PostCSS setup documented in the installed Next.js 16.4.0 CSS guide. Added web development dependencies `@tailwindcss/postcss ^4.3.3` and explicit `postcss ^8.5.23`, removed the unused Turbopack loader, and added `apps/web/postcss.config.mjs`. The lockfile was regenerated; existing package versions were not upgraded. The global stylesheet, layout, UI and API behavior were unchanged by this follow-up.

The original unstyled-page symptom could not be reproduced locally: the old loader also returned compiled CSS containing the `.hero` rules. Its being the root cause is therefore unproven. After restarting with PostCSS, HTML and its linked compiled stylesheet returned HTTP 200, the stylesheet had `text/css` content type, and Firefox visual inspection confirmed the styled desktop landing. Typecheck, lint and production build passed. Screenshots and diagnostic files remain outside the repository under `/tmp`.

Before installation, dependency advisories were reviewed: the relevant reported ESLint/Next plugin chain is development/build tooling; the reviewed advisory output reported no Tailwind/PostCSS advisory. No runtime exploitation was demonstrated in this CSS task. No forced fixes or unrelated security upgrades were applied.
