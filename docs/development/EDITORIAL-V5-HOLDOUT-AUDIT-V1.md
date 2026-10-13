# Independent Editorial V5 Holdout Audit V1

2026-10-13. **BLOCKED. Independent editorial-v5 holdout validation pending. Phase 4 NOT_READY.** This is a repository/protocol audit, not an extractor benchmark. No independent validation, network research, acquisition, extraction, comparator evaluation, full suite, commit or push was performed.

## Baseline and fingerprint verification

Baseline is `4734623` (C5 preflight), following `f4bef4a` (implementation) and `e4c7105` (historical/comparator artifacts). The working tree was clean at audit start. All fourteen current V5 SHA-256 hashes match both the frozen candidate manifest at `4734623` and implementation file contents at `f4bef4a`. The original manifest, hashes, comparator, rules and thresholds remain unchanged. Full hashes and file-level checks are in [audit JSON](validation/editorial-v5-holdout-audit-v1.json).

## Dataset inventory and independence

The [previous V5 independent packet](validation/editorial-v5-independent-holdout-v1.json) has four candidate pages / three journals, one acquired snapshot, **zero usable static editorial snapshots, zero gold annotations and zero extraction runs**. Biology Open's two pages were blocked HTTP 403; Systematic Biology is a policy hold; Discrete Analysis is an empty static app shell. Its annotations/extractions are empty and metrics null. The filename does not make it a benchmark-ready dataset.

The [C5 manifest](validation/editorial-v5-holdout-preflight-v1.json) contains eleven discovery candidates and eight recommended exact URLs, awaiting new human authorization. It contains no acquired case or gold. The earlier [V5 candidate manifest](validation/editorial-v5-holdout-candidates-v1.json) is frozen discovery history, not labels. Local Git history dates the historical packet to `e4c7105` and C5 manifest to `4734623`; implementation is `f4bef4a`. This supports the recorded ordering but cannot independently prove a usable corpus exists or that undocumented calibration never occurred. An operator independence attestation and actual snapshot/gold provenance are still required.

All completed local gold datasets are historical/development data. The V5 development artifact explicitly identifies itself as NOT_INDEPENDENT and combines prior PLOS/JOSS/JMLR/Confluentes gold. JMLR was independent for V3, and Confluentes for V4, before later V5 remediation used them. Neither can now be reused for independent V5 testing. eLife/PeerJ are development safety/rendering data. Synthetic fixtures and comparator unit-test dictionaries are also excluded. No historical case was relabeled as holdout.

## Existing protocol and acceptance criteria

Sources: [real-world methodology and C5 gate](EDITORIAL-REAL-WORLD-VALIDATION-V1.md), [OfficialSources protocol](OFFICIAL-SOURCES-V1.md), [extraction specification](EDITORIAL-EXTRACTION-V1.md), [testing commands](TESTING.md), [TODO](TODO.md), candidate manifests and the actual comparator/CLI source.

1. Verify the fourteen frozen hashes before any independent evaluation. Attest journal/page/snapshot independence from every V1–V5 development/calibration dataset; apply the C5 exclusions including Scientific Reports and Sensors.
2. Obtain exact-URL human authorization with officiality, applicability, current access/robots and internal-retention review. The earlier C4 reference does not authorize C5. Use unchanged 3E-A register → approve → fetch only for eligible approved URLs. No scripts, secondary resources, bypass, automatic replacement or security relaxation. Preserve requested/final URL and snapshot metadata internally.
3. The pre-existing acquisition stop rule is **three distinct journals with usable static editorial snapshots AND at least six plausible positive facts**, or exhaustion of all approved URLs. Exhaustion is a stop condition, not PASS. The preferred final gold target is **6–10 representable positives across at least three categories**; it is a corpus target, not a scoring threshold.
4. Prepare whole-corpus gold from retained original snapshots independently of extractor output. Human reviewer must resolve exact value/scope, explicit/ambiguous/OOS disposition and complete page coverage. Preserve qualifiers and unrepresentable facts separately. Freeze all gold before one unchanged V5 whole-corpus run, never a page → benchmark → correction cycle.
5. Run V5 only from eligible approved stored records, dry-run, isolated PostgreSQL READ ONLY transaction, no outbound HTTP and unchanged table counts. Capture every acquired page, including failures. Verify hashes and gold digest again after capture. Run the unchanged offline comparator on the completed packet.

The report's existing guidance is ideal precision 100%, preferred >=98% before exposure; it expressly says **no numeric benchmark pass threshold is invented**. The comparator produces counts/rates and validates input consistency, but implements no PASS/FAIL/readiness gate, minimum journal count or independence check. No formal required recall/F1/FP threshold or binary benchmark acceptance rule is defined. This audit adds none. Even a later score matching guidance cannot automatically establish formal PASS or product readiness; a pre-run acceptance decision is needed if a binary outcome is required.

## Exact comparator input contract

An [empty template](validation/editorial-v5-independent-holdout-template-v1.json) is prepared. It is deliberately not runnable: empty collections and incomplete human attestation must not be treated as validated data. Use a new actual result packet such as `validation/editorial-v5-independent-holdout-c5-v1.json`; retain all previous files unchanged. Do not populate it with historical/synthetic examples or search snippets.

| Collection | Required structure |
| --- | --- |
| Root | `schema_version: 1`, arrays `journals`, `pages`, `annotations`, `extractions`, and `annotation_policy`; add frozen V5 hashes, baseline, operator/independence provenance |
| Journal | `id`, title, publisher, official identity references; explicit independent split and no fabricated canonical identifiers |
| Page | Unique `id`, `journal_id`, `acquisition_status`, `source_record_id` for acquired pages; journal-bound source ID, requested/final URL, retrieval date, content hash/fingerprint, MIME, size, access/policy outcome and static-usability evidence |
| Annotation | Unique `id`, `page_id`, `snapshot_id`, `fact_type`, `value` object, `scope` object, `disposition`, `confidence`; retain reviewer reference, snapshot fingerprint, original locator and short permitted evidence fragment |
| Annotation policy | Truthy `annotator_reference`, `completed_at`, `page_coverage_attested`; explicitly attest whole-corpus labels frozen before extraction |
| Extraction | Exactly one row per `ACQUIRED` page, unique `page_id`, matching `snapshot_id`, `status` (`completed` or `extraction_failed`), `proposals` array; failure has empty proposals and sanitized cause |
| Proposal | `field`, typed `value` object and original `locator`; captured only from unchanged V5, not manually predicted |

Supported `fact_type`/`field` values: `fee`, `language`, `article_type`, `requirement`, `template`, `submission_url`. Annotation dispositions: `positive`, `ambiguous`, `out_of_scope`. Positive annotations require `confidence: explicit` and no scientific-domain restriction. All annotation snapshots must match their page/run. Duplicate annotations, duplicate positive identities and missing runs are invalid.

Value objects must use existing typed output: languages/article types use `code` and boolean `supported`; requirements use `requirement_type`, `operator`, `value_number`/`value_text`/`value_items`, `scope` and nullable `article_code`; fees use `fee_type`, `availability`, exact decimal-string `amount`, `currency` and `basis`; templates use `format`/`official_url`; submission links use `text_value`. Explicit no-APC is unknown scalar money with null amount/currency and `official_reported_no_apc` basis, never monetary zero. Annotation scope retains `article_code`, section, scientific-domain restriction and applicable stage/qualifiers. The comparator matches its supported field/value/article/section/domain identity; not every contextual attribute is independently scored, so human scope review remains necessary.

Freeze `gold_sha256` as SHA-256 of UTF-8 `json.dumps({"annotations": data["annotations"], "annotation_policy": data["annotation_policy"]}, sort_keys=True, separators=(",", ":"), ensure_ascii=False)`. Comparator checks this digest when present; the protocol requires it even though comparator parsing allows omission. Add capture timestamps and verify gold freeze precedes every extraction. Existing source-approved state/IDs are not substitutes for human gold approval.

## Commands and output locations

Executed here: `git status --short`, `git log -3 --oneline`, read-only `git show`/`git rev-parse`, local `rg`/`sed`, Python JSON inventory/SHA-256 checks and report/template preparation, then `git diff --check`. No tests or comparator evaluations were executed; previously accepted 15 comparator tests and 694 PostgreSQL tests / 4303 assertions are historical evidence.

Future commands **only after the independent data and authorization gates pass**:

```bash
# Within apps/api, configured for the isolated migrated PostgreSQL validation DB:
php artisan journals:sources register --journal=JOURNAL_UUID --type=official_journal --purpose=guidelines --url=EXACT_APPROVED_HTTPS_URL
php artisan journals:sources approve --source=SOURCE_UUID --actor=OPERATOR_REFERENCE --basis=REVIEW_REFERENCE
php artisan journals:sources fetch --source=SOURCE_UUID
# After complete human gold freeze, once per eligible acquired snapshot:
php artisan journals:sources extract --record=SNAPSHOT_UUID --dry-run
```

Those uppercase arguments are required external identifiers, not usable examples. The CLI's `--dry-run` suppresses writes but does not itself enforce PostgreSQL READ ONLY, whole-corpus ordering, independence or HTTP prohibition. The benchmark harness must enforce those historical safeguards and capture before/after table counts. No reusable whole-corpus production runner is committed; earlier reports describe validation harnesses. A future isolated runner must wrap the existing service without changing V5 and assemble CLI/service summaries into the input rows above. Never run `extract` without `--dry-run` for this benchmark.

From repository root, after a complete packet exists:

```bash
PYTHONDONTWRITEBYTECODE=1 python3 docs/development/validation/compare_editorial.py docs/development/validation/editorial-v5-independent-holdout-c5-v1.json
```

Comparator writes JSON to stdout. Save its unchanged raw output to a distinct future `validation/editorial-v5-independent-holdout-c5-metrics-v1.json`, and include it under the packet's `metrics`. Preserve sanitized per-page dry-run summaries, failure codes and provenance in the result packet; full publisher HTML remains only in private validation storage. Never run the comparator on this empty template or the failed old V5 acquisition packet.

## Raw outcomes, historical comparison and decision

Independent raw extractor results, TP/FP/FN/CA, precision/recall/F1 and ambiguous-case results are **null / NOT_EXECUTED**, not zero or 100%. No gold exists from which ambiguity or missing facts can be measured. Acquisition/rendering failures above are operational limitations, not extractor FN.

Stored historical results, read directly without rerunning their comparator:

| Dataset | TP / FP / FN | Relevance to independent V5 |
| --- | --- | --- |
| V2 real-world baseline | 0 / 0 / 6 | Historical baseline |
| V3 development replay | 6 / 0 / 0 | Development |
| JMLR V3 holdout | 0 / 0 / 2 | Later used for V5 remediation |
| V4 development replay | 6 / 0 / 0 | Development |
| JMLR V4 remediation | 0 / 0 / 2 | Development |
| Confluentes V4 holdout | 0 / 0 / 1 | Later used for V5 remediation |
| V5 development replay | 9 / 0 / 0 | Explicitly NOT_INDEPENDENT |
| Independent V5 | NOT_EXECUTED | No usable dataset |

**Decision: BLOCKED; validation remains PENDING.** Required external inputs are new exact-URL authorization, usable unchanged-3E-A snapshots, independent provenance attestation, complete human-reviewed whole-corpus gold with frozen digest, and a pre-run acceptance decision if formal PASS/FAIL is required. No PASS/FAIL can honestly be assigned to extractor quality now.

Recommended next action: obtain operator authorization for the existing eight C5 URLs and access/retention review; acquire only that bounded batch under unchanged security, then human-freeze the complete gold. Resolve acceptance wording before any benchmark output is observed. Do not reopen discovery or reuse development cases.

Only this report, its audit JSON and empty template are new uncommitted files. After actual validation, the new result/metrics packet, existing real-world report, TODO and TESTING would need updates; original fingerprints, historical gold/results and comparator stay untouched.
