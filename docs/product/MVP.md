# MVP V1 delivery boundary

Status: corrected design scope. MVP V1 guides researchers beyond journal recommendation into a lightweight, journal-specific research workspace. This specifies planned behavior, not implemented features.

## Target journey

Research intent → Journal discovery → Recommendation → Comparison → Journal selection → Research project → Journal-specific workspace → Requirements/templates → Contextual journal assistant.

## Included

1. Journal discovery from topic/title/abstract or field, with optional keywords and publication preferences; anonymous browsing remains supported.
2. Journal recommendation from a reviewed, bounded catalog: up to five eligible journals, configurable ranking, deterministic reasons, visible uncertainty and provenance. Semantic enrichment needs evaluation; scores mean relevance, never acceptance probability.
3. Basic comparison of 2–3 journals with scope, languages, applicable fees, OA, article types, indexation and source freshness.
4. Explicit journal selection carried into an authenticated project, not merely a saved bookmark.
5. Lightweight private research projects with creation, edit, resume and deletion, validated ownership and minimal workflow.
6. Journal-specific workspace with Overview, Requirements, Fees, Template and Assistant.
7. Verified available journal requirements with field evidence, applicability, selected revision, freshness and explicit unknown/conflict states.
8. Official guideline/template links and official submission URL where verified. Link first; mirroring needs rights. Missing links/requirements are acknowledged, not invented.
9. Simple contextual assistant answering selected-journal questions from verified Fikarohana data and retrieved authoritative sources, with citations and explicit abstention.

Supporting V1 scope includes Sanctum accounts, saved journals/searches, French/English UI, a shared `/api/v1` OpenAPI contract, internal ingestion/review and safe operational controls. Laravel 13 remains canonical; Next.js and Expo are clients; PostgreSQL + pgvector, Redis and private S3-compatible storage retain their established roles. FastAPI remains optional specialized computation.

Web is the proposed first release client; full mobile delivery needs validation. All clients share the same API and policies.

## Minimum project and workflow

| Field | Purpose |
| --- | --- |
| `title` | User-provided project title |
| `abstract` | Private research summary, editable during preparation |
| `scientific_field` | User-provided/confirmed field; no fabricated classification |
| `keywords` | Ordered/listed research keywords |
| `authors` | Descriptive author entries; no collaboration/invitations |
| `journal_shortlist` | References to candidate journal records |
| `selected_journal` | Selected journal reference, nullable before selection |
| `status` | `discovery`, `journal_selected` or `preparation` |
| `created_at`, `updated_at` | Server-managed UTC timestamps |

Owner and project ID are server-enforced metadata. Selection also references the journal requirements revision. Minimum workflow: `discovery` → `journal_selected` → `preparation`. Selection is required before preparation; journal changes reset the relevant context/status. No compliance/readiness claim follows from status. Full transition/revision behavior is defined in [PRODUCT](PRODUCT.md).

## Minimum workspace

Overview edits project metadata and selection. Requirements displays sourced rules and guideline links. Fees explains APC/other known fees and conditions. Template provides official applicable links. Assistant answers selected-journal questions. Before selection, journal-specific tabs ask the user to choose a journal.

Assistant question examples: French acceptance, APC, accepted article types, abstract requirements, reference style, official template and official submission URL. Each factual answer is evidence-backed. If evidence is unavailable or insufficient, explicitly say the information could not be verified. No invented journal requirements, generic writing chat or project-wide manuscript retrieval. Transient Q&A is sufficient; durable conversation history is deferred unless separately validated.

The assistant is required V1 functionality; an LLM and FastAPI deployment are implementation choices. Verified fact lookup and authoritative passage retrieval can produce deterministic cited answers. Optional generated wording requires grounding and privacy gates. Removing the assistant altogether would fail V1 scope.

## Outside MVP V1

- Full DOCX/PDF manuscript analysis and manuscript uploads/versioning.
- Automatic manuscript rewriting and automatic formatting.
- Full compliance scoring and submission readiness scoring.
- Reference manager.
- Collaboration and institutional tenancy.
- Billing implementation.
- Automatic submission and acceptance prediction.
- Full publication workflow automation.

Also deferred: Related Articles/Manuscript tabs, generic research-writing chat, advanced saved comparisons and exhaustive catalog coverage. No arbitrary live crawling from user questions.

## Ordered delivery slices and release gate

1. Foundation: PostgreSQL/pgvector readiness, Redis, `/api/v1`, Sanctum, OpenAPI and privacy/error conventions.
2. Journal model and ingestion: identity, assertions/reviews, revisions, requirements/documents, reviewed seed and approved adapter.
3. Discovery and recommendation: bounded retrieval, hard filters, configurable ranking and researcher-reviewed evaluation.
4. Profiles, requirements and comparison: evidence, official links, fee context and selection.
5. Lightweight projects and workspace: owner-scoped CRUD/deletion, minimum data/workflow, selection revision and all five tabs.
6. Contextual assistant: verified fact lookup, scoped authoritative passage retrieval, citations, abstention and adversarial/privacy tests.
7. MVP V1 pilot release: full bilingual journey, isolation/deletion, grounding, operational and accessibility checks.

Slices 1–4 are intermediate deliveries, not the complete V1 MVP. Release requires slices 5–6 as well. Human reviewers approve source rights, coverage/freshness, relevance and assistant evaluation thresholds, limits and privacy/hosting controls. Missing evidence is acceptable when explicitly acknowledged; unsupported answers are not. See [TESTING](../development/TESTING.md) and [DECISIONS](../architecture/DECISIONS.md).
