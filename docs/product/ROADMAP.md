# Capability roadmap

Status: corrected MVP V1 scope. Phases 1–9 deliver V1; phases 10 onward are later capabilities. Phases are dependency gates, not calendar commitments. [TODO](../development/TODO.md) holds actionable foundation work.

| Order | Phase | Scope | Exit gate |
| --- | --- | --- | --- |
| 1 | Foundation | PostgreSQL/pgvector readiness, Redis, versioned API, Sanctum, OpenAPI, CI and privacy conventions | API/auth/contract checks on PostgreSQL; infrastructure choices recorded |
| 2 | Journal data model | Identity, provenance assertions, revisions and verified projection | Model handles missing facts, conflicts and duplicate identifiers |
| 3 | Journal ingestion | 3A framework → 3B DOAJ → 3C Crossref identity → 3D OpenAlex subjects/discovery → 3E official requirements/APC/templates | Synthetic framework tests; provider rights/mappings and permitted fixtures; reviewed publication, repeat/failure tests and coverage report; 3E-A approved snapshots, then separately reviewed editorial extraction |
| 4 | Journal discovery | Public profiles at minimum identity level, filters, lexical retrieval | Validated queries and honest empty-result behavior |
| 5 | Recommendation V1 | Configurable rank, 3–5 shortlist, reasons, evaluation; semantic enrichment if justified | Researcher-reviewed benchmark and reproducible outputs |
| 6 | Journal profiles | Rich evidence, guidelines, official templates, freshness visibility | Field-level citations and source conflict handling |
| 7 | Comparison and selection (V1) | Basic comparison, account saves and explicit journal choice | Selection and source-aware comparison checks; intermediate delivery only |
| 8 | Lightweight projects/workspace (V1) | Minimum project metadata, `discovery` → `journal_selected` → `preparation`, selected revision; Overview, Requirements, Fees, Template, Assistant | Owner-scoped CRUD/deletion, transition and revision-change checks; no manuscript files |
| 9 | Contextual journal assistant and V1 release | Verified fact lookup and authoritative journal retrieval, cited answers and explicit inability to verify | Grounding/abstention, prompt-injection, privacy and complete bilingual workspace journey pass |
| 10 | Manuscript analysis | DOCX/PDF extraction, rule checks, declarations, reference/length checks | Supported formats and limits agreed; rule/evidence traceability proven |
| 11 | Advanced publication workflow | Rich readiness, collaboration, integrations, institutional features | User demand, tenant model and operational capacity validated |

MVP V1 release occurs after phase 9; projects/workspace and the simple assistant are required, not post-MVP extensions. Full mobile delivery, commercial subscriptions and institutional tenancy require their own validated scope; they are not implied by completing the web MVP. Related Articles/Manuscript tabs, manuscript uploads/versioning, full DOCX/PDF analysis, rewriting, formatting, compliance scoring, reference management, collaboration, billing implementation, automatic submission, acceptance prediction and full workflow automation stay outside V1.

## Dependencies and experiments

The [Phase 3 ingestion plan](../development/JOURNAL-INGESTION-PLAN.md) defines the ordered provider slices and their exit gates. 3A is implemented with local synthetic data; bounded 3B DOAJ code/offline tests are implemented, with live schema compatibility/recorded fixture still unverified. 3C Crossref bounded identity enrichment for existing candidates is implemented; 3D bounded OpenAlex taxonomy enrichment is implemented; 3E-A controlled official-page acquisition is implemented; editorial extraction remains planned. See [OpenAlex adapter](../development/OPENALEX-ADAPTER-V1.md). See [Crossref adapter](../development/CROSSREF-ADAPTER-V1.md). DOAJ is the first real provider; Crossref and OpenAlex enrich identity/discovery rather than establish current submission rules. Explicit official-page approval precedes each controlled fetch; proposed editorial extraction/review follows separately. Framework work can proceed with synthetic fixtures while source-access decisions remain open.

Evaluate semantic retrieval against the lexical baseline before operating FastAPI for embeddings. Validate permitted authoritative journal evidence and supported-question coverage before the V1 assistant release. Begin with verified facts and bounded passage retrieval; semantic RAG and an LLM are optional implementation choices, not conditions for including the assistant. Evaluate extraction accuracy on permitted sample manuscripts before promising compliance checks. Introduce billing only after plan boundaries and payment provider choices are approved.

Prioritize freshness, evidence and usability over catalog size. If strict constraints frequently produce empty results, expand relevant verified coverage or improve user guidance; do not relax constraints silently.

## Review checkpoints

At each gate, review user evidence, data-quality gaps, operating cost, privacy impact and unresolved decisions. Update the decision register and affected specifications before implementation diverges. No dates or paid feature commitments are implied by this initial roadmap.
