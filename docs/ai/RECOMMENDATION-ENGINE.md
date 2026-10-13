# Recommendation engine V1

Status: broader design; Phase 4A deterministic local metadata implementation is documented in [Journal Discovery API V1](../development/JOURNAL-DISCOVERY-API-V1.md). Recommendations originate exclusively in the eligible stored journal catalog. Laravel owns final policy, filtering and ranking; specialist Python computation is optional.

## Input and output

Input includes topic/title/abstract or field, optional specialization/keywords, publication language, APC budget/currency, OA preference, desired indexation and article type. Each optional criterion records whether it is strict or preferred. Validate input length, types and enums; preserve original text privately while normalizing transiently. UI locale is not a journal language constraint.

Output returns at most five unique active journal IDs/revisions, ranked relevance, reasons, satisfied/unsatisfied/unknown criteria, evidence references, freshness warnings, algorithm/config/catalog version and actual retrieval mode. Normally aim for 3–5 results; return fewer or none when necessary. Match is not acceptance probability.

## Pipeline

1. **Preprocess:** normalize whitespace and keywords; identify language/discipline hints with confidence if derived. Hints are not authoritative user intent. Do not overwrite explicit input.
2. **Retrieve candidates:** query active reviewed journal scope/discipline records using PostgreSQL full-text/structured retrieval. Bound the candidate pool and record retrieval version. Cheap indexed constraints can narrow retrieval, but final hard-filter checks are mandatory.
3. **Semantic matching, when enabled:** compute an input embedding and compare with journal-scope embeddings of the same model/version/dimensions. Combine lexical and semantic candidates so one method does not unnecessarily exclude valid journals. Unavailable/incompatible vectors trigger the documented lexical mode.
4. **Hard filters:** reject incompatible journals and those lacking current verified applicable evidence for strict constraints. Record aggregate exclusion reasons. Never infer unknown APC as zero or unknown language as supported.
5. **Rank:** Phase 4A combines taxonomy and lexical metadata relevance only; language, article type, APC and indexation are hard eligibility constraints, not boosts. Semantic matching and preference ranking remain future design subject to validation.
6. **Select:** take a bounded shortlist; stable opaque ID breaks equal-score ties. Categories may annotate qualified results without duplicates or invented winners.
7. **Explain:** render deterministic reasons tied to input features and source assertions. Optional LLM wording is a later enrichment; it cannot change IDs, scores, eligibility or facts.

When retrieval coverage is inadequate, report that the current catalog cannot satisfy the request and suggest an explicit criterion change. Never call the LLM for substitute journals or silently remove a strict preference.

## Configurable ranking

Version a configuration containing feature definitions, active weights, missing-data policy, candidate limits, strict filter rules and model/calibration identifiers. Validate ranges and nonnegative weights and normalize active weights. No weights in this document are permanent product commitments.

For applicable features, a proposed V1 aggregate is:

`relevance = 100 × Σ(weight_i × feature_i) / Σ(active_weight_i)`

Features are calibrated to `[0, 1]`; absent user criteria are inactive. If the journal lacks evidence for an active preference, assign zero preference contribution and separately report unknown coverage. Do not renormalize only over known journal facts: that can reward incomplete records. Similarity normalization/calibration must be recorded; raw cosine values are not probabilities. Avoid double-counting highly correlated scope/keyword signals without evaluation.

Keep evidence coverage as a separate diagnostic, not a fabricated confidence or journal quality score. In lexical fallback, use a separately evaluated configuration and disclose the mode; do not treat missing semantic similarity as a low-scoring journal feature. Score display precision is a UX choice and should not imply scientific certainty.

Budget checks account for applicable APC and other known mandatory fees. For strict budget, require comparable currency and a defensible upper bound under stated conditions; insufficient fee evidence makes eligibility indeterminate and excludes that strict match. Preferred budget may rank with warnings. Waiver eligibility is not assumed.

## Category rules

Best Match uses rank. Best No-APC requires verified explicit zero applicable APC and discloses other fees. Best French-Friendly requires verified acceptance of French for the selected article context. Best International stays disabled until its definition and evidence are approved. Category winners always satisfy all active hard filters.

## Evaluation and release

Create permitted French/English research-input fixtures and a journal snapshot, with researcher relevance judgments. Split tuning and evaluation examples to avoid reporting training fit as performance. Measure relevance at the top results (precision/nDCG where suitable), eligible coverage, strict-filter violations, unknown/stale rates, duplicate rate, latency and operating cost. Report lexical versus semantic results against the same snapshot and inputs.

Required invariants: no unknown journal IDs; zero strict-filter violations in fixtures; no unsupported facts in reasons; no acceptance probability phrasing; stable outputs for fixed deterministic inputs/config; empty-result and service-failure behavior tested. Human-set relevance and latency thresholds are pending in [DECISIONS](../architecture/DECISIONS.md).

Store minimal run metadata for reproducibility under privacy retention; persist full input only as an explicitly saved search. Model/config or data changes create new versions rather than overwriting prior run meaning. Future reranking must still satisfy Laravel filters and provenance constraints.
