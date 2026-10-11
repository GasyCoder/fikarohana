# Capability roadmap

Status: initial design. Phases are dependency gates, not calendar commitments. [TODO](../development/TODO.md) holds actionable foundation work.

| Order | Phase | Scope | Exit gate |
| --- | --- | --- | --- |
| 1 | Foundation | PostgreSQL/pgvector readiness, Redis, versioned API, Sanctum, OpenAPI, CI and privacy conventions | API/auth/contract checks on PostgreSQL; infrastructure choices recorded |
| 2 | Journal data model | Identity, provenance assertions, revisions and verified projection | Model handles missing facts, conflicts and duplicate identifiers |
| 3 | Journal ingestion | Seed catalog, provider boundary, staging/review, idempotent runs | Source access/rights validated; repeat and failure tests; coverage report |
| 4 | Journal discovery | Public profiles at minimum identity level, filters, lexical retrieval | Validated queries and honest empty-result behavior |
| 5 | Recommendation V1 | Configurable rank, 3–5 shortlist, reasons, evaluation; semantic enrichment if justified | Researcher-reviewed benchmark and reproducible outputs |
| 6 | Journal profiles | Rich evidence, guidelines, official templates, freshness visibility | Field-level citations and source conflict handling |
| 7 | Comparison and pilot release | Basic comparison, account saves, French/English journey | MVP acceptance and privacy/security gates passed |
| 8 | Research projects | Selected journal, snapshots, workspace, versioned private files | Ownership, deletion, file security and snapshot-change tests |
| 9 | Contextual journal assistant | Permission-aware document retrieval and cited answers | Grounding, prompt-injection and private-data evaluation passed |
| 10 | Manuscript analysis | DOCX/PDF extraction, rule checks, declarations, reference/length checks | Supported formats and limits agreed; rule/evidence traceability proven |
| 11 | Advanced publication workflow | Rich readiness, collaboration, integrations, institutional features | User demand, tenant model and operational capacity validated |

Full mobile delivery, commercial subscriptions and institutional tenancy require their own validated scope; they are not implied by completing the web MVP. Related articles can be added when licensed bibliographic coverage supports the workspace.

## Dependencies and experiments

Evaluate semantic retrieval against the lexical baseline before operating FastAPI for embeddings. Evaluate journal document coverage before building the assistant. Evaluate extraction accuracy on permitted sample manuscripts before promising compliance checks. Introduce billing only after plan boundaries and payment provider choices are approved.

Prioritize freshness, evidence and usability over catalog size. If strict constraints frequently produce empty results, expand relevant verified coverage or improve user guidance; do not relax constraints silently.

## Review checkpoints

At each gate, review user evidence, data-quality gaps, operating cost, privacy impact and unresolved decisions. Update the decision register and affected specifications before implementation diverges. No dates or paid feature commitments are implied by this initial roadmap.
