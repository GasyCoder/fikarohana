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

In MVP V1, selecting a journal leads to an authenticated research project and its journal-specific workspace. Carry research intent and shortlist into an explicit project creation action; a bookmark alone is not a project. Selection captures the journal requirements revision and shows a notice when newer guidance exists.

## Account boundary

Anonymous users can discover, inspect profiles and compare. Prompt for an account when saving journals/searches, creating/resuming a project or using its contextual assistant. Manuscript uploads are deferred. Preserve in-progress intent through sign-in without exposing it in URLs or analytics. Saved search text is private, editable by replacement and explicitly deletable.

Account creation should not require institutional affiliation. Laravel controls authentication, authorization and future entitlements. Client visibility is never an access-control mechanism.

## MVP V1 project and workspace

Minimum project data: `title`, `abstract`, `scientific_field`, `keywords`, `authors`, `journal_shortlist`, `selected_journal`, `status`, `created_at`, `updated_at`. Laravel also stores an owner and stable project ID; selection records its journal revision. Authors are descriptive entries, not collaborators or account invitations. Empty abstract/keyword/author entries may be completed during preparation; an unknown scientific field is not invented.

Minimum workflow: `discovery` → `journal_selected` → `preparation`. A project may start with a shortlist and no selection, or be created after choosing a journal and begin at `journal_selected`. Preparation requires a selected journal; status expresses user progress, not compliance or readiness. Users can return to discovery. Laravel validates transitions.

Changing/clearing the selected journal is explicit: replace/clear its requirements revision, reset to `journal_selected`/`discovery`, and clear transient assistant context. Previous-journal answers must not appear as guidance for the new selection. When the same journal publishes newer guidance, show its date and require an explicit revision refresh; never silently replace the selected requirements.

| Tab | MVP V1 behavior |
| --- | --- |
| Overview | Edit/resume project metadata, authors, shortlist, selection and workflow status |
| Requirements | Verified available language/article-type/abstract/reference/submission rules with evidence, dates and explicit unknowns; link official guidelines |
| Fees | Applicable APC and other known fees with currency, conditions, provenance and verification state |
| Template | Official applicable template and guideline links, formats/context where verified; explicitly indicate unverified/unavailable templates |
| Assistant | Selected-journal Q&A grounded in verified stored facts and authoritative source passages |

The workspace requires a selected journal for journal-specific tabs. Before selection, Overview guides users back to discovery/comparison. No manuscript editing, file processing, readiness scoring or automatic formatting is implied by preparation. Related Articles and Manuscript tabs are later capabilities.

## MVP V1 contextual assistant

The assistant answers questions such as: Does this journal accept French? What is the APC? Which article types are accepted? What are the abstract requirements? What reference style is required? Which official template should I use? What is the official submission URL?

Use verified Fikarohana facts and retrieved authoritative journal/publisher sources scoped to the selected journal, applicable article context and revision. Cite factual answers with source links/dates and passage locators where available. If evidence is unavailable, stale, conflicting or insufficient, explicitly state: “The information could not be verified,” explain the gap and provide available official links. Never invent requirements or treat absent fees as zero.

This is journal-specific assistance, not a generic research-writing chatbot. Out-of-scope writing/rewrite/analysis requests receive a scope explanation. Laravel authorizes each request. Send only the question and necessary journal context; do not automatically attach project abstract/authors. Persistent conversation history is not required for V1; the default is transient Q&A. See [RAG](../ai/RAG.md) for retrieval, safety and evaluation gates.

## Localization and commercial direction

French and English interface messages, errors and help are first priorities. Keep original source text available. Label translated explanations; retain original units, currency, dates and provenance. Identifiers and API enums are locale-independent.

Freemium is a direction, not a finalized entitlement contract. Proposed free access covers discovery, limited recommendations and basic information. Potential paid value includes advanced comparison, projects, assistant, manuscript/compliance analysis and richer history. Basic provenance must remain visible regardless of plan. Basic comparison, saved records, lightweight projects, the five-tab workspace and simple contextual assistant belong in MVP V1. A future paid packaging option must not remove them from the V1 delivery scope; prices, quotas and billing implementation are deferred. Institutional subscriptions follow validated individual workflows.

## Evidence of product value

Measure successful shortlists, comparison use, profile/source inspection, project creation/resumption, preparation transitions and supported/abstained assistant answers with minimal non-content analytics. Pair these with researcher usability interviews and relevance judgments. Track empty-result reasons and catalog coverage gaps. Do not optimize solely for clicks or promise increased acceptance without independent evidence.
