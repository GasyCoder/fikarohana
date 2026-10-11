# MVP delivery boundary

Status: initial design. The first MVP validates sourced discovery and journal selection, not the entire publication workflow.

## Included

1. A reviewed, bounded journal catalog with stable identifiers, source records, field-level verification and official links.
2. Anonymous discovery from topic/title/abstract or field, with optional keywords and publication preferences.
3. A candidate-retrieval and configurable ranking pipeline returning up to five eligible journals, with deterministic reasons and visible missing data. Semantic matching is introduced only after a measured improvement over the lexical baseline.
4. Profiles with verified available identity, scope, languages, fees, OA, article types, indexation, guidelines/template links and timestamps. Detailed requirements may be incomplete and must say so.
5. Basic comparison of 2–3 journals.
6. Sanctum-backed accounts, saved journals and saved searches with ownership checks and deletion.
7. French and English UI foundations and the same `/api/v1` contract for all clients.
8. Internal ingestion commands/jobs and review tools sufficient to maintain the pilot catalog; a custom administration UI is unnecessary initially.

Web is the first proposed release client. Keep mobile scaffold intact and verify contract usability for mobile; a full mobile release is a separate gate, subject to human validation. No client-specific business API is permitted.

## Excluded

Research projects and manuscript files; full journal workspace; assistant/chat; DOCX/PDF analysis; compliance/readiness scoring; automatic article generation/submission; collaboration/institutional tenancy; billing; advanced saved comparisons; exhaustive catalog coverage; arbitrary live crawling during a discovery request.

LLM explanations are optional after baseline evaluation and privacy approval. The MVP must remain useful without an LLM or an active FastAPI deployment.

## Delivery slices

| Slice | Deliverable | Exit evidence |
| --- | --- | --- |
| Foundation | PostgreSQL, versioned API, authentication design, OpenAPI, error/privacy conventions | PostgreSQL-backed smoke checks; contract validation; cookie/token flow tests |
| Journal model | Identity, assertions/evidence, requirements/documents and quality states | Unknown/zero/conflict distinctions and identifier merges tested |
| Ingestion | Reviewed seed import and first approved provider adapter | Repeat import does not duplicate records; partial failure preserves published data |
| Discovery | Filters and bounded candidate retrieval | Strict unsupported criteria return partial/empty results with reasons |
| Recommendation V1 | Versioned scoring and deterministic explanations | Judged fixture and reproducibility report; no acceptance language |
| Profiles | Sourced facts, official guidelines/templates | Every material fact has evidence; stale/conflicting values visible |
| Comparison/accounts | Basic comparison, saves and deletion | Cross-user access denial; bilingual end-to-end pilot journey |

These slices are sequential capabilities, not independently deployable microservices. The roadmap may gate semantic enrichment separately.

## Pilot gate

- Human reviewers approve source rights, catalog scope and a benchmark set representative of French and English research inputs.
- Every published journal has a sourced identity and enough scope evidence for retrieval; other missing fields do not silently become facts.
- Recommendation output is drawn exclusively from active catalog IDs and honors strict filters.
- Fees display amount, currency, fee type, applicability and verification state; unknown fees cannot qualify as free.
- Users can inspect sources, compare, save and delete; personal data is isolated.
- Relevant checks in [TESTING](../development/TESTING.md) pass; basic monitoring, backup restore and incident handling are demonstrated before production.

Catalog count, relevance target, request quotas and load budget remain proposed inputs to this gate. Do not substitute arbitrary numbers for a validated pilot design.
