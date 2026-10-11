# Implementation backlog

Status: design handoff. Nothing below is implemented by the documentation task. Follow [ROADMAP](../product/ROADMAP.md) and preserve existing user changes.

## Phase 1 — recommended first implementation

Goal: a reproducible API/data foundation and a reviewed small catalog, without discovery UI, projects or manuscript features.

| Priority | Work | Completion evidence |
| --- | --- | --- |
| 1 | Confirm package manager/runtime conventions, pilot scope and data-review owner | Record O-01/O-11 outcomes; no competing lockfiles |
| 2 | Provision isolated local/test PostgreSQL, ensure pgvector availability, plan Redis integration | Laravel PostgreSQL connection/migrations and test isolation pass; no production SQLite reliance |
| 3 | Define `/api/v1`, migration/deprecation of scaffold `/api/user`, JSON validation/error format and public ID convention | Document compatibility decision; versioned smoke endpoint tested |
| 4 | Complete API/auth/OpenAPI docs and create `packages/contracts/openapi.yaml` using selected validator/generator | Contract validation and reproducible generated client check; no hand-edited generated types |
| 5 | Implement/verify Sanctum cookie/CSRF and revocable mobile token flows for the agreed endpoints | Auth and cross-user/anonymous denial tests; no secrets in logs |
| 6 | Implement journal identity, sources, assertions/reviews and published revision model | PostgreSQL migrations and missing/zero/conflict/identity tests |
| 7 | Build approved synthetic/permitted seed import and source registry | Idempotent import, field citations and reviewable sample projection |
| 8 | Validate source rights and implement the first approved provider adapter with staging/review | Recorded terms review; retries/quarantine/partial failure tests; no provider auto-publication |
| 9 | Add targeted CI and sanitized operational metrics | API/contract checks and ingestion counters demonstrate foundation gate |

Steps 1–5 establish technical foundation; steps 6–8 establish the first data slice. Vector similarity, Python deployment and private manuscript storage are not prerequisites to importing sourced metadata. Do not create future project tables or an assistant during this phase.

Before public catalog publication, approve provider rights, field authority, refresh windows and data-quality review. While those choices are pending, development can use reviewed synthetic/permitted fixtures.

## After foundation

- [ ] Implement bounded anonymous discovery and lexical candidate retrieval over active reviewed records.
- [ ] Implement strict/preferred criteria with unknown/stale behavior and visible exclusion reasons.
- [ ] Implement versioned ranking configuration, deterministic explanations and qualified category labels.
- [ ] Curate researcher-judged French/English evaluation fixtures; evaluate semantic matching before selecting/deploying embeddings.
- [ ] Build sourced profile API/UI with guidelines/template links, fee context and field freshness.
- [ ] Build basic comparison and owner-scoped saved searches/journals with deletion.
- [ ] Complete bilingual/accessibility journey validation and MVP security/privacy/operational gates.
- [ ] Validate mobile release scope and consume the shared contract without duplicating business logic.

## Deferred capabilities

- [ ] Research projects, journal-selection requirements snapshots and workspace tabs.
- [ ] Private object uploads, manuscript versioning, quarantine, deletion and controlled downloads.
- [ ] Related articles from permitted bibliographic data.
- [ ] Document ingestion/chunking and journal-specific RAG after corpus/grounding gates.
- [ ] DOCX/PDF analysis and evidence-backed compliance/readiness checks.
- [ ] Advanced comparisons/history, subscriptions and institutional tenancy after product validation.

## Human validation queue

See [DECISIONS](../architecture/DECISIONS.md) for IDs and owners. Highest-priority items are initial catalog scope, provider terms/authority, freshness/review workflow, package manager/OpenAPI tools and versioned authentication migration. Before release, also resolve success thresholds, web/mobile delivery scope, quotas and privacy/hosting/retention/recovery choices. Commercial decisions do not block the technical foundation but do block billing commitments.

Do not convert unresolved options into implied promises. Update relevant docs and tests when a decision becomes concrete. Keep acceptance probability, fabricated metadata, automatic submission and full manuscript analysis outside MVP.
