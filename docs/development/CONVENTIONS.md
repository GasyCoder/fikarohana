# Development conventions

Status: initial design. [AGENTS.md](../../AGENTS.md) and [CLAUDE.md](../../CLAUDE.md) are mandatory repository instructions.

## Change discipline

Read the relevant specifications and existing code before editing. Identify the affected layer and implement the smallest coherent change. Document assumptions and consequential decisions; never silently diverge from the specifications. Avoid unrelated rewrites or restoring the former application.

Work on `master` unless explicitly asked to create a branch. Do not commit automatically or rewrite history. Preserve unrelated user edits. Documentation-only tasks must not modify application code, manifests or lockfiles.

No new framework/infrastructure dependency without documented purpose. Before dependency updates, classify advisories as direct/transitive, runtime/build, exploitable in this product or not, and compatible fix available or unavailable. Do not use force install/audit fixes or legacy peer bypass without explicit approval; do not downgrade Expo outside its compatibility matrix. npm workspaces and root package-lock.json are canonical (approved Foundation V1 decision); do not introduce Yarn/pnpm or competing lockfiles.

## Layer boundaries

- Laravel: controllers validate/authorize and delegate to cohesive application/domain services; transactions guard canonical state. Use policies/scoped queries for ownership.
- Web/mobile: consume generated contracts, render UI and maintain presentation state. Client validation improves UX; Laravel still validates all canonical inputs.
- Python: typed internal computation contracts, explicit resource/time limits and versioned results. No duplicate account, project or entitlement rules.
- Contracts: review OpenAPI first, generate clients reproducibly, then verify consumers. Do not hand-edit generated artifacts or create competing model definitions.

Start within existing framework conventions. Directory restructuring and module frameworks are not prerequisites for writing focused domain code.

## API conventions

New public business routes are `/api/v1`. Use resource names and HTTP semantics; private discovery text goes in POST bodies. Define bounded pagination/filtering, validation and stable machine-readable error codes in OpenAPI. UTC ISO timestamps, explicit nullable/unknown states and opaque domain IDs are preferred. Secrets and stack traces never appear in responses.

Authentication remains Sanctum: first-party web cookies/CSRF and revocable mobile tokens, with ownership enforcement in Laravel. CORS/client affordances are not authorization. Document the framework CSRF route exception and scaffold route migration before changing existing behavior.

Model money exactly with currency/context; avoid floating-point fee math. Codes/enums are locale-independent, while human messages are localized. Breaking changes require a recorded compatibility/deprecation decision. A new API version is not a substitute for evaluating migration impact.

## Data and background jobs

Every consequential journal fact retains source, URL and retrieval/verification metadata. Never normalize missing into free, allowed or unlimited. Distinguish source assertions from derived summaries and versioned projections. Provider adapters implement fetch/normalize/stage boundaries rather than modifying public tables ad hoc.

Jobs are bounded, retryable and idempotent, with sanitized failure codes and unique work keys. Publish reviewed revisions transactionally; refresh derived state after commit. Do not hold transactions across network calls. Test PostgreSQL-specific search/constraints on PostgreSQL.

## Privacy and UI

No credentials, manuscripts or raw research text in logs, fixtures, commits, analytics or issues. Use synthetic or permitted public test data. Private objects and temporary grants follow Laravel authorization. UI text keeps provenance, uncertainty and AI interpretation visible; no acceptance probability language or unnecessary implementation details in user flows.

External URLs/source markup are untrusted. Render safe text, validate outbound fetch destinations and separate document content from AI instructions. Translate UI strings separately from authoritative source text and identify translated guidance.

## Definition of done

Affected requirements and contracts match implementation; tests cover meaningful behavior and failure modes at the affected layer; relevant checks in [TESTING](TESTING.md) pass; docs/decision register capture consequential choices. Report changes, validation and unresolved risks. Do not claim future components are deployed or record tests as passed without executing them.
