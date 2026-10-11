# Implementation backlog

Status: Foundation V1, Phase 2 journal data/provenance and Phase 3A controlled synthetic ingestion implemented; Phase 3B bounded DOAJ adapter is implemented with offline tests; live compatibility and other providers/later MVP slices remain pending. Follow [ROADMAP](../product/ROADMAP.md) and preserve existing user changes.

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
| 7 | Completed Phase 3A: source ingestion framework using synthetic adapter/fixtures | PostgreSQL-tested durable staging, idempotency, dry-run, identifier resolution, conflicts, explicit review/publication and field provenance |
| 8 | Bounded DOAJ adapter implemented; validate live compatibility/production policy, then 3C Crossref, 3D OpenAlex, 3E official sources | Versioned mappings, synthetic/permitted recorded fixtures, bounded transport and provider-specific evidence; no automatic verification |
| 9 | Add targeted CI and sanitized operational metrics | API/contract checks and ingestion counters demonstrate foundation gate |

Technical parts of steps 1–5 are complete, with pilot decisions still to validate. Step 6 is complete, including UUIDv7 convention; step 7 (3A) is complete; step 8 has a bounded DOAJ implementation; deployed-schema/recorded-fixture and production activation gates remain pending. Targeted checks are executable; CI wiring in step 9 remains pending. See [FOUNDATION-V1](FOUNDATION-V1.md) for actual verification. Vector similarity, Python deployment and private manuscript storage are not prerequisites to importing sourced metadata. Keep lightweight project and assistant implementation in the later V1 slices below; do not add those features during foundation.

Before public catalog publication, approve provider rights, field authority, refresh windows and data-quality review. While those choices are pending, development can use reviewed synthetic/permitted fixtures.

## Phase 3 — ordered ingestion slices

3A and bounded 3B are implemented; live DOAJ mapping/recorded fixture remain unverified. 3C–3E remain planned. See [JOURNAL-INGESTION-PLAN](JOURNAL-INGESTION-PLAN.md) for boundaries and exit gates.

- [x] **3A — Framework:** provider adapter boundary, durable staging, idempotency, validation/quarantine, explicit review/publication and provenance; synthetic fixtures, recursive incompatible-text quarantine, bounded `--limit`, unique-item counters, sanitized interruption/resume, provider/source binding and real PostgreSQL partial-write rollback tests; no external calls.
- [x] **3B — DOAJ adapter:** official-source-checked v4 API and official CSV transports/shared mapping into 3A, bounded acquisition, synthetic fixtures and offline PostgreSQL tests; see [DOAJ-ADAPTER-V1](DOAJ-ADAPTER-V1.md).
- [x] Verify official CSV schema/download and repeat the bounded three-row CSV dry-run after separating metadata storage from fetch policy: 3 observed, 3 valid/mapped, 0 quarantined, exit 0; all 37 table counts unchanged.
- [ ] Broaden real-record mapping coverage beyond the successful three-row CSV sample, complete live API verification, add permitted recorded fixture and approve production retention/freshness/review operations.
- [ ] Decide separately whether/how to publish a structured currency-free DOAJ no-APC fact; current basis/context preserves explicit absence without redesigning canonical money.
- [ ] **3C — Crossref:** identity enrichment with collision/transfer review; no current submission-rule authority.
- [ ] **3D — OpenAlex:** reviewed subjects/discovery enrichment; derived bibliographic signals remain distinct from journal scope or accepted languages.
- [ ] **3E — Official journal/publisher sources:** authoritative requirements/APC/template evidence and links; controlled fetching later, behind access and SSRF/resource-limit gates.

Each provider reuses 3A and Phase 2 evidence/revision primitives. New provider observations never erase prior evidence or silently merge identities. Fetching, normalization and validation do not verify facts.

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

Phase 2 schema/results and remaining ingestion work are recorded in [JOURNAL-DATA-MODEL-V1](JOURNAL-DATA-MODEL-V1.md). Phase 3A now supports controlled local synthetic batches through Artisan; see [JOURNAL-INGESTION-V1](JOURNAL-INGESTION-V1.md). DOAJ retrieval now feeds the same framework; the failed live probe persisted no seed catalog. No public journal API or journal-site fetching exists.
