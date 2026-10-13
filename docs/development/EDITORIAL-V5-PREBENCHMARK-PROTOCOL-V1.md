# Editorial V5 pre-benchmark decision protocol V1 — approved and frozen

Date: 2026-10-13. **APPROVED_AND_FROZEN — NOT AUTHORIZATION TO ACQUIRE.** Independent editorial-v5 holdout validation remains PENDING/BLOCKED. No holdout output was consulted to select these criteria. This document establishes the human-approved external decision gate; it changes no implementation threshold, scoring, comparator or historical artifact.

Baseline: `4734623`, implementation `f4bef4a`, historical/comparator `e4c7105`. References: [C5 corpus and stop rule](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#phase-3e-c5--independent-holdout-acquisition-preflight-v1), [precision guidance in the real-world report](EDITORIAL-REAL-WORLD-VALIDATION-V1.md), [extraction specification](EDITORIAL-EXTRACTION-V1.md), [testing methodology](TESTING.md), [blocked audit and input contract](EDITORIAL-V5-HOLDOUT-AUDIT-V1.md), and [frozen C5 manifest](validation/editorial-v5-holdout-preflight-v1.json). The precision guidance is at line 168 of the real-world report.

## 1. Existing requirements and approved decision gates

Earlier documentation defined no formal binary V5 benchmark PASS threshold. The human operator explicitly approved all C1–C5, P1, R1, R2 and semantic safety criteria under `florent-editorial-v5-protocol-2026-10-13`. Approval was recorded and the final protocol frozen at `2026-10-13T15:29:37Z` (`2026-10-13T18:29:37+03:00`, Indian/Antananarivo). This is the recording timestamp; the chat provides no independently verified submission timestamp. Numerical origins remain documented below.

| ID | Numerical criterion | Existing basis | Approved use |
| --- | --- | --- | --- |
| C1 | At least **3 distinct journals** with usable original static editorial snapshots | Existing C5 acquisition stop rule | Promote to required scored-corpus gate; explicitly approved |
| C2 | At least **3 usable pages** | Derived from C1: distinct journals require distinct journal-bound pages | Required minimum page cases, not three facts or three duplicate captures; explicitly approved |
| C3 | At least **6 human-frozen representable positive facts** | Existing C5 six plausible positives and preferred 6–10 reviewed positives | Replace plausible opportunities with validated positives for admission; explicitly approved |
| C4 | At least **3 positive fact categories** | Existing C5 preferred minimum categories | Promote preference to hard admission requirement; explicitly approved |
| C5 | At least **1 positive gold fact per qualifying journal** | New approved coverage guard | Prevent OOS-only journals from filling C1 without positive recall evidence; explicitly approved |
| P1 | **100% micro precision / FP = 0** | Existing ideal 100%; preferred >=98% before exposure | Select documented ideal as hard PASS gate; explicitly approved. Do not silently apply 98% instead |
| R1 | **Micro recall >=80%** | No documented recall threshold | New engineering acceptance threshold explicitly approved for benchmark V1; rationale below |
| R2 | At least **1 TP in each qualifying positive category** | New approved representational-coverage guard | Prevent aggregate recall from concealing a completely missed represented category; explicitly approved |

The approved R1 criterion balances conservative abstention with measurable recovery of supported facts. It is a policy choice, not empirically established or derived from historical scores. With six positives it allows at most one FN; with ten it allows at most two. Use exact inequality `5 * TP >= 4 * (TP + FN)` without rounded percentages. With six positives, one FN actually gives 83.33% recall. The 80% recall requirement is an **engineering acceptance threshold for this benchmark V1**, not an estimate of general performance across scientific journals. With six positives, 6/6 or 5/6 can PASS only if all other criteria pass; 4/6 or fewer FAIL on recall. No F1 threshold is imposed; F1 is reported descriptively.

The 6–10 positives range remains a preferred corpus size, **not an upper cap**: annotate every supported fact on included pages, even if the count exceeds ten. Do not select six easy facts and ignore the remainder. Exactly eight URLs and their order come from the existing C5 recommended batch; one proposed URL per journal. The prior broader maximum of two URLs per journal is not permission to add a second page now. No publisher/platform quota is invented; disclose shared-platform dependence, journal/page counts and structure diversity.

Counts are distinct journal identities, original page cases and semantic gold identities, not repeated HTML snapshots or corroborating duplicate statements. Category means the unchanged comparator's field grouping, including `requirement.abstract_words`, `requirement.word_count`, `requirement.reference_style`; fee amounts and no-APC share `fee` and do not count as two categories. Only categories with representable positive gold qualify for C4/R2. Other categories remain reported without invented perfect scores.

Existing 3E-A limits (HTTPS, all DNS addresses public, TLS/SSRF protections, approved-origin redirects, 256 KiB and existing cadence) and excerpt restrictions remain unchanged. No new acquisition threshold is introduced here. Short evidence stays within the existing maximum 25 quoted words per page; full units/snapshots remain restricted internal evidence.

## 2. Decision algorithm

Evaluate in this order; never tune gates after seeing results:

1. **BLOCKED** if this protocol lacks signed approval/freeze, acquisition authorization, valid independent provenance, complete human gold freeze, matching baseline hashes, adequate C1–C5 coverage or reproducible valid captures. Invalid/insufficient evidence is not extractor FAIL. Exhausting the batch below coverage leaves BLOCKED. No comparator is run on incomplete data.
2. For a valid, adequate, fully frozen benchmark, **FAIL** if P1, R1 or R2 fails, or a fixed semantic safety veto below is triggered. Preserve all raw outputs, annotations, failure codes and metrics. A valid poor result must not be relabeled BLOCKED to hide it.
3. **PASS** only if every admission/integrity gate, P1, R1, R2 and semantic safety check passes under the approved protocol. If a safety question cannot be resolved against frozen gold, decision is BLOCKED pending independent adjudication; numeric results still remain recorded.

PASS is bounded evidence about this fixed corpus/version, not a confidence interval, publisher-wide accuracy guarantee or automatic authorization for production exposure. Operational/security/publication gates remain separate. Small sample precision 100% does not demonstrate population precision >=98%.

## 3. Corpus acquisition and exclusion handling

Use only individually authorized exact C5 URLs in the manifest's recorded order. Continue until three distinct usable journals AND six plausible positives are available, or all approved URLs are exhausted, as already documented. The three-category goal should be reviewed at this stop point; do not silently enlarge/replace the batch if it is unmet. Complete human gold may reduce the apparent count; unmet C1–C5 then means BLOCKED, requiring an explicitly authorized future corpus decision before extraction.

Public-reader/search text is not a retained snapshot. No JS, assets, templates, secondary URLs, CAPTCHA bypass or alternative transport. Record unattempted, policy-held, DNS/TLS/HTTP/size-blocked and acquired outcomes without fabricating a successful final URL/hash. Keep requested/final URLs when actually observed. Never retry a blocked source or substitute www/bare hosts without a separate operator decision compatible with unchanged security.

Each acquired page receives a pre-output usability decision: useful original static editorial text, journal applicability and interpretable scope. Empty app shells, unavailable bodies, unauthenticated-access failures and policy holds cannot support positive gold. Full provenance includes these outcomes and their exclusion reasons. Excluded acquired-unusable pages are **not** silently relabeled HTTP failures: keep `acquisition_status: ACQUIRED`, usability false and an explicit scoring exclusion in provenance. They are omitted from the scored packet's `pages` collection and retained in its `excluded_acquired_pages` inventory; otherwise the unchanged comparator would require a run for every ACQUIRED page. No exclusion may be made after observing V5 output.

Unsupported facts on usable pages are `out_of_scope`, not page exclusions. Include usable OOS-only pages as safety cases and capture a run for each, but they do not count toward the three positive-bearing journals. Do not require invented OOS/ambiguous counts: retain all actually encountered observations. AB/policy holds are acquisition inventory, never FN. If a usable acquired page later produces a deterministic extractor/preparer failure, keep it as `extraction_failed` with empty proposals; its gold positives remain FN. This is a valid coverage failure evaluated under the gates, not a retrospective usability exclusion. Infrastructure/capture corruption that makes the run untrustworthy invalidates the benchmark instead.

## 4. Evaluation semantics — unchanged comparator

One run per eligible acquired page after whole-corpus gold freeze. Use V5 dry-run in isolated PostgreSQL READ ONLY with network prohibited and unchanged before/after table counts. Capture raw sanitized summaries, version, snapshot identity, start/end timestamps and every failure. Hashes and gold digest must match before and after the whole run. No pending assertions, reviews, revisions or publication may be written.

Scoring remains the existing exact semantic identity: field, normalized value, article scope, section and scientific-domain restriction. Monetary strings/currencies use existing normalization; links compare their exact target and role. Wrong value/scope normally produces both FP and FN. Equivalent corroborating predictions count once under the existing comparator; no fuzzy matching, weighted score, threshold alteration, regex tuning or ignored errors.

Report micro totals and per-field/category and per-journal counts/rates, raw page runs, acquisition/rendering exclusions, extraction failures, OOS, CA and ambiguous_not_evaluated. Empty precision is null, never 100%. Per-category/journal rates with no denominator remain null. Macro summaries, if later desired, must be explicitly supplemental and cannot replace approved gates. No category-specific numeric precision/recall thresholds other than P1/R2 are introduced.

- **FP:** every comparator unmatched prediction counts; none is excused as a minor error. P1 fails on any FP. Preserve whether it fabricates fees, broadens conditions, invents support or selects a wrong link. Do not remove the prediction from the capture.
- **FN:** every unreturned representable positive counts, including positives on valid extraction-failed pages. Missing outputs, wrong values and incorrect scopes do not become OOS after the run. Apply R1 and report misses by category/journal.
- **Ambiguous:** freeze as `ambiguous`; do not put ambiguous observations into the positive denominator or invent a normalized truth. Preserve conditional/alternative meanings. Existing CA is credited only for a completed empty-output page; failed/mixed-output pages are not claimed as correct abstention. Predictions not matching positive gold are still FP under the unchanged comparator; ambiguous/OOS observations do not provide FP immunity.
- **Missing source fields:** absent metadata is unknown, not free/false/unsupported/zero. No positive/FN is inferred from absence. A predicted unsupported missing field is an unmatched prediction/FP. Record page/category coverage gaps explicitly.
- **Explicit but unrepresentable:** freeze as OOS with exact scope/reason; preserve separate qualifiers and coverage notes. Do not force canonical values or silently discard them.

**Approved semantic safety veto — immediate FAIL on a valid corpus:** a captured output that strips an applicable stage/qualifier, broadens conditional support, misstates voluntary fees as mandatory APC or invents a canonical fact fails the decision even if the comparator's narrower identity cannot score that attribute. This does not alter comparator counts or add manual TP/FP/FN. It is a separately reported, predeclared validity-of-claim check against frozen evidence. Do not edit gold to make a post-output interpretation fit. Unresolved evidence ambiguity requires adjudication and a BLOCKED decision, with raw metrics preserved.

## 5. Per-URL provenance record

The [C5 provenance template](validation/editorial-v5-c5-provenance-template-v1.json) enumerates all eight exact URLs without granting approval or inventing observations. Required fields:

| Group | Required record |
| --- | --- |
| Identity | Stable candidate/page/journal identifiers, exact requested URL, domain, official homepage, publisher relationship and reviewed identity references |
| Authorization | Status, operator identity/role, explicit reference, justification, exact URL scope, authorized_at, current robots/access/retention basis; PENDING/DO_NOT_APPROVE until actually decided |
| Acquisition | Status, attempt timestamp, successful acquisition timestamp (null on failure), mechanism and baseline code identity, source/snapshot IDs, observed final URL/redirect chain, HTTP/MIME/bytes and fixed failure code; no fabricated values |
| Mechanism | Existing 3E-A OfficialSources, unchanged TLS/DNS/IP/SSRF/redirect/body/cadence protections; no browser fallback; record guard outcomes and policy-review reference |
| Snapshot | Original content SHA-256, revision fingerprint, requested/final URL, retrieved_at, private storage reference and restricted retention basis; full HTML never in Git |
| Independence | Reviewer identity/role, attested_at, explicit unused-for-V1–V5-development/calibration statement, exclusion/identity checks, snapshot/gold provenance; publisher/platform sharing disclosed |
| Usability/exclusion | Pre-output reviewer and timestamp, static editorial presence, applicability, usable boolean, exclusion reason/code and rendered/access limitations; record all skipped/blocked/unusable pages |

Timestamps are ISO-8601 with timezone and indicate actual events. Approval is not acquisition; requested is not observed final URL; snapshot presence is not usable gold. Retain failed attempts without bodies, cookies, tokens or sensitive logs.

## 6. Whole-corpus human-gold freeze

Use the existing [empty comparator dataset template](validation/editorial-v5-independent-holdout-template-v1.json) and audit's exact input contract. No historical examples are inserted. Annotation rows require `id`, `page_id`, `snapshot_id`, snapshot fingerprint, fact type, normalized value, exact scope, original locator, short exact snapshot-backed excerpt, human review reference, disposition and confidence. Use `positive` + `explicit` for approved EXPLICIT representable facts; `ambiguous` for AMBIGUOUS; `out_of_scope` for OUT_OF_SCOPE/unrepresentable facts. Never label from extractor output.

Before freeze, a human annotator scans the **complete static editorial content of every included page**, enumerating every representable supported fact and all material conditional/OOS/ambiguous observations. Freeze the page eligibility list, positive counts/category/journal coverage, exclusions, missing-field observations and any unresolved ambiguity. Word limits, languages, types, fee kinds, scopes, stages and destinations require direct snapshot evidence. Duplicates and contradictory/conditional claims require explicit disposition; gold must not flatten meaning. Mechanical completeness cannot prove human review; the coverage attestation is essential.

Identity placeholders: `annotator_reference: null`, `reviewer_reference: null`, `reviewer_role: independent-human-gold-reviewer`, `completed_at: null`. Human reviewer must be independent of using V5 outputs to form labels; a second person is not mandated by this approved protocol. If roles are held by one person, record that transparently and attest separation from tuning. No automatic signature or assumed approval.

Freeze sequence:

1. Human resolves all proposed row dispositions and attests complete included-page coverage; confirms no V5 output was seen and all labels precede extraction.
2. Save the actual gold packet separately from the template and old artifacts; record review reference and UTC/offset-aware freeze timestamp. No extraction rows or predictions may have been generated yet.
3. Compute the existing comparator-compatible gold digest over UTF-8 `json.dumps({"annotations": data["annotations"], "annotation_policy": data["annotation_policy"]}, sort_keys=True, separators=(",", ":"), ensure_ascii=False)`. Store `gold_sha256`; also record the byte SHA-256 of the separate immutable pre-run gold file and the provenance/eligibility manifest hash. These are distinct digests, not interchangeable.
4. Record reviewer approval and freeze timestamp in the freeze record. Record the approved protocol file SHA-256 and decision criteria there as well. Verify C1–C5 and all fourteen V5 hashes before execution.
5. Require protocol approval/freeze and gold approval/freeze timestamps to precede every V5 capture start. Copy frozen labels/policy into the result packet unchanged; append captures and metrics there without changing the original gold file. Reverify both gold digests and V5 hashes after capture.

No gold addition, deletion, scope change or value correction after outputs are viewed can rescue the same confirmatory benchmark. Discovery or acquisition alone grants no permission to execute V5 before whole-corpus human gold validation.

## 7. Invalidation, reruns and new independent data

Benchmark invalid/BLOCKED if independence cannot be attested; any fourteen-file/comparator baseline drifts; protocol/eligibility/gold lacks pre-run freeze; unauthorized acquisition or security bypass occurs; snapshot identity/hash/locator provenance is missing; labels are influenced by outputs; coverage is incomplete; an eligible capture is omitted; results are edited; database/network isolation fails; or infrastructure corrupts capture reproducibility. Document the reason and retain any already observed outputs; do not report them as a successful confirmatory run.

Technical reproducibility reruns on identical frozen code, gold, snapshot set and protocol may verify the **same experiment**, with operator approval and all prior outputs preserved. They are not a new independent holdout. Deterministic extraction failure is a result, not permission to exclude or retry until successful. Non-deterministic results require investigation and BLOCKED; do not select the best run.

Require a **new independent holdout** after V5 is adjusted using any current holdout evidence, criteria/scope/eligibility/labels are changed after outputs are seen, cases are cherry-picked after failure, or source/labels influenced development/calibration. A replacement version must be separately frozen and use fresh journals/pages not influenced by that tuning. Re-evaluating the old corpus may be reported as development/regression only. Even without code changes, changing a failed threshold after viewing outputs cannot turn the old run into PASS under a new protocol.

Before any outputs exist, if the approved batch cannot meet admission coverage, record BLOCKED and seek a new exact-URL approval/corpus amendment; no automatic replacement. Any amendment must be reviewed, signed and hashed before the new acquisition/benchmark stage. If authorization is revoked or source evidence becomes unusable, stop; a new observed snapshot requires new pre-output gold rather than silently replacing the frozen one.

## 8. Approval record and next step

All criteria and exclusion/ambiguity policies are explicitly approved by the human operator under `florent-editorial-v5-protocol-2026-10-13`. The separate [approval record](validation/editorial-v5-protocol-approval-v1.json) contains the operator role, recording timestamp, exact SHA-256 of this final protocol and accepted criteria. The digest is kept outside this document to avoid self-reference. The previous audit, templates, historical data and implementation remain untouched.

PASS requires sufficient valid corpus, precision 100%, recall >=80%, at least one TP in every qualifying category and no semantic safety violation simultaneously. A valid corpus failing any applicable acceptance criterion is FAIL. An insufficient corpus, invalid evidence or gold that cannot be correctly frozen is BLOCKED; it is never recast as extractor PASS or FAIL. Exclusions, ambiguous observations and OOS are fixed before execution and cannot move afterward to improve metrics.

After protocol approval: separately authorize the eight exact C5 URLs with current officiality/access/robots/internal-retention basis, then perform unchanged bounded 3E-A acquisition under the established stop rule. Next prepare and human-freeze the whole gold, attest admission coverage and provenance, then obtain the explicit go-ahead for one fixed V5 batch. Protocol approval alone does not authorize acquisition or benchmark execution.

No PASS/FAIL, scores or benchmark outputs exist in this document. No acquisition, V5, comparator, commit or push is performed for this task.
