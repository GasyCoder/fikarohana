# AI policy

Status: design policy for all phases. [RECOMMENDATION-ENGINE](RECOMMENDATION-ENGINE.md) and [RAG](RAG.md) specialize this policy.

## MVP V1 assistant boundary

The simple contextual journal assistant is required inside the selected-journal project workspace. It answers questions about accepted languages, APC/other fees, article types, abstract requirements, reference style, official templates and official submission URL using verified Fikarohana data and retrieved authoritative journal sources. Cite evidence and applicability; when evidence is unavailable or insufficient, explicitly say the information could not be verified. Never invent journal requirements.

It is not a generic research-writing chatbot. Full DOCX/PDF analysis, automatic manuscript rewriting/formatting, full compliance scoring, reference manager, automatic submission, acceptance prediction and full publication workflow automation are outside V1. Collaboration, institutional tenancy and billing implementation are also deferred. Project preparation status does not certify compliance.

Laravel enforces project ownership and selected-journal/revision scope. Verified fact lookup and authoritative passage retrieval may use deterministic cited responses; an LLM, semantic embeddings and FastAPI deployment are optional. Private project abstract/authors are not attached automatically. Transient Q&A is the V1 default; durable history requires a separate approved policy. See [RAG](RAG.md).

## Allowed roles

AI may assist semantic matching, derive labeled research-area hints, extract proposed structured rules, explain cited journal information in MVP V1 and assist manuscript drafting/analysis only in later separately scoped capabilities. It is never the source of truth for journal identity, APC/fees, accepted languages, indexation, timelines, templates or submission requirements.

Candidates always originate in the stored catalog. AI extraction enters staging and review. Model confidence cannot mark a source claim verified. Canonical authorization, strict filters, workflow, pricing and eligibility remain in Laravel.

## Output labels

| Class | Required presentation |
| --- | --- |
| Verified source information | Claim and applicable source/evidence with verification date |
| Derived calculation | Method, inputs/source IDs, units and version; e.g. normalized relevance |
| AI interpretation | Explicitly labeled interpretation or translation with supporting context where available |
| Missing/conflicting evidence | Acknowledge uncertainty; no invented value or fabricated citation |

Do not hide these distinctions in a general disclaimer. Keep them near consequential information. Match/relevance scores never mean likelihood of acceptance. Future submission readiness concerns documented checklist completion, not scientific quality, editorial success or a guaranteed compliant manuscript.

## Scientific integrity

Do not fabricate papers, references, findings, authors or publication metrics. Suggested literature must resolve to sourced bibliographic records; verify supporting metadata before presentation. Generated prose is draft assistance requiring researcher review, never verified research results. Do not generate invented experimental data or claim analyses were performed when they were not.

Fikarohana is not a substitute for scientific judgment or journal decisions. It does not submit, impersonate authors or make autonomous publication commitments. Users remain responsible for journal-specific AI-use disclosure and policies; display sourced requirements when available.

## Data and provider controls

Use the least content required. Research titles/abstracts can be confidential, even before a manuscript upload. No external embedding/LLM processing of private inputs before provider terms, retention/training settings, residency and user disclosure/consent basis are reviewed. If that gate is unmet, disable external processing and retain local lexical discovery and deterministic verified-fact/authoritative-passage journal assistance. The required V1 assistant cannot be replaced with unsupported general model answers.

No model training or fine-tuning on user manuscripts by default. Any future opt-in requires separate explicit policy and revocation semantics. Keep raw prompts, completions, files and tokens out of routine logs/analytics. Restrict evaluation fixtures to synthetic, public-permitted or explicitly consented content.

## Operational controls

Version models, prompts, embeddings, ranking configurations and document-processing methods. Set time/token/input budgets, timeouts, rate limits and fail-safe responses. Validate returned schemas and source IDs before persistence or display. Do not let generated content execute code or change canonical records without explicit application validation and an authorized user operation.

Adversarial documents are untrusted data. Defense includes scoped retrieval, network/tool restrictions, citation checks and regression fixtures, not a prompt instruction alone. Test hallucination, unsupported inference, privacy leakage, bias/coverage across languages and prompt injection before enabling a model-backed feature.

Record incidents/corrections with sanitized identifiers and source/model versions. A rollback to deterministic behavior must be possible. Model/provider or privacy-policy changes require updating this policy and the decision register before release.
