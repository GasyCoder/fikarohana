# Implementation backlog

Status: Foundation V1 and Phase 2 journal data/provenance schema implemented; external ingestion and later MVP slices remain pending. Follow [ROADMAP](../product/ROADMAP.md) and preserve existing user changes.

## Phase 1 — foundation progress and next data slice

Completed technical foundation: PostgreSQL/pgvector, versioned API, Sanctum and OpenAPI. Next build journal identity/provenance and a reviewed small catalog; no domain/UI/project/manuscript feature was implemented in Foundation V1.

| Priority | Work | Completion evidence |
| --- | --- | --- |
| 1 | Confirm package manager/runtime conventions, pilot scope and data-review owner | O-11 package/tool choices resolved; O-01 pilot scope/review owner still pending |
| 2 | Completed: isolated local/test PostgreSQL and vector extension; Redis implementation deferred | Laravel PostgreSQL connection/migrations and test isolation pass; no production SQLite reliance |
| 3 | Completed: `/api/v1`, removal of unconsumed `/api/user`, safe JSON/errors/request IDs; Phase 2 resolves new domain IDs to UUIDv7 | Document compatibility decision; versioned smoke endpoint tested |
| 4 | Completed: API/auth/OpenAPI docs, OpenAPI 3.1 specification, validator/generator | Contract validation and reproducible generated client check; no hand-edited generated types |
| 5 | Completed: tested Sanctum cookie/CSRF and device-named expiring/revocable token flows | Auth and cross-user/anonymous denial tests; no secrets in logs |
| 6 | Completed Phase 2: journal identity/vocabularies, typed facts, sources, assertions/reviews and journal/catalog revisions | PostgreSQL migrations, field provenance, history/unknown/zero/conflict/identity/rollback tests; synthetic fixtures only |
| 7 | Build approved synthetic/permitted seed import and source registry | Idempotent import, field citations and reviewable sample projection |
| 8 | Validate source rights and implement the first approved provider adapter with staging/review | Recorded terms review; retries/quarantine/partial failure tests; no provider auto-publication |
| 9 | Add targeted CI and sanitized operational metrics | API/contract checks and ingestion counters demonstrate foundation gate |

Technical parts of steps 1–5 are complete, with pilot decisions still to validate. Step 6 is complete, including UUIDv7 convention; steps 7–8 are the next ingestion slice. Targeted checks are executable; CI wiring in step 9 remains pending. See [FOUNDATION-V1](FOUNDATION-V1.md) for actual verification. Vector similarity, Python deployment and private manuscript storage are not prerequisites to importing sourced metadata. Keep lightweight project and assistant implementation in the later V1 slices below; do not add those features during foundation.

Before public catalog publication, approve provider rights, field authority, refresh windows and data-quality review. While those choices are pending, development can use reviewed synthetic/permitted fixtures.

## Ordered MVP V1 implementation after foundation

- [ ] Implement bounded anonymous discovery and lexical candidate retrieval over active reviewed records.
- [ ] Implement strict/preferred criteria with unknown/stale behavior and visible exclusion reasons.
- [ ] Implement versioned ranking configuration, deterministic explanations and qualified category labels.
- [ ] Curate researcher-judged French/English evaluation fixtures; evaluate semantic matching before selecting/deploying embeddings.
- [ ] Build sourced profile API/UI with guidelines/template links, fee context and field freshness.
- [ ] Build basic comparison and owner-scoped saved searches/journals with deletion.
- [ ] Implement owner-scoped lightweight project CRUD/deletion: title, abstract, scientific field, keywords, authors, journal shortlist, selected journal, status, created_at and updated_at.
- [ ] Enforce `discovery` → `journal_selected` → `preparation`; journal selection captures a requirements revision, clearing/reselection resets context/status and newer guidance needs explicit refresh.
- [ ] Build Overview, Requirements, Fees, Template and Assistant tabs; show verified requirements, official guideline/template/submission links and explicit unavailable information.
- [ ] Implement the simple contextual journal assistant: verified fact lookup and scoped authoritative passage retrieval, citations and explicit inability to verify; no generic writing chat.
- [ ] Test supported French/APC/article-type/abstract/reference/template/submission questions, abstention, source injection, cross-user access, selection-change concurrency and optional model outages.
- [ ] Complete the bilingual discovery-to-workspace-and-assistant journey, accessibility, project deletion, privacy/security and operational gates before declaring MVP V1 complete.
- [ ] Validate mobile release scope and consume the shared contract without duplicating business logic.

## Deferred capabilities

- [ ] Private object uploads, manuscript versioning, quarantine, deletion and controlled downloads.
- [ ] Related articles from permitted bibliographic data.
- [ ] Advanced semantic RAG/reranking and durable conversation history only if validated beyond the minimum V1 fact/passage assistant.
- [ ] Full DOCX/PDF analysis, automatic manuscript rewriting/formatting, full compliance/readiness scoring and reference manager after V1.
- [ ] Advanced comparisons/history, collaboration, institutional tenancy and billing implementation after product validation.
- [ ] Full publication workflow automation requires separate scope; automatic submission and acceptance prediction remain outside MVP.

## Human validation queue

See [DECISIONS](../architecture/DECISIONS.md) for IDs and owners. Highest-priority items are initial catalog scope, provider terms/authority, freshness/review workflow, the now-resolved package manager/OpenAPI/authentication choices (O-11/O-12). Before V1 release, also resolve source-passage coverage/rights, deterministic versus optional LLM response strategy, project validation/reselection UX, assistant grounding thresholds, web/mobile delivery scope, project/question quotas and privacy/hosting/retention/recovery choices. Transient Q&A is the default; persistent history needs separate validation. Inclusion of projects/workspace/assistant in V1 is settled, not an open scope question. Commercial decisions do not block the technical foundation but do block billing commitments.

Foundation, catalog, discovery and comparison are intermediate deliveries; lightweight projects/workspace and contextual journal assistance must pass their gates before MVP V1 release. Do not convert unresolved options into implied promises. Update relevant docs and tests when a decision becomes concrete. Keep acceptance probability, fabricated metadata, automatic submission and full manuscript analysis outside MVP.

Phase 2 schema/results and remaining ingestion work are recorded in [JOURNAL-DATA-MODEL-V1](JOURNAL-DATA-MODEL-V1.md). No journal API, seed catalog import, external fetching or provider data exists yet; synthetic fixtures are test-only.
