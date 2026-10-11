# Decision register

Status: initial design, 2026-10-11. “Required” reflects supplied project constraints. “Proposed” is a design recommendation awaiting human validation; implementation must record changes here.

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
| D-12 | Full manuscript analysis is outside MVP | Establish reliable discovery and sourced data first |

## Proposed design decisions

| ID | Proposal | Rationale / validation needed |
| --- | --- | --- |
| P-01 | Start as a modular Laravel monolith with queue workers | Small coherent operational surface; split only when measurements justify it |
| P-02 | Web-first pilot; mobile consumes the same contract later | Limit delivery breadth; confirm user device needs |
| P-03 | First MVP includes basic comparison and account saves, excludes projects/assistant/billing | Validate discovery-to-selection value; confirm pilot scope |
| P-04 | Immutable source assertions with a reviewed current journal projection | Resolve conflicting sources without erasing history; validate review workload |
| P-05 | Lexical baseline before optional semantic enrichment; deterministic reasons before optional LLM | Prove relevance improvements and avoid early provider cost/privacy dependencies |
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
| O-05 | Evaluator dataset, relevance threshold, latency/load budgets and pilot success criteria? | Product + engineering | MVP release gate |
| O-06 | Web-first scope, save limits, free/paid boundary, prices and billing provider? | Product/business | Client release or monetization, respectively |
| O-07 | Embedding model, supported languages, dimensions, provider/local processing and evaluation? | AI + privacy lead | Semantic deployment |
| O-08 | AI provider retention/training terms and private-data consent flow? | Privacy/security lead | Any external AI processing |
| O-09 | Hosting/store vendor, regions, jurisdiction, backup RPO/RTO and deletion deadlines? | Operations + privacy lead | Production or confidential file uploads |
| O-10 | Source-document and private-input retention periods, telemetry basis and policy text? | Privacy/legal + product | Production personal-data collection |
| O-11 | Package manager, OpenAPI version/generator and contract CI tools? | Engineering | Foundation dependency/tool changes |
| O-12 | `/api/user` migration and exact web/mobile authentication endpoints? | API + client leads | Versioned auth implementation |
| O-13 | Later manuscript formats, size limits, readiness criteria and journal-change workflow? | Product + scientific advisors | Projects/analysis scope commitment |

No unresolved item authorizes inventing source facts, bypassing Laravel or collecting private inputs indefinitely. Foundation modeling can proceed against reviewed fixtures while provider/commercial choices are pending.

## Change process

For a consequential change, record date, status, context, chosen option, alternatives, consequences and affected documents. Keep prior decisions visible when superseded. Validate API compatibility and privacy effects before adopting the new design. Do not change package versions or infrastructure merely to make documentation appear implemented.
