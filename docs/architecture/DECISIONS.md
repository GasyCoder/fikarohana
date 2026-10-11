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
| O-11 | Package manager, OpenAPI version/generator and contract CI tools? | Engineering | Foundation dependency/tool changes |
| O-12 | `/api/user` migration and exact web/mobile authentication endpoints? | API + client leads | Versioned auth implementation |
| O-13 | Later manuscript formats, size limits and readiness criteria? | Product + scientific advisors | Post-MVP files/analysis scope commitment |
| O-14 | Which permitted guideline passages, supported-question fixtures and verification workflow cover the pilot journals? | Data + AI + academic advisors | V1 assistant release |
| O-15 | Deterministic retrieval alone or optional LLM wording; local/external processing and generation validation method? | Engineering + AI + privacy lead | V1 assistant implementation/release |
| O-16 | Detailed field validation, descriptive author fields, reselection/refresh UX and concurrency handling? | Product + API/client leads | V1 project/workspace implementation |
| O-17 | Is durable assistant history needed beyond transient V1 Q&A; if so, retention/deletion rules? | Product + privacy lead | Any persisted conversation capability |

No unresolved item authorizes inventing source facts, bypassing Laravel or collecting private inputs indefinitely. Foundation modeling can proceed against reviewed fixtures while provider/commercial choices are pending.

## MVP scope correction record

2026-10-11 — User-confirmed correction: discovery-only delivery was insufficient. Lightweight projects, selected-journal workspace and contextual Q&A now belong to MVP V1 (D-13–D-15), replacing P-03. Manuscript automation remains deferred. Consequences: project authorization/deletion, minimum workflow, requirement revision handling and assistant grounding/privacy tests become V1 release gates. API-first service boundaries are unchanged. Updated product, architecture, data, AI, backlog, README and supporting testing/security/privacy/schema documentation. V1 feature inclusion is settled; the open implementation choices above do not make projects or the assistant optional.

## Change process

For a consequential change, record date, status, context, chosen option, alternatives, consequences and affected documents. Keep prior decisions visible when superseded. Validate API compatibility and privacy effects before adopting the new design. Do not change package versions or infrastructure merely to make documentation appear implemented.
