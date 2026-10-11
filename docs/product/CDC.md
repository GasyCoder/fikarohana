# Cahier des charges — Fikarohana

Status: initial design, 2026-10-11. This is a requirements baseline; proposed thresholds and commercial choices require validation. See [MVP](MVP.md) for the first release and [decisions](../architecture/DECISIONS.md) for unresolved choices.

## Problem and purpose

Researchers often lack a reliable way to identify appropriate journals, verify publication conditions and prepare against the right instructions. Fikarohana must reduce this uncertainty without implying that relevance guarantees publication or that missing metadata is known.

Primary users are doctoral students, master's research students, novice researchers, lecturers/researchers and independent researchers. Universities, doctoral schools, laboratories, research centers and academic libraries are future institutional customers. The initial design supports individual ownership; institutional tenancy is a later extension.

## Required journey

Topic/title/abstract → research area → journal candidates → 3–5 recommendations where enough eligible journals exist → comparison → selection → verified information/guidelines/templates → research project → journal-specific preparation → compliance analysis → submission readiness.

Never pad a shortlist to meet the target count. A selected journal remains a user decision. Editorial acceptance and actual submission remain outside Fikarohana's authority.

## Functional requirements

| ID | Requirement | Phase | Acceptance evidence |
| --- | --- | --- | --- |
| FR-01 | Accept field, specialization, title, abstract, keywords, publication language, budget, OA preference, indexation and article type | MVP | Input validated by Laravel; user can start with topic/title/abstract or field, without completing every field |
| FR-02 | Retrieve candidates only from the eligible stored catalog | MVP | Each returned ID resolves to a sourced journal profile |
| FR-03 | Return a small, ranked shortlist with reasons and unmet/unknown preferences | MVP | Normally 3–5 results, or an explicit partial/empty result without invented journals |
| FR-04 | Compare matching criteria with verified publication conditions | MVP | Basic side-by-side comparison preserves unknowns and citations |
| FR-05 | Show profile identity, scope, fees, languages, OA, indexation, requirements and official links where known | MVP | Every material published fact has evidence and verification state |
| FR-06 | Allow basic anonymous discovery; require authentication to persist personal data | MVP | Anonymous queries are not placed in a permanent personal history |
| FR-07 | Save journals and searches; delete saved records | MVP | An account cannot retrieve or modify another user's saved data |
| FR-08 | Create a project with authors, shortlist, selection, requirements snapshot and activity | Later | Project ownership and workflow transitions are enforced in Laravel |
| FR-09 | Offer a journal workspace with Overview, Requirements, Fees, Template, Related Articles, Manuscript and Assistant | Later | Workspace is bound to a selected journal and shows requirement revision dates |
| FR-10 | Answer journal questions using retrieved authoritative evidence | Later | Answers cite supporting passages or acknowledge insufficient evidence |
| FR-11 | Import DOCX/PDF and inspect structure, references, lengths, declarations and compliance | Later | Rules trace to a selected requirements revision; unsupported checks remain unknown |
| FR-12 | Support French and English interfaces and explain source language | MVP onward | UI localization does not translate or alter authoritative facts silently |

## Nonfunctional requirements

- API-first: all domain operations go through versioned Laravel endpoints, usable by web, mobile and future clients.
- Traceability: published facts retain field-level evidence; recommendation runs retain algorithm/data version identifiers.
- Confidentiality: research inputs and future manuscripts are private by default, excluded from logs and public caches.
- Reliability: ingestion is idempotent, provider failures do not destroy a last valid catalog, and AI failures have explicit fallback behavior.
- Accessibility: target WCAG 2.2 AA for the web interface; validate keyboard navigation, labels, contrast and status announcements before release. This is a design target, not a current compliance claim.
- Performance: proposed discovery p95 under 3 seconds on an agreed catalog/load fixture, excluding asynchronous ingestion; optional explanations must not block the result. Confirm load and latency budgets before treating them as release requirements.
- Operations: monitor freshness, source failures, queue backlog and API errors without recording private text. Define backup recovery objectives before production.

## Boundaries and non-goals

No full manuscript analysis in MVP; no article-generation product; no automatic submission, acceptance prediction, universal journal trust score, fabricated metadata, unlicensed crawling or entire-world catalog promise. No institutional billing, collaboration, payment processing or new microservice topology in MVP.

## Release acceptance

A user can enter research intent, obtain eligible candidates, inspect reasons and source dates, compare journals and save a selection after authentication. Unsupported strict filters produce a clear result rather than a silent relaxation. A source change can be audited back to the ingestion run. Cross-user access tests, contract checks, data-quality fixtures and bilingual journey checks pass.

Human validation is needed for the initial disciplines/geographies, catalog size, pricing/quotas, source permissions, freshness intervals, evaluation set and performance/privacy targets. These do not authorize changing the architecture constraints.
