# Decision register

Status: design with corrected MVP V1 scope, 2026-10-11. “Required” reflects supplied project constraints. “Proposed” is a design recommendation awaiting human validation; implementation must record changes here.

## Required decisions

| ID | Decision | Reason and consequence |
| --- | --- | --- |
| D-01 | Laravel 13 is the canonical business API | One authorization/policy/data layer for web, mobile and future clients |
| D-02 | Public business endpoints use `/api/v1`; OpenAPI is the canonical contract | Client consistency and explicit evolution; existing scaffold route needs a documented migration |
| D-03 | PostgreSQL with pgvector is the target database | Relational provenance, search and vectors in one durable store; SQLite cannot underpin production functionality |
| D-04 | Next.js and Expo are presentation clients | No direct database or FastAPI access; business checks remain server-side |
| D-05 | FastAPI is internal and justified by Python-specific value | Optional specialist compute; no duplicate account/project/policy service |
| D-06 | Redis is target cache/queue infrastructure; private S3-compatible objects store files | Separate disposable cache from durable data; vendor remains open |
| D-07 | Journal facts originate in verifiable sources and support provenance | LLM knowledge cannot fill metadata gaps; conflicting assertions remain auditable |
| D-08 | Recommendations rank stored journals and scores mean relevance | Prevent invented candidates and misleading acceptance claims |
| D-09 | Sanctum supports web and mobile authentication | Foundation must verify cookie/CSRF and device-token paths |
| D-10 | Manuscripts/projects are private by default | Authorization, private storage, deletion and safe AI handling are release requirements |
| D-11 | French/English and internationalization are priorities | Source languages and interface locales remain distinct |
| D-12 | Full manuscript analysis and automated publication operations are outside MVP V1 | No full DOCX/PDF analysis, rewriting/formatting, compliance scoring, reference manager, billing implementation, collaboration/tenancy, submission or acceptance prediction |
| D-13 | MVP V1 extends selection into lightweight research projects and journal-specific workspace | Required data: title, abstract, scientific field, keywords, authors, shortlist, selected journal, status, created_at and updated_at; workflow `discovery` → `journal_selected` → `preparation` |
| D-14 | Overview, Requirements, Fees, Template and Assistant are required V1 tabs | Verified requirements and official guideline/template links support preparation without manuscript processing |
| D-15 | Simple contextual journal assistant is required in MVP V1 | Selected-journal verified facts and authoritative retrieval only; citations and explicit inability to verify; no generic writing chatbot |
| D-16 | Approved Foundation mobile token expiry is configurable, default 43200 minutes | SANCTUM_MOBILE_TOKEN_EXPIRATION is validated through Laravel config; explicit per-token expires_at; web sessions are independent |

## Proposed design decisions

| ID | Proposal | Rationale / validation needed |
| --- | --- | --- |
| P-01 | Start as a modular Laravel monolith with queue workers | Small coherent operational surface; split only when measurements justify it |
| P-02 | Web-first pilot; mobile consumes the same contract later | Limit delivery breadth; confirm user device needs |
| P-03 | Superseded: earlier finder-only MVP excluded projects/assistant | Replaced by user-confirmed D-13–D-15; billing implementation remains deferred |
| P-04 | Immutable source assertions with a reviewed current journal projection | Resolve conflicting sources without erasing history; validate review workload |
| P-05 | Lexical recommendation baseline and deterministic cited journal answers before optional semantic/LLM enrichment | Assistant functionality is mandatory; an LLM/FastAPI deployment is not; validate generated wording separately |
| P-06 | Optional constraints are preferences unless explicitly strict | Avoid unnecessary empty results; test terminology with researchers |
| P-07 | Official template links before mirroring documents | Reduce licensing and freshness burden; validate access reliability |
| P-08 | Owner-based individual records now, institutional membership later | No premature tenant subsystem; future migration must preserve isolation |

## Open decisions and required validation

| ID | Question | Responsible role | Needed before |
| --- | --- | --- | --- |
| O-01 | Pilot disciplines, geography, languages and catalog count? | Product + researcher advisors | Dataset selection |
| O-02 | Provider access method, terms, reuse rights, quotas and review responsibilities? | Data lead + legal reviewer | Provider ingestion/public redistribution |
| O-03 | How to define “International” and journal trust indicators without unsupported claims? | Product + academic advisors | Enabling those labels |
| O-04 | Freshness windows by fact/source and verification automation scope? | Data lead | Published catalog pilot |
| O-05 | Evaluator dataset, relevance and assistant-grounding/abstention thresholds, latency/load budgets and full workspace pilot success criteria? | Product + engineering | MVP release gate |
| O-06 | Web/mobile delivery scope, project/question/save quotas and future paid packaging/prices/billing provider? | Product/business | Client release or monetization, respectively |
| O-07 | Embedding model, supported languages, dimensions, provider/local processing and evaluation? | AI + privacy lead | Semantic deployment |
| O-08 | AI provider retention/training terms and private-data consent flow? | Privacy/security lead | Any external AI processing |
| O-09 | Hosting/store vendor, regions, jurisdiction, backup RPO/RTO and deletion deadlines? | Operations + privacy lead | Production or confidential file uploads |
| O-10 | Source-document and private-input retention periods, telemetry basis and policy text? | Privacy/legal + product | Production personal-data collection |
| O-11 | Resolved in Foundation V1: npm workspaces/root package-lock.json; OpenAPI 3.1.0; openapi-typescript and Redocly core validate/generate/check scripts | Engineering | Approved and implemented; future CI wiring remains separate |
| O-12 | Resolved in Foundation V1: remove unconsumed `/api/user`; register, SPA login/logout, device tokens/current revocation and me under `/api/v1`; CSRF framework exception documented | API + client leads | Approved and implemented |
| O-13 | Later manuscript formats, size limits and readiness criteria? | Product + scientific advisors | Post-MVP files/analysis scope commitment |
| O-14 | Which permitted guideline passages, supported-question fixtures and verification workflow cover the pilot journals? | Data + AI + academic advisors | V1 assistant release |
| O-15 | Deterministic retrieval alone or optional LLM wording; local/external processing and generation validation method? | Engineering + AI + privacy lead | V1 assistant implementation/release |
| O-16 | Detailed field validation, descriptive author fields, reselection/refresh UX and concurrency handling? | Product + API/client leads | V1 project/workspace implementation |
| O-17 | Is durable assistant history needed beyond transient V1 Q&A; if so, retention/deletion rules? | Product + privacy lead | Any persisted conversation capability |

No unresolved item authorizes inventing source facts, bypassing Laravel or collecting private inputs indefinitely. Foundation modeling can proceed against reviewed fixtures while provider/commercial choices are pending.

## MVP scope correction record

2026-10-11 — User-confirmed correction: discovery-only delivery was insufficient. Lightweight projects, selected-journal workspace and contextual Q&A now belong to MVP V1 (D-13–D-15), replacing P-03. Manuscript automation remains deferred. Consequences: project authorization/deletion, minimum workflow, requirement revision handling and assistant grounding/privacy tests become V1 release gates. API-first service boundaries are unchanged. Updated product, architecture, data, AI, backlog, README and supporting testing/security/privacy/schema documentation. V1 feature inclusion is settled; the open implementation choices above do not make projects or the assistant optional.

## Foundation V1 implementation record

2026-10-11 — User-approved decisions resolve O-11/O-12 without deleting history. npm workspaces/root package-lock.json replaces earlier package-manager uncertainty. OpenAPI 3.1.0 is canonical; pinned openapi-typescript/Redocly-core tooling validates and generates reproducible declarations. Laravel 13/Sanctum remains the business/auth layer; no JWT. Web uses HttpOnly first-party sessions with CSRF; mobile uses device-named 30-day Bearer tokens revocable individually. The no-consumer scaffold route is removed. PostgreSQL/pgvector development and test setup is implemented; no embedding columns. Redis, FastAPI, storage, client UI and all journal/project/assistant domain features are expressly deferred from this foundation task, not removed from full MVP V1. Tests and exact limitations are recorded in [FOUNDATION-V1](../development/FOUNDATION-V1.md).

## Foundation security correction decision

2026-10-11 — APPROVED by the user after the read-only security review: configurable mobile PersonalAccessToken expiration, default 43200 minutes (30 days), through `SANCTUM_MOBILE_TOKEN_EXPIRATION` and Laravel config. Invalid durations fail configuration loading; issuance sets explicit `expires_at`. Existing tokens retain their issuance expiry, and web session lifetime is unchanged. The earlier implementation record is retained as history: its hardcoded 30-day duration was an implementation choice, not separately approved at that time; D-16 supersedes that choice. Keeping bcrypt, enforcing its 72-byte limit and comparable missing-account hash verification are authorized Foundation corrections. No domain or infrastructure expansion is authorized. CI remains planned, not implemented.

## Phase 2 journal data decisions

2026-10-11 — Implemented within the user-authorized data-model phase: Laravel-native UUIDv7 (`HasUuids`) for all new domain records; existing user/token IDs remain unchanged. This resolves the previously open UUID/ULID choice without a new dependency. Material publication facts use explicit typed tables and field-level immutable assertions/source observations; append-only review states are pending/verified/stale/conflicting/rejected. No review means pending, not verified.

Journal revisions select assertion plus review IDs and seal once; complete immutable catalog snapshots retain journal revision mappings for later reproducibility. A small internal publication service serializes snapshots transactionally; no event sourcing, ingestion endpoint or automatic source winner selection. Source precedence is a documented review policy. All history-bearing deletions restrict rather than cascade; referenced source/vocabulary semantics cannot be rewritten. Mutable working identities are not implicitly verified public facts. The global normalized ISSN/eISSN ownership constraint is conservative; transfers/corrections require an explicit future identity-resolution policy, not automatic merging.

Public APIs/eligibility, provider rights/access, freshness/retention, controlled vocabularies and operational review remain unresolved ingestion/release decisions. Projects/workspace/assistant remain required full MVP capabilities and are unimplemented in this phase.

## Phase 3A controlled ingestion decisions

2026-10-11 — User-authorized implementation: a minimal transport-free provider mapping interface and a registry containing only the synthetic adapter. Laravel owns local bounded runs, PostgreSQL staging, validation, ISSN/eISSN ownership resolution, conflict detection, explicit operator reviews and transactional publication through Phase 2 revisions. No real provider, HTTP fetching, public route, Redis or FastAPI is introduced. Phase 3B starts with DOAJ separately.

Correction pass, 2026-10-11: use an immutable relational one-provider-per-source binding rather than adding another provider dimension to Phase 2 source observations. PostgreSQL checks run/provider consistency; a second provider must have a distinct source. This preserves existing provenance identities.

Run identity is source plus caller key with a canonical input hash; changed input under the same key is rejected. Source content fingerprints include the source URL and original permitted payload, separately from mapping version and retrieval time. Same-content observations preserve first retrieval; changed mapping versions create new staging items and reuse identical source assertions. Publication receipts prevent duplicate revisions for the same observation/explicit assertion selection. Changed selections require a new staging proposal, never rewriting a published receipt.

Unique-item counters derive from immutable membership (including whether an item was reused) and current outcomes; no incrementing counters are stored. Handled processing failures roll back the item before a separate sanitized failure-state transaction; retries keep earlier results and historical run diagnostics. Incompatible PostgreSQL text is rejected recursively before JSONB and quarantined with hash-only retention. The synthetic CLI stages at most a selected prefix of 1–100 records; it never transports provider data.

Valid identifiers reserve immutable ownership while assertions remain pending. Identifier collisions/type reassignment are quarantined; titles never merge identities. Repeat identifier observations retain their source-to-journal staging link without violating the existing unique ownership constraint. Different values in the same field/context create a conflicting incoming review and preserve prior evidence; automatic comparison never verifies or chooses a winner. Trusted CLI access relies on host/operator permissions, without a public admin/roles subsystem.

The new migration fixes a Phase 2 PostgreSQL parameter/column ambiguity when selecting identifier evidence, keeping the existing helper signature. Ingestion rollback removes its own tables/functions and retains this harmless catalog helper correction. Provider rights, real-payload retention, identity transfers, vocabularies and field freshness remain release decisions. [Phase 3A report](../development/JOURNAL-INGESTION-V1.md) documents behavior, limits and checks.

## Phase 3B bounded DOAJ decisions

2026-10-11 — Extend the existing registry/CLI with explicit DOAJ v4 retrieval, native Laravel HTTP, separate page validation and transport-free mapping. Fixed official HTTPS roots, redirects disabled, 1–100 records and bounded retries; no generic URL fetcher or provider-specific catalog writes. Official source commit/schema and terms are recorded in [DOAJ-ADAPTER-V1](../development/DOAJ-ADAPTER-V1.md). Live compatibility remains unverified after connection failure; this is not full-catalog support.

Exclude envelope retrieval time from run content hashes across providers: identical fetched content under the same key replays without resetting evidence freshness. Provider dates remain in raw content/fingerprint; changed content still requires a new caller key. Extend canonical proposals using existing publisher/language/subject models and raw field locators, without schema changes. Publisher country remains publisher metadata. DOAJ subjects use qualified flat names, not automatic taxonomy reconciliation. Superseded by the CSV extension below: the initial no-APC → not_applicable mapping was incorrect; explicit absence is now retained in fee basis/context with unknown scalar monetary values; multiple currencies stay raw/unknown. All claims remain pending/conflicting until explicit review.

## Change process

For a consequential change, record date, status, context, chosen option, alternatives, consequences and affected documents. Keep prior decisions visible when superseded. Validate API compatibility and privacy effects before adopting the new design. Do not change package versions or infrastructure merely to make documentation appear implemented.

## Phase 3B official CSV extension

2026-10-11 — User-authorized second acquisition transport, not a second provider/pipeline. API remains the default targeted search/refresh transport; official public CSV supports bounded bootstrap samples (1–100), without full synchronization. CSV normalizes export questions into the shared DOAJ bibjson representation. The stable `/csv` source may redirect once to the observed exact DOAJ S3 bucket and export filename pattern; signed URLs remain ephemeral transport details. No model/migration, dependency, canonical API or service boundary changes.

Correct the initial APC mapping: official DOAJ `has_apc=false` means no APC is charged, not not-applicable and not absence of every other fee. Keep the explicit flag/raw row plus `doaj_reported_no_apc` basis/context; scalar amount/currency remain unknown under the existing currency-required known-money invariant. Explicit absence participates in ingestion conflict detection even though its monetary availability is unknown. Unknown/absence/positive/not-applicable/waiver remain distinguishable; publication of this unknown monetary claim stays blocked by existing rules. A richer typed fee-presence model would require separate human review; it is not introduced here. Mapper version advances to `doaj-v4-csv-v2`; old evidence/reviews are unchanged.

## Phase 3B final metadata URL correction

2026-10-11 — Explicit user correction: metadata storage validation must be separate from fetch authorization. HTTP(S) metadata accepts query/fragment/percent encoding/path parameters. Shared URL normalization rejects controls, invalid UTF-8, unsupported schemes and credentials; original DOAJ observations remain untouched. Optional invalid DOAJ websites are omitted from proposals/claims while identity/title/ISSN evidence remains staged. Recursive U+0000/UTF-8 protections still apply before retention. Raw unmapped links do not authorize any request.

DOAJ API/CSV clients, exact official origins, controlled redirects, TLS, limits and timeouts are unchanged. CSV provenance stays the stable official `/csv` source, never the journal website or signed S3 download. Source-envelope validation remains distinct and conservative. Mapper is `doaj-v4-csv-v3` so old quarantined items can be remapped without modifying immutable observations or claiming a fresh retrieval. APC model and semantics are unchanged.
