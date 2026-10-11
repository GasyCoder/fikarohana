# Journal-specific retrieval-augmented assistance

Status: later-phase design. No assistant is included in the first MVP. Build only after journal-document coverage, access rights and evaluation are sufficient.

## Purpose and scope

The assistant explains selected-journal information and author requirements using retrieved evidence. It may help interpret supported guidance, but cannot invent missing rules, choose journals from model memory or promise acceptance. Laravel authorizes the conversation and determines journal/project scope before any retrieval.

## Corpus lifecycle

Use reviewed official journal/publisher guidance and explicitly applicable authoritative documents. Retain source URL, language, retrieved/verified dates, effective dates, journal/article context, version/hash and permission status. Prefer original official templates/links; do not index restricted full text without rights.

Extraction and chunking retain headings, page/section locators, tables and rule context. Preserve original wording and separate editorial requirements from examples. Chunks inherit source and journal revision. Embeddings include model/version/dimensions; source changes trigger replacement in current retrieval while historical citations remain resolvable under retention policy.

Python can implement extraction, multilingual embeddings and retrieval/reranking if justified. Laravel controls document eligibility and final response scope; FastAPI receives only authorized inputs and returns evidence references/computation. The client never calls Python directly.

## Query pipeline

1. Laravel authenticates the user, checks project access if applicable, and determines selected journal, article type and requirement snapshot.
2. Retrieve only allowed journal documents and, when explicitly supported, that user's project documents. Apply scope filters before retrieval/reranking, not after a model has seen private data.
3. Rank relevant passages; detect stale or conflicting policy evidence and prefer the applicable reviewed revision.
4. Construct a bounded context with source identifiers/locators, clear separation of source content and instructions, and explicit permission to abstain.
5. Generate an answer with citations for factual claims. Label interpretation and translation; report unsupported details as unknown.
6. Validate citations resolve to allowed documents and cited passages support material claims. Unsupported answers are withheld or replaced with an evidence-only response.

Responses contain answer text, citations, journal/document revision, warnings and an insufficiency status. A citation names the source, URL, page/section, relevant dates and assertion/document ID. Merely retrieving a passage is not proof that it supports every generated sentence.

## Safety and failure behavior

Treat journal pages, uploaded documents and user text as untrusted data. Ignore embedded instructions to change policy, reveal secrets, call tools or retrieve other projects. The model receives no provider secrets and has no autonomous upload, email, submission or database-writing tools.

If guidance is missing, say what is unknown and link to available official instructions. If policies conflict, cite the conflict and suggest verification with the journal; do not choose an invented resolution. On model/provider failure, return accessible source excerpts/links or an explicit unavailable state without speculative answers. Never perform arbitrary user-supplied URL fetching from the assistant.

Keep public journal retrieval separate from private manuscript retrieval. Private indexes, caches and prompts must remain owner/project scoped. External processing needs the privacy/provider approval gates in [PRIVACY](../security/PRIVACY.md); do not send full manuscripts when a minimal excerpt suffices.

## Evaluation gate

Maintain grounded Q&A fixtures, questions with no available answer, conflicting/stale guidance, multilingual cases and malicious source instructions. Evaluate factual support, citation accuracy, abstention, cross-project leakage, translation fidelity, prompt-injection resistance, latency and cost. Include manually checked examples for fees, language, word limits and declarations.

A grounded-answer benchmark and privacy/security review must pass before release. RAG alone is not a guarantee of correctness. Store only approved minimal conversation/evaluation data; the UI must make persistence and deletion clear.
