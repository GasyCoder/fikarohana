# Implementation backlog

Status: Foundation V1, Phase 2 journal data/provenance and Phase 3A controlled synthetic ingestion implemented; Phase 3B bounded DOAJ, Phase 3C Crossref identity and Phase 3D OpenAlex taxonomy adapters are implemented with offline tests; live compatibility and other providers/later MVP slices remain pending. Follow [ROADMAP](../product/ROADMAP.md) and preserve existing user changes.

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
| 8 | Bounded DOAJ, Crossref and OpenAlex adapters implemented; validate live coverage/production policy, then 3E official sources | Versioned mappings, synthetic/permitted recorded fixtures, bounded transport and provider-specific evidence; no automatic verification |
| 9 | Add targeted CI and sanitized operational metrics | API/contract checks and ingestion counters demonstrate foundation gate |

Technical parts of steps 1–5 are complete, with pilot decisions still to validate. Step 6 is complete, including UUIDv7 convention; step 7 (3A) is complete; step 8 has bounded DOAJ, Crossref and OpenAlex implementations; deployed-schema/recorded-fixture and production activation gates remain pending. Targeted checks are executable; CI wiring in step 9 remains pending. See [FOUNDATION-V1](FOUNDATION-V1.md) for actual verification. Vector similarity, Python deployment and private manuscript storage are not prerequisites to importing sourced metadata. Keep lightweight project and assistant implementation in the later V1 slices below; do not add those features during foundation.

Before public catalog publication, approve provider rights, field authority, refresh windows and data-quality review. While those choices are pending, development can use reviewed synthetic/permitted fixtures.

## Phase 3 — ordered ingestion slices

3A and bounded 3B are implemented; live DOAJ mapping/recorded fixture remain unverified. 3C existing-candidate Crossref enrichment is implemented; 3D OpenAlex enrichment is implemented; 3E-A approved official-page acquisition is implemented; bounded deterministic extraction is implemented in 3E-B; wider interpretation remains planned. See [JOURNAL-INGESTION-PLAN](JOURNAL-INGESTION-PLAN.md) for boundaries and exit gates.

- [x] **3A — Framework:** provider adapter boundary, durable staging, idempotency, validation/quarantine, explicit review/publication and provenance; synthetic fixtures, recursive incompatible-text quarantine, bounded `--limit`, unique-item counters, sanitized interruption/resume, provider/source binding and real PostgreSQL partial-write rollback tests; no external calls.
- [x] **3B — DOAJ adapter:** official-source-checked v4 API and official CSV transports/shared mapping into 3A, bounded acquisition, synthetic fixtures and offline PostgreSQL tests; see [DOAJ-ADAPTER-V1](DOAJ-ADAPTER-V1.md).
- [x] Verify official CSV schema/download and repeat the bounded three-row CSV dry-run after separating metadata storage from fetch policy: 3 observed, 3 valid/mapped, 0 quarantined, exit 0; all 37 table counts unchanged.
- [ ] Broaden real-record mapping coverage beyond the successful three-row CSV sample, complete live API verification, add permitted recorded fixture and approve production retention/freshness/review operations.
- [ ] Decide separately whether/how to publish a structured currency-free DOAJ no-APC fact; current basis/context preserves explicit absence without redesigning canonical money.
- [x] **3C — Crossref:** bounded explicit existing-candidate ISSN enrichment, immutable source/staging evidence, conflict quarantine/review and PostgreSQL offline tests; see [CROSSREF-ADAPTER-V1](CROSSREF-ADAPTER-V1.md). No APC/submission-rule authority or automatic identifier transfer.
- [ ] Validate operational Crossref contact/quota coordination, publisher disambiguation, freshness/review/retention and broader permitted real-record coverage before production activation.
- [x] **3D — OpenAlex:** bounded existing-candidate Source enrichment, source-qualified versioned topic/subfield/field/domain hierarchy, retained conflicts and PostgreSQL tests; see [OPENALEX-ADAPTER-V1](OPENALEX-ADAPTER-V1.md). Derived scholarly signals are not authoritative editorial scope.
- [ ] Approve OpenAlex production retention/freshness/review, quota coordination, publisher-ID disambiguation and taxonomy reconciliation; broaden permitted real-record coverage.
- [x] **3E-A — Final security correction:** guarded approval mass assignment, fresh approval before every GET, lock-before-check rollback with real PostgreSQL writer, native local transfer/CRLF/entity/proxy/pin regressions; no new live request.
- [x] **3E-A — Official source foundation:** explicit registration/approval, DNS/IP pinning, bounded snapshots, freshness/change detection and PostgreSQL security tests; see [OFFICIAL-SOURCES-V1](OFFICIAL-SOURCES-V1.md).
- [x] **3E-B — Deterministic editorial proposals:** stored snapshots, bounded inert parsing, rule/version/locator evidence, idempotent typed assertions and PostgreSQL tests; see [EDITORIAL-EXTRACTION-V1](EDITORIAL-EXTRACTION-V1.md).
- [ ] **3E later:** pilot applicability/conflict review and approved requirements/APC/template publication; licensed retention/purge, origin-wide operations and freshness policies.

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

Phase 2 schema/results and remaining ingestion work are recorded in [JOURNAL-DATA-MODEL-V1](JOURNAL-DATA-MODEL-V1.md). Phase 3A now supports controlled local synthetic batches through Artisan; see [JOURNAL-INGESTION-V1](JOURNAL-INGESTION-V1.md). DOAJ retrieval now feeds the same framework; the failed live probe persisted no seed catalog. No public journal API exists. Journal-site fetching is confined to explicitly approved Phase 3E-A sources, with a separate stored-snapshot proposal extractor and no automatic verification/publication.

- [x] **3E-B semantic correction:** contextual qualifier abstention, explicit maximum/article scope, deleted markup exclusion, negative APC conflict gates, fragment-only link rejection and versioned bounded context provenance. See [correction report](EDITORIAL-EXTRACTION-V1.md).


- [x] **3E-C frozen validation:** human-approved six positive facts; V2 baseline preserved (0 TP/0 FP/6 FN), no scope generalization or hidden acquisition failures.
- [x] **3E-C2 deterministic remediation:** V3 candidate budgets, bounded link channel, general no-APC/style/abstract grammar; same-snapshot 6 TP/0 FP/0 FN, PostgreSQL regressions and separate post-fix report.
- [ ] **Independent holdout before Phase 4 exposure:** operator approval of exact new official URLs, safe bounded acquisition, human gold freeze, then unchanged V3 evaluation. Current development-set success does not resolve NOT_READY. See [holdout approval gate](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#independent-holdout-approval-gate).


- [x] Freeze human JMLR gold and evaluate unchanged editorial-v3 offline: 0 TP / 0 FP / 2 FN, 3 OOS; generic publication-fee absence never converted to APC. See [JMLR holdout](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#jmlr-independent-holdout--frozen-human-gold-and-unchanged-v3-benchmark).
- [ ] Resolve independent coverage before Phase 4: JMLR recall remains 0%; BMC policy hold means fewer than two usable journals and fewer than three-to-five validated positives. Further remediation/versioning or acquisitions require a separate task; retain frozen holdout results and NOT_READY.


- [x] Phase 3E-C3: V4 general abstract/link grammar, PostgreSQL provenance compatibility and synthetic regressions; previous development result remains 6 TP / 0 FP / 0 FN.
- [ ] JMLR context coverage remains unresolved (V4: 0 TP / 0 FP / 2 FN). Retain conservative unknown/oversize qualifier rejection; any future context change needs explicit design and negative regressions.
- [ ] New V4 independent holdout: [exact candidates](validation/editorial-v4-new-holdout-candidates.json) need human approval → unchanged 3E-A acquisition → candidate gold → human validation → V4 evaluation. Two static usable journals and 4–6 supported positives are targets, not verified facts. JMLR is now development/remediation data; BMC stays DO_NOT_APPROVE. Phase 4 NOT_READY.


- [x] C3 independent holdout operator authorization received (`florent-3ec3-holdout-2026-10-13`); existing 3E-A acquired Confluentes only. JSS DNS link-local answer blocks acquisition without bypass.
- [ ] [Independent V4 candidate gold](validation/editorial-v4-independent-holdout-v1.json): seven observations awaiting human scope/disposition/coverage validation, no extraction or metrics. Only one acquired journal; two-journal / 4–6-positive target remains unmet. V4 strictly frozen, Phase 4 NOT_READY.


- [x] Human Confluentes gold frozen and unchanged V4 independent benchmark recorded: **0 TP / 0 FP / 1 FN / 6 OOS**; no retuning. [Preserved result](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#confluentes-independent-v4-holdout--human-gold-frozen-and-benchmark-complete).
- [ ] Broader independent validation still needed: only one acquired/evaluated journal, one positive, recall 0%; JSS acquisition blocked. New URLs require separate operator approval and gold review. Phase 4 remains NOT_READY; do not alter V4 before presenting the frozen result.


- [x] Phase 3E-C4 V5 neutral-versus-unsafe context remediation, scoped example boundaries, action-link qualification and bounded French unconditional-English extraction; complete PostgreSQL suites pass.
- [x] Six-page development replay: PLOS 4 / JOSS 2 / JMLR 2 / Confluentes 1 TP, zero FP/FN; all prior V2–V4 history/gold retained. JMLR/Confluentes now development data, not independent V5 evidence.
- [x] First V5 independent acquisition attempt: [four exact pages / three journals](validation/editorial-v5-holdout-candidates-v1.json) conditionally authorized and outcomes recorded below. Discovery opportunities did not produce usable gold; no independent V5 benchmark executed. Original manifest retained unchanged.


- [x] Operator V5 holdout authorization recorded (`florent-3ec4-v5-holdout-2026-10-13`). Actual 3E-A attempts: both Biology Open pages blocked HTTP 403; Oxford guidelines DO_NOT_APPROVE under automated-access terms; Discrete acquired only as empty static app shell.
- [ ] [V5 independent acquisition packet](validation/editorial-v5-independent-holdout-v1.json) has no usable editorial candidates, no frozen gold and no extraction/metrics. Await whole-corpus human review before any benchmark/replacement. Do not use search snippets, browser fallback or scripts to invent annotations. V5 unchanged; Phase 4 NOT_READY.

### Phase 3E-C5 — independent holdout acquisition preflight

**Independent editorial-v5 holdout validation pending.** Phase 4 production exposure of editorial extraction is not fully validated. C5 discovery is stopped; the proposed eight URLs have not been acquired.

- [x] Bounded public discovery produced an [11-page / 11-journal candidate pool](validation/editorial-v5-holdout-preflight-v1.json), with exactly **eight URLs / eight journals** proposed for human approval. Selection ranks acquisition suitability only; no gold, predictions, extractor invocation or 3E-A registration/approval/fetch.
- [x] V5 development success remains **9/9** (PLOS ONE 4, JOSS 2, JMLR 2, Confluentes 1; zero development FP/FN). These are development journals, not independent V5 evidence. First independent attempt is an **acquisition/corpus failure**, not an extractor benchmark result.
- [x] All fourteen recorded `editorial-v5` / `editorial-dom-v5` source hashes match the original freeze. Prior validation artifacts and 3E-A security remain unchanged. Scientific Reports and Sensors are also excluded because prior development records contain them.
- [ ] Human operator must approve the exact new eight-URL batch and applicable officiality/access/robots/internal-retention basis. Previous C4 authorization does not cover C5 URLs or reserves. DNS/TLS/SSRF, same-origin redirects, 256 KiB and raw static usability remain unverified; reader visibility is not 3E-A eligibility.
- [ ] Later authorized acquisition: proceed in the proposed order until **three distinct journals with usable static snapshots AND at least six plausible candidate positive facts** exist, or all approved URLs are exhausted. Record every attempted outcome; no automatic retry, crawling, secondary resources or replacement URLs.
- [ ] After acquisition stops, prepare and human-freeze gold for the complete acquired corpus before one unchanged V5 whole-corpus evaluation. No page-by-page benchmark/remediation. **Phase 4 NOT_READY.**

## Phase 4A — public reviewed-catalog discovery API

- [x] Anonymous `/api/v1/journals`, journal detail and `/api/v1/journal-recommendations`; immutable published catalog membership/current usable selections, strict unknown-safe filters and configurable deterministic metadata relevance. See [Phase 4A](JOURNAL-DISCOVERY-API-V1.md).
- [x] OpenAPI/generated contracts and PostgreSQL route/ranking/publication/validation regressions; no frontend, external request or saved manuscript input.
- [ ] Phase 4B UI remains separate; verify actual reviewed catalogue coverage and measured production scale before broad release.
- [ ] editorial-v5 independent holdout validation remains pending; Phase 4A does not depend on unvalidated V5 extraction. No holdout work resumed.
