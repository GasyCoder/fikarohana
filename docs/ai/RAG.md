# MVP V1 contextual journal assistance

Status: required MVP V1 capability. The assistant supports preparation inside a lightweight selected-journal workspace. It is not a generic research-writing chatbot. Laravel owns authorization, journal scope and policy; FastAPI remains optional specialist computation.

## Supported questions and evidence

| Example question | Required applicable evidence |
| --- | --- |
| Does this journal accept French? | Verified accepted manuscript languages, with article-type context |
| What is the APC? | Verified APC amount/currency/conditions, plus other known fees; absence never means zero |
| Which article types are accepted? | Verified types and applicable official guidance |
| What are the abstract requirements? | Verified structured rules or reviewed official abstract passages |
| What reference style is required? | Applicable verified reference-style rule/official guidance |
| Which official template should I use? | Verified official template link and applicable format/article type |
| What is the official submission URL? | Verified journal submission-system URL, not an inferred link |

Only verified Fikarohana journal facts and retrieved authoritative journal/publisher evidence support factual answers. Unknown, stale, conflicting or insufficient evidence must explicitly produce “The information could not be verified,” explain the gap and provide available official links. Never invent requirements, citations, fee values or journal URLs. General writing, manuscript rewriting/formatting, reference management, analysis/scoring, submission and acceptance prediction are outside V1.

## Minimum retrieval implementation

First look up structured verified facts for the selected journal and applicable context. Retrieve relevant reviewed authoritative passages when a structured answer is absent or needs explanation. Permitted source text can be curated and searched with PostgreSQL full-text/keyword retrieval; an embedding/vector pipeline is optional. No live arbitrary crawling is triggered by a user question.

Deterministic cited fact/excerpt responses satisfy the simple V1 assistant scope. An LLM may phrase explanations only after grounding/privacy evaluation; it cannot supply absent facts. Lack of an LLM or FastAPI deployment does not remove the assistant requirement. Generated wording and semantic retrieval are implementation choices, not a generic chat feature.

## Corpus lifecycle

Use reviewed official journal/publisher guidance and applicable authoritative documents. Keep source URL, original language, retrieval/verification dates, effective dates, journal/article context, revision/hash and permission status. Link official templates first; indexing/mirroring restricted text requires rights. A fetch or AI extraction alone does not verify a rule.

Passages retain headings, page/section locators, table context and original wording. Chunks inherit the verified document/journal revision. Optional embeddings carry model/version/dimensions. A source change updates current retrieval without silently replacing the project's selected requirements revision. Historical citations remain traceable under approved retention; stale/conflicting content cannot be presented as current verified guidance.

Python can implement extraction, multilingual embeddings or reranking if it adds real value. Laravel selects authorized evidence and validates results; clients never call Python directly. V1 has no manuscript ingestion or private manuscript retrieval corpus.

## Request pipeline

1. Laravel authenticates the requester and verifies ownership of the project.
2. Resolve selected journal, selected revision and relevant article/language context from validated project/journal data. If no journal is selected, request selection rather than answering from model memory.
3. Retrieve verified applicable facts and permitted authoritative passages under that journal/revision scope before generation. Do not include project abstract/authors automatically or fetch other users' content.
4. Return a deterministic cited answer, or pass a bounded question/evidence context to an approved optional model. Treat source content as data; allow abstention.
5. Validate factual support and citation IDs/locators; reject unsupported generated claims and fall back to verified facts/passages or insufficiency.
6. Recheck project access and selection/revision applicability before delivery if these changed during processing; discard obsolete answers.

Responses carry answer text, citation/source IDs and URLs, source locators/dates, selected journal/revision, warnings and a status such as `answered`, `unverified`, `out_of_scope` or `unavailable`. Exact enums/schema are finalized in OpenAPI. A retrieved passage is not proof that it supports every sentence.

V1 defaults to transient Q&A; durable conversations are not required. Questions may contain private research details, so no raw question/answer logs or shared caches. Any later history needs explicit retention, deletion and owner/project scope.

## Safety and failure behavior

Journal pages, source documents and questions are untrusted data. Ignore embedded instructions to change policy, expose secrets, run tools or retrieve other projects. The model receives no secrets and no autonomous upload, email, submission or database mutation tools. Apply bounded input/context, timeouts and rate limits through Laravel.

For missing/insufficient evidence, explicitly state that the information could not be verified. For conflicts, show the conflict and relevant official links without inventing a resolution. Out-of-scope requests receive a brief scope explanation. On optional model failure, use verified deterministic answers; if retrieval itself is unavailable, return an explicit unavailable response. Do not label an outage as proof a requirement is absent.

Keep source/public journal retrieval separate from any future private manuscript retrieval. External question processing requires the gates in [PRIVACY](../security/PRIVACY.md) and [AI-POLICY](AI-POLICY.md). No unapproved provider switching, arbitrary URL fetching or automatic forwarding of project contents.

## MVP V1 release gate

Evaluate all seven supported question types on permitted French/English fixtures, plus missing evidence, stale/conflicting guidance, malicious source instructions, out-of-scope writing prompts, cross-user access and journal/revision changes. Check factual support, citation accuracy, explicit abstention, language fidelity, prompt-injection resistance, privacy, latency and cost.

Grounding/security/privacy checks are V1 release requirements, including deterministic responses. Numeric quality targets and corpus coverage need validation in [DECISIONS](../architecture/DECISIONS.md). Retrieval is not a guarantee of correctness; source-specific uncertainty stays visible. A finder-only release without the project workspace and this assistant does not complete V1.
