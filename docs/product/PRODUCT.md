# Product behavior

Status: initial design. [CDC](CDC.md) defines requirements; [MVP](MVP.md) defines the delivery boundary.

## Discovery experience

Start with a topic, title, abstract or scientific field. Explain that more context improves matching. Specialization, keywords, article type, desired publication language, maximum APC with currency, OA preference and indexation refine the request. Distinguish interface language from publication language.

Each constraint is explicitly strict or preferred. A strict constraint excludes journals with incompatible or insufficiently verified evidence; a preference affects rank and displays uncertainty. Default optional inputs to preferences unless the user requests a strict requirement. Never turn a budget into a comparable value without currency and fee context.

The first view presents at most five journal cards. Each card shows identity, match/relevance, a short reason, key known publication conditions, unknowns and a route to evidence. Users can adjust criteria and run discovery again. They should not have to inspect hundreds of candidates.

Category labels are optional views over eligible candidates:

| Label | Qualification |
| --- | --- |
| Best Match | Highest relevance under the active criteria |
| Best No-APC Option | Explicit, current evidence of zero APC for the relevant article context; other fees remain visible |
| Best French-Friendly Option | Verified French acceptance for the relevant article type |
| Best International Option | Requires a human-approved definition; disabled initially |

A journal can qualify for several categories without duplicating the displayed card. Never manufacture a category winner. A relevance score means fit to the input, not editorial acceptance probability.

## Profiles and comparison

Profiles display identity, publisher, official URLs, scope, languages, article types, OA model, fees, indexation, timelines, requirements, guidelines and templates where available. Place source links, retrieval/verification dates and stale/conflict notices close to consequential facts. Unknown is a visible state; zero fees, unrestricted language and unlimited length are not defaults.

Separate provider inclusion from independently supported indexation. Do not label a journal universally trustworthy or predatory from a single signal. Explain the evidence and let users inspect it. Timeline statements are reported values with their context, not promises.

MVP comparison supports 2–3 journals across scope fit, languages, APC and other fees, OA, indexation, article types, source freshness and official guidance. No aggregate winner or hidden conversion of currencies. Advanced comparison later adds saved comparison sets, deeper requirements and project-specific checks.

Selecting a journal in MVP can save it to the account; it does not create a research workspace yet. A future project selection captures the requirements revision and warns when newer guidance exists.

## Account boundary

Anonymous users can discover, inspect profiles and compare. Prompt for an account when saving journals/searches or creating future projects, uploading files or preserving history. Preserve in-progress intent through sign-in without exposing it in URLs or analytics. Saved search text is private, editable by replacement and explicitly deletable.

Account creation should not require institutional affiliation. Laravel controls authentication, authorization and future entitlements. Client visibility is never an access-control mechanism.

## Future workspace

A project contains title, abstract, field, keywords, authors, files, shortlist, selected journal, requirements/template references, manuscript versions, analyses, activity and status.

Workflow: `discovery` → `journal_selected` → `manuscript_preparation` → `compliance_analysis` → `ready_to_submit`. Users may return to an earlier phase. Changing the selected journal invalidates applicable compliance results and prompts an explicit requirements snapshot update. Readiness is a checklist state based on supported checks and user acknowledgment; it is not acceptance assurance.

Workspace tabs are Overview, Requirements, Fees, Template, Related Articles, Manuscript and Assistant. Related articles must come from sourced bibliographic records and do not imply access to licensed full text. Official templates are linked first; mirroring requires permission. Assistant answers are grounded in the selected journal's retrieved evidence.

## Localization and commercial direction

French and English interface messages, errors and help are first priorities. Keep original source text available. Label translated explanations; retain original units, currency, dates and provenance. Identifiers and API enums are locale-independent.

Freemium is a direction, not a finalized entitlement contract. Proposed free access covers discovery, limited recommendations and basic information. Potential paid value includes advanced comparison, projects, assistant, manuscript/compliance analysis and richer history. Basic provenance must remain visible regardless of plan. Basic comparison and saved records belong in the MVP pilot; prices, limits and billing implementation are deferred. Institutional subscriptions follow validated individual workflows.

## Evidence of product value

Measure successful shortlists, comparison use, profile/source inspection and saved selections with minimal non-content analytics. Pair these with researcher usability interviews and relevance judgments. Track empty-result reasons and catalog coverage gaps. Do not optimize solely for clicks or promise increased acceptance without independent evidence.
