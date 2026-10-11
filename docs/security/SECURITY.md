# Security design

Status: required controls and proposed implementation approach; not a claim of current implementation. The system may hold unpublished research and must treat it as confidential.

## Threats and trust boundaries

| Threat | Boundary/control |
| --- | --- |
| Cross-user access to saves, projects or files | Laravel owner/project policies on every operation; negative authorization tests |
| Session/token theft | Secure cookies, CSRF protection, revocable scoped device tokens, no credential logging |
| Research leakage through logs, caches or AI | Content minimization, private scopes, redaction and provider gates |
| Hostile source URL/document | SSRF controls, bounded parsing, quarantine and untrusted-data treatment |
| Source poisoning / fabricated rules | Staging, evidence review, immutable revisions and correction audit |
| Injection/XSS | Parameterized queries, validation, escaped rendering and safe source-text display |
| Abuse/resource exhaustion | Rate limits, input/file bounds, queue budgets and provider quotas |
| Object/backup exposure | Private buckets, least-privilege grants, encrypted storage and restore/access review |

## Authentication and authorization

Sanctum is the foundation. Web uses first-party session cookies with CSRF and narrowly configured trusted origins/domains; production cookies must be secure and use appropriate same-site/http-only settings. Mobile uses revocable per-device tokens stored in platform secure storage. Foundation V1 issues device-named tokens with configurable explicit expiry (`SANCTUM_MOBILE_TOKEN_EXPIRATION`, default 43200 minutes / 30 days) and revokes only the current personal access token. Web logout invalidates/regenerates session/CSRF without revoking device tokens. Registration does not sign in or issue a token. Do not use a shared permanent mobile credential.

Laravel validates all input and applies ownership checks when loading, listing, updating, deleting or issuing file URLs. Scope queries by owner; opaque IDs are not permission checks. Administrative ingestion/review operations require separate capabilities and audit trails. Account/role changes must not be inferred from client-provided fields.

Future institutional access requires explicit membership/project policies and tests before release. Owner-based MVP saves do not imply shared organization access.

## Files — required before project uploads

Private bucket/object ACLs by default. Generate server-controlled object keys; do not trust filenames as paths. Use allowlisted formats, MIME/signature validation, size/page/decompression limits and quarantined processing. DOCX parsing must resist archive bombs; PDF/office parsers run without privileged filesystem/network access. Define malware scanning and safe preview behavior before enabling uploads.

Authorize each upload completion and download. Temporary signed URLs have short configured lifetimes and scoped operations; never place them in logs or analytics. They permit storage transfer only; Laravel remains responsible for authorization and metadata. Deletion revokes future grants and removes originals and derivatives through a tracked job; already issued grants expire within the documented maximum lifetime.

No public manuscript URLs or executable inline source HTML. Store hash/type/size/scan state and audit relevant operations without recording file contents.

## Ingestion and AI boundaries

Restrict outbound fetching to approved provider/journal URLs. Validate schemes, DNS/IP destinations and every redirect; reject loopback, private/internal and cloud metadata destinations. Enforce time/size limits and avoid arbitrary URL-fetch endpoints. Do not bypass robots/access restrictions or use personal cookies to scrape sources.

FastAPI is private, authenticated between services, and receives only scoped data. Restrict its outbound network and resource limits; rotate service credentials and reject unauthorized callers. LLMs receive no secrets or automatic mutation tools. Retrieved instructions are data, not authority. See [RAG](../ai/RAG.md).

## Deployment and operations

TLS in production, private database/Redis/AI networking, separate environments/service accounts, least privilege and managed secrets. Disable public debug traces; restrict operational health/admin endpoints. CORS uses approved client origins. Protect login, registration, discovery and expensive jobs with distinct rate limits.

Log action/resource IDs, actor where appropriate, outcome, time and correlation ID. Never log bearer tokens, cookies, passwords, abstracts, manuscript text or signed URLs. Audit sensitive access, selection/review changes, deletion, file grants and administrative operations. Access and retention for audit data are restricted.

Encrypt confidential storage/backups using deployment controls; assess field encryption where needed. Test restores in an isolated environment. RPO/RTO, hosting region, incident contacts and rotation schedules require validation before production. Incident response: contain exposure, revoke affected credentials/grants, preserve minimized evidence, assess scope, notify under the applicable policy and fix/test the cause.

## Dependency discipline and release gates

Classify advisories by direct/transitive, runtime/build, actual exploitability and compatible upstream fix before changing versions. Never force-install, force-audit-fix or bypass peer dependencies without explicit approval. Preserve Expo SDK compatibility; Doctor and install compatibility checks must pass when dependencies change.

Release checks include authorization/isolation, web CSRF/mobile revocation, sanitized errors/logs, provider SSRF behavior, dependency review and backup restoration. MVP V1 also requires project ownership/deletion and contextual assistant grounding, abstention, prompt-injection and selected-journal isolation checks. File/parser and private-manuscript retrieval checks remain blockers for the later releases that introduce those capabilities.

## Implemented Foundation V1 controls

`/api/v1` responses have no-store JSON, server-generated request IDs and generic error details even when app debug is enabled. API exception reporting records only a fixed event, exception class and request ID; no default SQL/message/trace leakage. Register/login/token requests have IP/email-hash rate limits. CORS requires one explicit frontend origin and credentials never use wildcard origins. Session cookies default to Secure outside the local example, with HttpOnly and Sanctum-enforced SameSite=Lax; production must use HTTPS/same-site first-party deployment.

PostgreSQL dev/test databases are separate localhost-only containers with non-production credentials. The test harness refuses unsafe DB configurations before migration. Vector extension rollback preserves the shared extension. No private-file or AI-provider processing exists yet; the broader controls above remain future feature/release requirements. See [Foundation verification](../development/FOUNDATION-V1.md) for test results, existing dependency advisories and unverified production controls.

Foundation password controls keep bcrypt: registration minimum 12 characters, maximum 72 UTF-8 bytes for all credential endpoints, exact confirmation and rejection of null bytes. Missing-account mobile checks use a precomputed cost-12 dummy hash through Laravel hashing; both failure paths perform verification and return generic 401 errors. Reassess the dummy work factor if bcrypt cost changes; no micro-timing equality is claimed. Production release requires the HTTPS/cookie/origin/domain checks in [AUTHENTICATION](../api/AUTHENTICATION.md); local Secure=false is never a production setting.
