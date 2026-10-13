# Editorial Real-World Validation V1

Current status: **3E-C5 acquisition preflight complete; new exact-URL human approval pending; V5 frozen; Phase 4 NOT_READY.** See [C5 preflight](#phase-3e-c5--independent-holdout-acquisition-preflight-v1). Historical baseline: **FROZEN BASELINE COMPLETE — human-approved gold, unchanged editorial-v2, no production fixes.** Four snapshots were evaluated, including one preparation failure. Historical results below describe the actual frozen implementation, not an optimized rerun. This is a completed bounded baseline evaluation with serious coverage limitations, not approval for product exposure. The machine-readable [development artifact](validation/editorial-real-world-v1.json) records frozen code/gold digests, acquired snapshots, human gold, raw dry-run summaries and deterministic metrics.

**Independent editorial-v5 holdout validation pending.** Phase 4 production exposure of editorial extraction is not fully validated. C5 discovery is stopped; no acquisition of its proposed eight URLs is authorized by this Git organization task. Previously accepted PostgreSQL verification remains 694 tests / 4303 assertions; the full suite is not rerun here.

## Methodology

Mandatory sequence: select and approve sample → register sources → human operator attests ownership/applicability/access/robots/retention → existing controlled OfficialSources approve/fetch → immutable snapshots in isolated local PostgreSQL validation database → human inspect snapshots and freeze annotations → current extractor dry-run → deterministic exact comparison → failure analysis. Do not change extraction code or use its proposals to build gold labels. Do not use DOAJ/Crossref/OpenAlex or search snippets as expected facts.

Current catalog inspection used the existing isolated test database: zero journals and zero identifiers. Production/development catalogs were not inspected or altered. Canonical journal IDs are null. Any new journal setup must remain validation-only, inactive and unpublished, with identity provenance; do not fabricate provider evidence or use factories as live snapshots. Create a separate validation database before approved acquisition so PHPUnit RefreshDatabase cannot erase the corpus.

The eight proposed pages below are operator-discovery candidates, not approved sources or stored observations. Search results are used only to identify candidates and identity sources. Candidate URLs, identity, applicability and access conditions still require operator confirmation. No source rights are inferred from an article's open-access license.

## Frozen baseline

- HEAD: `3d52182bd57d6c5c562949ae3324e6310437aee8`
- Rule registry: `editorial-v2`
- Preparation: `editorial-dom-v2`
- SHA-256 digests: all extraction files and shared assertion/conflict/publication files in the JSON artifact.
- Production changes: NONE.
- First frozen run completed after explicit human gold validation. All recorded code digests remained identical before and after extraction.

## Journal sample

Proposed heterogeneous sample; no benchmark coverage claims are made from this selection.

| Journal | eISSN | Publisher | Proposed split | Rationale |
| --- | --- | --- | --- | --- |
| PLOS ONE | 1932-6203 | PLOS | development | Non-profit OA; long multi-topic guidelines, language and external submission evidence. |
| Scientific Reports | 2045-2322 | Springer Nature | development | Large commercial publisher; structured guidelines, templates and word scopes. |
| Sensors | 1424-8220 | MDPI | development | OA publisher with detailed author guidance; source URL requires operator confirmation. |
| Frontiers in Psychology | 1664-1078 | Frontiers | development | Article-specific requirements and unsupported article labels; heterogeneous site structure. |
| Journal of Open Source Software | 2475-9066 | Open Journals | development | Community software journal; candidate for explicit no-fee evidence without assuming it. |
| IUCrJ | 2052-2525 | International Union of Crystallography | holdout | Society journal; numbered headings, approximate versus maximum limits, waiver prose. |
| PeerJ | 2167-8359 | PeerJ | holdout | Additional publisher structure; candidate instructions URL requires operator confirmation. |
| eLife | 2050-084X | eLife Sciences Publications | development | Non-profit publishing model; less structured prose and unsupported peer-review observations. |

Identity references and official home URLs are stored individually in the JSON artifact. PeerJ identity uses the primary ISSN registry; all identity references are bibliographic discovery references, never editorial gold truth. The holdout designation is provisional until human sample approval. Keep holdout annotations/extractions unavailable to rule design if fixes are later considered.

## Source pages

| Journal key | Role | Candidate URL | Status |
| --- | --- | --- | --- |
| plos-one | guidelines | [https://journals.plos.org/plosone/s/submission-guidelines](https://journals.plos.org/plosone/s/submission-guidelines) | NOT_ATTEMPTED |
| scientific-reports | guidelines | [https://www.nature.com/srep/author-instructions/submission-guidelines](https://www.nature.com/srep/author-instructions/submission-guidelines) | NOT_ATTEMPTED |
| sensors | guidelines | [https://www.mdpi.com/journal/sensors/instructions](https://www.mdpi.com/journal/sensors/instructions) | NOT_ATTEMPTED |
| frontiers-psychology | article_types | [https://www.frontiersin.org/journals/psychology/for-authors/article-types](https://www.frontiersin.org/journals/psychology/for-authors/article-types) | NOT_ATTEMPTED |
| joss | editorial | [https://joss.theoj.org/about](https://joss.theoj.org/about) | NOT_ATTEMPTED |
| iucrj | guidelines | [https://journals.iucr.org/m/services/notesforauthors.html](https://journals.iucr.org/m/services/notesforauthors.html) | NOT_ATTEMPTED |
| peerj | guidelines | [https://peerj.com/about/author-instructions/](https://peerj.com/about/author-instructions/) | NOT_ATTEMPTED |
| elife | editorial | [https://elifesciences.org/about/aims-scope](https://elifesciences.org/about/aims-scope) | NOT_ATTEMPTED |

Initial plan: eight editorial pages, one per journal; six approved fetch operations completed, four snapshots acquired. Maximum ten journals and three approved editorial pages per journal. Additional roles require explicit recorded selection within that cap; do not opportunistically replace difficult or blocked pages. No crawl, recursive links, templates, browser, login or bot-protection workaround. Existing 30-second cadence, size/MIME/charset/DNS/TLS/origin restrictions apply without overrides. No page is approved until the human operator's documented review is available.

## Annotation policy

A human reviewer must inspect the actual stored snapshot before seeing extractor output. Your phase request explicitly forbids an LLM deciding TP/FP/FN; this agent therefore cannot provide gold labels or interpret correctness on behalf of the reviewer.

For every relevant observation record: unique annotation ID, journal key, source_record_id/fingerprint, fact type, normalized value, complete semantic scope, original DOM/line locator, minimal evidence (at most 25 words total per page), human confidence explicit/ambiguous/unsupported and human reasoning. Source locators must resolve against the original immutable snapshot; no evidence from current browser/search text substitutes for the snapshot. The reviewer must attest page-level coverage of all evaluated supported facts before metrics are permitted. Unknown/ambiguous content stays unknown.

Supported targets: fees/APC/explicit no APC, languages, mapped article types, maximum word limits, supported named reference styles, official templates and submission links. Explicit representable facts count as positives even when V2's narrow grammar misses them; do not relabel grammar misses as unsupported to improve recall. Canonical model limitations (e.g. peer review, unsupported article identities or conditional applicability) remain OOS or abstention observations as the reviewer determines. Never infer whole-manuscript language from an English abstract requirement.

## Matching policy

Freeze this policy with human gold before extraction. Match within a single journal and snapshot. A TP requires equality of fact type, normalized value and complete scope. Normalize only representational differences: monetary decimal grouping/scale to exact decimal strings, uppercase explicit currency, vocabulary codes where the existing mapping is explicit. No currency conversion, label similarity, edition guessing or cross-journal matching. Deduplicate equivalent facts within one snapshot; reject duplicate annotation IDs and contradictory positive labels rather than silently resolving them.

Fee identity includes kind, exact amount, currency, article/language applicability and conditional scope. Explicit no APC is a separate presence claim with null monetary amount/currency; it is not unknown, zero or waiver. Word identity includes requirement kind, operator, numeric/text value, abstract/manuscript section and explicit article type. Link identity requires semantic role and exactly resolved HTTP(S) URL; template origin policy remains unchanged. Never fetch link targets.

Wrong value/scope counts as **one FP proposal plus one FN expected fact**, not partial TP. Unmatched proposals are FP only after human annotation coverage is complete. Every explicit representable gold fact not matched is FN. Correct abstention counts annotated ambiguous/unsupported observations for which no conflicting universal proposal was emitted. Human-marked OOS and safely blocked acquisition are reported separately and excluded from recall. Do not classify unattempted acquisition or missing approval as AB.

Metrics: precision TP/(TP+FP), recall TP/(TP+FN), F1 2TP/(2TP+FP+FN); false-positive rate FP/(TP+FP) is the false discovery fraction, not a specificity measure. Zero denominators yield null/undefined, never fabricated 100%. Group by supported fact type and journal/publisher, preserving exact counts and small-sample limitations. CA/OOS counts use unique reviewed observations, not arbitrary page text units.

## Baseline results

Human gold: six positive facts, four ambiguous grouped observations and nine OOS observations. Six approved acquisition operations yielded four snapshots; two acquisitions were blocked and two candidate pages were not approved. The latter categories are excluded from recall. One acquired snapshot failed preparation; its four representable expected positives remain FN.

| Measure | Frozen baseline |
| --- | ---: |
| Proposals emitted | 0 |
| TP | 0 |
| FP | 0 |
| FN | 6 |
| CA | 1 |
| Ambiguous observations not evaluated | 3 |
| Annotated OOS observations | 9 |
| Acquisition blocked (AB) | 2 |
| Policy holds (not fetched) | 2 |
| Extraction failures | 1 |
| Precision | undefined (no proposals) |
| FP rate | undefined (no proposals) |
| Recall | 0% |
| F1 | 0, using 2TP/(2TP+FP+FN) |

Zero FP does **not** demonstrate 100% precision, >=98% precision or useful coverage. CA is limited to completed extraction with no proposals; failed PLOS preparation is not counted as three correct semantic abstentions. OOS is an annotation inventory, not a count of every unsupported statement on the pages; two PLOS OOS observations were not processed because that entire extraction failed. PeerJ's empty body is a page limitation, not an invented CA/OOS fact.


## Results by fact type

| Fact type | Expected positives | TP | FP | FN | Recall |
| --- | ---: | ---: | ---: | ---: | --- |
| Explicit no APC | 1 | 0 | 0 | 1 | 0% |
| Manuscript language | 1 | 0 | 0 | 1 | 0% |
| Abstract maximum | 1 | 0 | 0 | 1 | 0% |
| Reference style | 1 | 0 | 0 | 1 | 0% |
| Submission URL | 2 | 0 | 0 | 2 | 0% |
| Article types / other fees / manuscript maxima / templates | 0 | 0 | 0 | 0 | undefined |

Precision is undefined for every type because no proposals were emitted. eLife domain-qualified types are excluded OOS, never universal positive targets.

## Results by journal

| Journal / publisher | Run | TP | FP | FN | CA | OOS |
| --- | --- | ---: | ---: | ---: | ---: | ---: |
| PLOS ONE / PLOS | preparation failed | 0 | 0 | 4 | 0 | 2 |
| JOSS / Open Journals | completed, no proposals | 0 | 0 | 2 | 0 | 0 |
| eLife / eLife Sciences Publications | completed, no proposals | 0 | 0 | 0 | 1 | 7 |
| PeerJ / PeerJ | completed, empty editorial body | 0 | 0 | 0 | 0 | 0 |

Frontiers and IUCrJ: acquisition blocked. Scientific Reports and Sensors: policy holds. No TP/FP/FN is attributed to these four unfetched pages. PLOS and JOSS recall is 0%; eLife/PeerJ recall is undefined. Publisher figures are identical to these one-journal samples and do not estimate publisher-wide performance.

## False positives

Observed FP: **0**, with **0 proposals**. No eLife type was generalized into global journal support. This is absence of output, not a precision estimate or proof of future safety.

## False negatives

Six FN: four PLOS positives lost to the 512-unit preparation cap, plus JOSS explicit no-APC and its submission URL. The cap rejected the 513th eligible unit (PrepareDocument.php line 268); the original DOM has only 1669 elements, so the 4096-element limit is not the cause. This is a retained failed baseline result, not AB/OOS.

JOSS's no-APC paragraph is under Cost and Sustainability Model, outside the strict supported-heading whitelist; its longer prose also falls outside FeeRule's exact statement grammar. The submission link is in excluded header/navigation and its short Submit label is outside LinkRule's grammar. These are real misses despite conservative intentions. No threshold, heading whitelist, grammar, nav policy or source transport was changed to improve scores.

## Correct abstentions

One CA: the human-marked ambiguous eLife submission-pathway observation, on a completed page with no emitted proposal. Three PLOS ambiguous observations remain unassessed because preparation failed.

## Out-of-scope observations

Nine annotated OOS: two eLife mapped article labels with unsupported scientific-domain applicability, five eLife categories without an explicit V1 vocabulary mapping, PLOS absence of a numeric length ceiling and PLOS external OSF templates. Their restrictions/raw meanings remain in the benchmark; none becomes a globally accepted type, invented maximum or same-origin template. Other unannotated out-of-scope content is not used to inflate counts.

## Acquisition failures

Frontiers failed with official_response_too_large (existing 256 KiB bound); IUCrJ failed with official_forbidden (HTTP 403). Complete sanitized errors were recorded; no retry, alternate acquisition, size/security change or code fix. IUCrJ was deactivated after the access refusal. Scientific Reports and Sensors remain unfetched DO_NOT_APPROVE policy holds. Search retrieval is not OfficialSources acquisition.

## Root-cause analysis

Baseline diagnostics: PLOS whole-document unit bound; JOSS supported-context/grammar and navigation exclusions; PeerJ JS-dependent acquired shell; eLife unsupported heading and excessive ancestry depth plus domain-scope model limitation. eLife's article-type unit is ambiguous at the eight-level structural bound. These diagnostics explain current output; they do not establish how a modified rule would behave. Any future general-purpose improvement requires separate synthetic regressions and revalidation, preserving this baseline and the domain restriction.

## Production fixes, if any

NONE. All frozen production code is unchanged. No publisher-specific selectors, network feature, LLM evaluator or canonical validation labels were introduced.

## Post-fix results, if applicable

NOT APPLICABLE.

## Holdout results

No effective positive-fact holdout evaluation: IUCrJ acquisition was blocked and PeerJ has no readable editorial body or expected positive facts. PeerJ completed the frozen extraction with no proposals. No production rules were changed using either journal.

## Limitations

This artifact prepares a benchmark; it does not validate real editorial extraction, source rights or acquisition reliability. Explicit human source approval is required by [Official sources V1](OFFICIAL-SOURCES-V1.md#registry-and-approval): “No title similarity, aggregator-returned domain or LLM can approve a source.” The phase request separately requires human gold and prohibits LLM correctness labels. These are actual workflow constraints, not a new inferred approval policy.

No full publisher HTML, template, cookie, token, secret or private research is included. Only identity/URL/rationale, frozen-code hashes and methodology are retained. Live raw snapshots may be retained only in the approved isolated database with reviewed rights.

## Recommendation for Phase 4

**NOT_READY** for exposing real editorial extraction in Phase 4: recall is 0%, precision cannot be estimated, PLOS preparation failed and the sample/holdout is substantially reduced by acquisition and rendering limitations. This is lack of validated evidence, not a measured extractor failure. Next work should be separately scoped: generalizable handling of bounded large guideline documents, explicit prose/link coverage with safe scope retention, and broader approved static evidence. No replacement pages or security relaxation is implied. Phase 4 catalog/discovery planning may continue, but this baseline does not qualify editorial coverage for exposure. Guidance remains ideal precision 100%, preferred >=98% before exposure; no benchmark pass threshold or readiness is invented.


## Preparation checks

JSON artifact parsed successfully; eight unique journals/pages, ISSN checksums and query/fragment-free HTTPS candidate URLs checked. All recorded frozen SHA-256 digests match current code and HEAD. Annotation/proposal arrays are empty and metrics null. Markdown validation: 171 local file targets, zero missing. `git diff --check` passed. No benchmark tooling or application code was added; no PHPUnit suite rerun is claimed. These checks validate preparation only, not editorial reliability.

Files changed: this report and validation/editorial-real-world-v1.json. Both remain uncommitted. No branch, commit, push, installation, source approval or official acquisition. Phase completion remains pending the human operations above.


## Human authorization and pre-fetch decision — 2026-10-13

The user is the human operator, reference `florent-3ec-validation-2026-10-13`, and will validate annotations before the benchmark. Authorization is limited to the eight exact manifest URLs: official public unauthenticated pages, successful existing DNS/SSRF/TLS safeguards, no clearly applicable robots/access prohibition, restricted internal snapshot retention and no secondary link/template acquisition. This supersedes the earlier pending-operator status; it does not constitute human gold approval.

The final eight-URL table was displayed in commentary before any editorial fetch. Scientific Reports is DO_NOT_APPROVE because robots explicitly rejects multiple AI agents and applicability is uncertain; Sensors is DO_NOT_APPROVE because robots verification returned 429. Neither editorial URL is requested. No replacement is authorized. PLOS, Frontiers, JOSS, IUCrJ, PeerJ and eLife are eligible under the stated conditions, with final safeguards enforced by actual OfficialSources register/approve/fetch services.

Robots review is a separate finite operator activity, not editorial acquisition. Five direct policy reads used existing OfficialUrl/PublicDns checks and TLS-verified public-IP-pinned requests, no proxy/auth/redirects and a 64 KiB bound. Nature/Frontiers policy reads used the research tool; MDPI's 429 is recorded without retry. No robots page becomes an editorial snapshot or gold fact.

A dedicated `fikarohana_3ec_validation` database was created on the existing isolated PostgreSQL test container and migrated through the actual migrations. It is separate from PHPUnit's fikarohana_test; storage is ephemeral across container restart. Development/production database and .env are untouched. Validation journal identities are inactive local containers with IDs recorded separately as validation_journal_id; canonical catalog IDs remain null. No fabricated provider observations or canonical ISSN assertions are created to seed them. Actual registered/approved sources and acquired SourceRecords supply provenance.


## Acquisition outcome and annotation review packet

The actual 3E-A workflow acquired four immutable official snapshots (all HTTP 200 / text/html):

| Journal | Requested/final URL | Snapshot ID | Bytes |
| --- | --- | --- | ---: |
| PLOS ONE | https://journals.plos.org/plosone/s/submission-guidelines | 01a133a9-2611-7302-a1a5-7b27b4698bcf | 188788 |
| JOSS | https://joss.theoj.org/about | 01a133aa-2038-73ec-9502-04c4d4372d2b | 153129 |
| PeerJ | https://peerj.com/about/author-instructions/ → https://peerj.com/journals/peerj/author-instructions/ | 01a133ab-88d3-7391-9d66-82717b8fc4e0 | 40135 |
| eLife | https://elifesciences.org/about/aims-scope | 01a133ac-062c-7152-930d-119647199623 | 147085 |

Source IDs, retrieval timestamps, final URLs, MIME, body hashes and content fingerprints are in the JSON manifest. PeerJ's same-origin redirect follows the unchanged 3E-A policy; no destination source was separately approved or fetched outside that controlled operation. Six source operations made between seven and eight editorial requests under the existing two-redirect cap; exact hop counts are not exposed by the client summary. No secondary link or template was fetched. Robots reads were separate finite policy checks.

Six **DRAFT candidates, not gold labels**, are supplied for the human reviewer:

| Draft ID | Snapshot | Candidate normalized fact | Candidate scope |
| --- | --- | --- | --- |
| plos-one-draft-1 | PLOS ONE | accepted language en | manuscript, no article restriction |
| plos-one-draft-2 | PLOS ONE | reference_style / text / Vancouver | references, no article restriction |
| plos-one-draft-3 | PLOS ONE | abstract_words / max / 300 | abstract, no article restriction |
| joss-draft-4 | JOSS | explicit no APC, null amount/currency | unspecified article scope |
| elife-draft-research_article | eLife | accepted research_article | journal subject areas |
| elife-draft-review_article | eLife | accepted review_article | journal subject areas |

Original DOM locators and minimal fragments were mechanically checked against stored snapshots, without running PrepareDocument or any extraction rule. Fragments total 11 words for PLOS ONE, 6 for JOSS and 4 for eLife, all below the per-page ceiling. These are proposed observations for human correction, not LLM correctness classification or confirmed facts. Confidence and classification stay null; canonical annotations/extractions remain empty.

PeerJ's title identifies author instructions, but body text after operator inspection removes scripts/styles/noscript is empty. No browser/JS/resource rendering was attempted. A pending review observation records this acquisition limitation; it is not preclassified CA/OOS/FN. eLife's subject-area applicability must be considered by the human reviewer before approving its article-type candidates. The reviewer must additionally inspect supported fact coverage and ambiguous/unsupported observations on every acquired page; six drafts alone do not establish exhaustive gold or overall recall.

No holdout rule design or production fix occurred. IUCrJ holdout acquisition is blocked; PeerJ holdout has no readable editorial text in this snapshot. Do not report an effective two-journal holdout benchmark.

Database count at completion: six inactive validation journals, four SourceRecords, zero assertions, zero journal revisions and zero catalog revisions. The authorizations/evidence are local validation data only. Frozen code digests and .env remain unchanged.

Remaining human gate: approve/correct/add the draft annotations and relevant CA/OOS observations, attest page coverage, then freeze that human gold before extractor execution. No metrics or phase PASS is claimed while this gate is pending. Previous preparation-only sections record methodology; current acquisition counts above supersede the earlier zero-attempt state.


## Human gold freeze and completed baseline — final record

The user explicitly validated six safe positive facts and four-snapshot coverage before extraction. Gold SHA-256: `b25dcecd06ec489e3845b0d768edca8e005306fa0ae083631da38c3befa796ac`. PLOS abstract evidence retains both contextual heading/list locators. JOSS submission evidence retains navigation_origin=true. Duplicate no-APC wording is corroboration of one fact, not extra positives.

The two eLife observations retain their original labels and explicit scientific-domain restriction, but are OOS for the V1 normalized matcher because canonical membership/proposals cannot carry that applicability condition. They are not stored as global positive facts. A synthetic regression proves that a hypothetical unqualified eLife-type proposal would be FP, not TP or silently ignored OOS.

Actual ExtractEditorialSnapshot ran on all four stored SourceRecords with dryRun=true inside a PostgreSQL READ ONLY transaction. Body hashes/fingerprints and frozen production code digests were checked. All 37 public-table row counts before/after match; zero database writes, assertions/reviews or revisions. Laravel stray HTTP was prohibited; no provider/website call, linked resource, JS or LLM evaluator was used. The manifest records the failure and all completed empty proposal arrays without copying raw HTML.

[compare_editorial.py](validation/compare_editorial.py) is a small development-only pure comparator plus file-reading/stdout CLI. It has no application integration, network, DB or write capability. Human annotations, code/snapshot/gold hashes and matching rules remain separate from canonical data. Run locally:

```bash
PYTHONDONTWRITEBYTECODE=1 python3 docs/development/validation/compare_editorial.py docs/development/validation/editorial-real-world-v1.json
PYTHONDONTWRITEBYTECODE=1 python3 -m unittest discover -s docs/development/validation -p 'test_compare_editorial.py' -v
```

### Failures and verification

- Frozen production extraction rejected PLOS with editorial_document_limit. Read the complete sanitized failure and preparation stack location; identified the 512-unit cap. Retained failure and four FN; no production correction, truncation, limit increase or hidden skip.
- The first gold-hash check after PHP capture failed. PHP associative JSON round-trip converted empty objects in non-positive annotation value/scope into empty arrays. Restored only these empty-object representations and verified the **original** frozen gold hash; no semantic label/value/scope change. Re-ran the exact hash gate first, then the complete comparator/test suite. Added a digest regression to prevent unnoticed gold changes.
- Comparator: **15 synthetic unittest tests passed**. Tests cover TP/FP/FN, CA, OOS/AB exclusions, failed-page counting, normalized fee/currency/absence/zero distinctions, word/article scope, exact links, malformed/duplicate input, deterministic metrics, gold immutability and a pure no-IO calculation. No test disabled or SQLite used. Canonical zero writes are additionally enforced by the real baseline's PostgreSQL read-only transaction and table counts.

No production fix, post-fix metrics or successful holdout precision claim. The earlier preparation/annotation-review sections above are historical stages; current frozen baseline results supersede their pending statuses. Phase verdict: **PASS WITH LIMITATIONS** for conducting and recording the honest bounded evaluation; **NOT_READY** for product exposure of editorial extraction. This is not a benchmark-success or precision-threshold claim.


### Final quality gates and inventory

Pint --test PASS; Composer validate with network disabled PASS; OpenAPI validation and generated-contract reproducibility PASS; git diff --check PASS; 173 local Markdown targets, zero missing. Comparator metrics reproduce exactly; gold digest, frozen code, HEAD and .env unchanged. No production code was changed, so no new full Laravel run is claimed; the real four-snapshot read-only extraction and 15 comparator tests are the checks performed for this validation layer. Composer audit was not run in this continuation: no new network requests were needed or made.

Five uncommitted files for completed 3E-C: docs/development/EDITORIAL-REAL-WORLD-VALIDATION-V1.md, docs/development/validation/editorial-real-world-v1.json, docs/development/validation/compare_editorial.py, docs/development/validation/test_compare_editorial.py and docs/development/TESTING.md. Synthetic tooling tests contain no publisher HTML. Full real HTML remains only in restricted local PostgreSQL snapshots; no copied template/cookie/auth/private user data in Git. No branch, commit or push.


## Phase 3E-C2 — POST-FIX editorial-v3 / editorial-dom-v3

This appended section leaves the complete frozen V2 baseline, gold, sample, acquisition outcomes and failure analysis above intact. The [separate post-fix dataset](validation/editorial-real-world-v1-postfix.json) reuses the identical human gold and four snapshot IDs/fingerprints; the original baseline JSON is byte-identical to its pre-remediation copy. No new acquisition was performed. A five-TP intermediate V3 attempt, before fixing Unicode quote parsing, remains recorded separately for audit.

### Root causes and general remediation

PLOS's 513th general evidence unit caused whole-document V2 rejection. V3 scans all bounded DOM blocks and applies a lexical rule-candidate gate before budgeting evidence. Paragraphs with no supported field/style/language/word/type vocabulary are not emitted; this does not remove their headings, qualifier structure or original snapshot bytes. No first/last-unit slicing, ranking by publisher or specific DOM path. Eligible overflow still fails safely; arbitrary prose remains unsupported.

Current exact bounds: raw input 262144 bytes; 4096 DOM elements; individual complete text/anchor 1024 bytes; textual channel 512 candidate units and 131072 total normalized text bytes; link channel 128 eligible anchors and 16384 total normalized anchor bytes; heading/context text 128 bytes; locator 512 bytes; href/resolved URL 2048 bytes; eight persisted contexts; ancestry traversal 16 levels; 128 distinct proposals; extraction metadata <=6000 encoded bytes before existing 8192-byte SQL ceiling. Label-plus-unit regex input is bounded by 1024+128+2 bytes; lexical candidate gate by 1024+128+1 bytes. No user regex or unbounded HTML matcher. At most 4096*16 ancestor steps; emitted text remains at most 128 KiB plus 16 KiB anchors. Per-channel budgets bound total work without depending on irrelevant paragraph position. PLOS now produces 176 text candidates / 37045 text bytes and one eligible link.

PreparedDocument now separates textual units and link units. Only syntactically explicit submission-action anchors survive header/nav/footer filtering; navigation prose remains excluded from every textual rule. Main-body anchors also pass exact semantic-label eligibility before the link budget. Unsafe/fragment hrefs, unknown/example/conditional context and external-template policy remain unchanged. External submission links are metadata only: no DNS/HTTP/approval or source trust inheritance.

Rules add bounded explicit negative fee statements, including zero publication fees (article processing charges), no APC and authors pay no publication fee. The supported journal copula sentence may surround the zero-fee clause; unsupported trailing conditions do not match. Some/only/invited/eligible/promotional/waiver/additional/beyond/information/for/except language vetoes this absence grammar. Null amount/currency and existing absence basis remain; zero money, unknown, waiver and absence are distinct.

Generic section labels now include submission guidelines, style/format, language, parts of a submission and cost/sustainability model; arbitrary unknown or qualified headings still abstain. ReferenceStyleRule recognizes a bounded named subject uses named-style sentence, with Unicode quote support and explicit condition veto. A structurally associated Abstract/Manuscript should list introduction supplies only that local scope for Not exceed N words. Minimum/exact/range and conflicting article context remain unsupported. No journal name, publisher/hostname check or exact locator branch was added.

Registry/preparer advance to editorial-v3/editorial-dom-v3. A forward migration expands the existing metadata CHECK to V3 with mandatory valid contexts; committed V1/V2 migrations and persisted history remain unchanged. Populated V3 rollback refuses safely. No parallel provenance, enum, canonical money/requirement or publication/conflict model change. Real same-snapshot revalidation uses dry-run only; no validation-DB migration or canonical writes were necessary.

### Side-by-side frozen results

| Measure | BASELINE V2 | POST-FIX V3 |
| --- | ---: | ---: |
| Proposals | 0 | 6 |
| TP | 0 | 6 |
| FP | 0 | 0 |
| FN | 6 | 0 |
| CA | 1 | 1 |
| OOS inventory | 9 | 9 |
| AB | 2 | 2 |
| Policy holds | 2 | 2 |
| Extraction failures | 1 | 0 |
| Precision | undefined | 100% |
| Recall | 0% | 100% |
| F1 | 0 | 1 |
| FP rate | undefined | 0% |

The unchanged comparator conservatively leaves three ambiguous observations uncredited on pages with positive outputs, pending finer human attribution; it does not inflate CA because PLOS now completes. OOS is an annotation inventory, not successful extraction. The six validated positives, not emitted proposals or successfully parsed pages, determine recall. No gold labels were altered. All 37 public-table counts match under the final PostgreSQL READ ONLY run.

| Fact type | V2 TP/FP/FN | V3 TP/FP/FN |
| --- | --- | --- |
| Manuscript English | 0/0/1 | 1/0/0 |
| Vancouver style | 0/0/1 | 1/0/0 |
| Abstract max 300 | 0/0/1 | 1/0/0 |
| Explicit no APC | 0/0/1 | 1/0/0 |
| Submission URL | 0/0/2 | 2/0/0 |

PLOS has four TP; JOSS two TP; eLife and PeerJ emit nothing. No serious FP observed. eLife's scientific-domain restriction remains OOS and never global support. PeerJ is not rendered or fetched again. Frontiers size failure, IUCrJ 403 and Nature/MDPI holds remain unchanged.

### Failures encountered and fixed in C2

- First PHPUnit invocation used vendor/bin from repository root and failed before tests; corrected only working directory.
- First focused run produced 20 migration errors before assertions. Read the full representative SQL error: PDO treated JSONB ?& as placeholders in prepared DDL. Switched only the server-generated trusted CHECK restoration to DB::unprepared; reran the exact failing dataset first, then all focused/extraction tests. Atomic migration behavior and constraint guards remained intact.
- New V2-history fixture omitted required fee applicability and returned PostgreSQL 23502. Read error/model, added that synthetic field, reran that method first, then focused/extraction suites. No model constraint was weakened.
- First V3 real replay had five TP and one FN: named-style Unicode quotes were matched without UTF-8 regex mode. Added generic quoted-style synthetic regressions, corrected only the bounded grammar's Unicode modifier, then reran tests and the same snapshots. This attempt is retained in the post-fix artifact.
- A condition veto overmatched Example in the synthetic subject Example Journal, failing two tests. Removed that text keyword from the new veto while keeping structural example-section abstention; the two exact tests passed first, followed by focused/extraction/full suites. Tests were not skipped/deleted; no network or dependency fix.

### Verification

| Gate | Result |
| --- | --- |
| Final remediation focused | 25 tests / 143 assertions PASS |
| EditorialExtraction | 175 tests / 1304 assertions PASS |
| Catalog/publication + ingestion | 63 tests / 471 assertions PASS |
| OfficialSource | 118 tests / 463 assertions PASS |
| OpenAlex | 64 tests / 376 assertions PASS |
| Crossref | 49 tests / 267 assertions PASS |
| DOAJ API/CSV | 107 tests / 566 assertions PASS |
| Full Laravel PostgreSQL | 619 tests / 3756 assertions PASS |
| Unchanged comparator | 15 tests PASS |
| Pint / Composer validate | PASS / valid, network disabled |
| OpenAPI / generated contracts | PASS / reproducible |

No SQLite, disabled test, installation, source download, branch, commit or push. Composer audit is NETWORK-UNVERIFIED: no new network/advisory request in this offline remediation. Required OfficialSource regression tests use their existing finite local loopback fixture, not external acquisition.

### Independent holdout approval gate

No positive independent holdout exists. V3 recovers the development set's six positives; these tiny development results do not establish general accuracy or Phase 4 readiness. No further rule design should use new holdout pages. Proposed additional pages, not requested or approved:

| Journal | Publisher | Exact candidate URL | Role | Rationale |
| --- | --- | --- | --- | --- |
| Journal of Machine Learning Research | JMLR | https://www.jmlr.org/author-info.html | guidelines | Different journal-owned author-info layout; candidate static editorial text and submission links |
| BMC Medicine | Springer Nature / BMC | https://link.springer.com/journal/12916/submission-guidelines/research-article | guidelines | Independent commercial-publisher structured article instructions; possible language/abstract/reference observations |

These exact URLs require human ownership/applicability/robots/access/retention review and existing 3E-A checks; current content, reachability and >=3–5 supported facts are **not verified**. No Nature/MDPI source is reused. Only after explicit operator approval may they be acquired; human holdout gold validation must precede editorial-v3 execution. STOP at this gate. **Phase 4 readiness: NOT_READY.**


### Final C2 inventory and verification

178 local Markdown targets, zero missing; git diff --check PASS. Baseline JSON is byte-identical; the full prior report is retained as an unchanged prefix. Gold, snapshot hashes/IDs, baseline metrics, comparator source/tests, acquisition/conflict/publication code, .env and HEAD are unchanged. Post-fix metrics reproduce exactly. No new network request, source approval, holdout fetch, installation, branch, commit or push.

18 files changed by C2 (21 files total currently uncommitted, including the three unchanged pre-existing baseline/comparator artifacts):

```text
apps/api/app/Services/EditorialExtraction/ExtractEditorialSnapshot.php
apps/api/app/Services/EditorialExtraction/FeeRule.php
apps/api/app/Services/EditorialExtraction/LinkRule.php
apps/api/app/Services/EditorialExtraction/PrepareDocument.php
apps/api/app/Services/EditorialExtraction/PreparedDocument.php
apps/api/app/Services/EditorialExtraction/ReferenceStyleRule.php
apps/api/app/Services/EditorialExtraction/RuleContext.php
apps/api/app/Services/EditorialExtraction/RuleRegistry.php
apps/api/app/Services/EditorialExtraction/WordLimitRule.php
apps/api/database/migrations/2026_10_13_120000_allow_editorial_v3_metadata.php
apps/api/tests/Feature/EditorialExtractionTest.php
apps/api/tests/Feature/JournalCatalogTest.php
docs/architecture/DECISIONS.md
docs/development/EDITORIAL-EXTRACTION-V1.md
docs/development/EDITORIAL-REAL-WORLD-VALIDATION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/development/validation/editorial-real-world-v1-postfix.json
```


## Independent holdout — human authorization, acquisition only

The user subsequently conditionally authorized only JMLR https://www.jmlr.org/author-info.html and BMC Medicine https://link.springer.com/journal/12916/submission-guidelines/research-article under `florent-3ec-holdout-2026-10-13`. This revokes the pending-source-approval stop only for these exact pages; human gold validation is still mandatory before any holdout extraction/metrics. Frozen V3 code hashes were checked before acquisition and no changes are designed from these sources.

JMLR robots returns 404 with valid public DNS/TLS: no directive exists to inspect, and editorial public access remains subject to the unchanged 3E-A client. BMC robots returns 200: wildcard /journal* allow coexists with complete prohibitions for named AI agents. Applicability remains uncertain, so this agent conservatively marks the page DO_NOT_APPROVE consistently with the earlier Nature decision; no editorial request, source approval or alternative user-agent/transport. One repeated operator robots check was needed because the first Springer response omitted Content-Type and the policy-inspection helper initially suppressed its body; the repeat retained the same DNS/TLS/size/no-redirect controls. Application acquisition MIME/security logic was not altered.

The [separate holdout artifact](validation/editorial-holdout-v1.json) records source authorization, current policy decision and acquisition IDs/fingerprints. Editorial acquisition uses actual OfficialSources register → approve → fetch in the restricted validation database; operator policy discovery is not a canonical source snapshot or benchmark truth. No linked scripts/assets/templates or other editorial URLs are acquired. The target of two usable holdout journals with >=3–5 supported facts is not fulfilled by merely selecting these pages; availability/usable facts and human review must be reported honestly.


### Holdout acquisition result and candidate gold packet — no benchmark

JMLR actual 3E-A acquisition succeeded: requested/final URL https://www.jmlr.org/author-info.html, HTTP 200, text/html, 22728 bytes, SourceRecord `01a13487-40bf-7275-936b-ddd3cf3e9ed3`, source `01a13486-c422-709f-a402-662b9ec9a64c`, fingerprint `c7887cc2423829c978f0ab0fae948f8c625ee91019cd2a1b256a31987a476848`. JMLR was registered to a new inactive validation-only journal, approved with the exact human operator reference and internal-retention basis, then fetched once under unchanged cadence/DNS/TLS/size policies. BMC was not registered/approved/fetched because of the documented policy-applicability hold. No other editorial URL is approved or acquired.

The JMLR snapshot contains usable static author instructions. Operator DOM inspection (NONET, no scripts/resources) mechanically validated the following five candidate locators/fragments. These **proposed statuses are not human gold, TP/FP/FN labels or inferred extractor performance**; confidence/classification remain null in the holdout artifact.

| Candidate | Normalized value / scope | Locator | Proposed status |
| --- | --- | --- | --- |
| Abstract | abstract_words / max / 200; initial-submission abstract metadata, no article type | /html/body/div[2]/ul[4]/li[4] | EXPLICIT |
| Submission portal | https://jmlr.csail.mit.edu/manudb/; journal electronic submission system | /html/body/div[2]/p[18]/a | EXPLICIT |
| Review articles excluded | review_article / supported=false; journal-wide negative, not an accepted type | /html/body/div[2]/p[7] | EXPLICIT |
| Publication fees absent | generic publication-fee absence, null amount/currency; APC subtype interpretation requires human review | /html/body/div[2]/p[31] | AMBIGUOUS for V1 normalization |
| LaTeX style file | https://github.com/JmlrOrg/jmlr-style-file; manuscript style, cross-origin | /html/body/div[2]/p[19]/a[1] | OUT_OF_SCOPE under V1 template origin policy |

Exact evidence fragments are in the holdout artifact and total **24 words** for this page, below the 25-word ceiling. The abstract is listed among information to supply at submission: preserve this stage context, not a newly assumed whole-manuscript limit. The generic no-publication-fees statement is explicit, but do not silently equate its fee subtype with APC; the human may validate a justified absence mapping or keep the claim outside the current V1 representation. Negative review_article support is representable by the canonical supported flag; absence of a matching frozen grammar is not by itself OOS. Invited surveys are not silently collapsed into review_article.

Other review notes: 35/50-page conditional review/justification thresholds are not universal numeric word maxima; invited survey eligibility is conditional; external cover-letter template and LaTeX/PDF formatting requirements are outside the evaluated grammar/origin policy. No manuscript language or named reference style is inferred from page language or linked documents. No template, submission portal, author guide, stylesheet, asset or script was requested.

Current independent holdout is only one acquired journal. The two-journal target and three-to-five **human-validated** positive facts remain unfulfilled; three EXPLICIT candidates are not an approved gold count. BMC is a policy hold, not an attempted acquisition failure or FN. No holdout ExtractEditorialSnapshot/PrepareDocument/RuleRegistry invocation or comparator run occurred. Frozen V3 hashes and both development baseline/post-fix datasets are unchanged. Holdout annotations/extractions stay empty and metrics null. STOP for explicit human gold review; Phase 4 remains NOT_READY.


## JMLR independent holdout — frozen human gold and unchanged V3 benchmark

Following the operator's explicit JMLR validation, the conditional decisions were resolved **before** extraction. Canonical JournalArticleType supports a boolean `supported`, but frozen V3 ArticleTypeRule emits only positive memberships and ExtractEditorialSnapshot materializes article_type with `supported=true`. The operator's condition requiring both V3 and canonical negative representation therefore puts the explicitly rejected review_article **OUT_OF_SCOPE** for this benchmark. This is a documented V3 capability boundary, never inverted support and never an implicit omission. The prior candidate-stage note about canonical representability alone is superseded by this narrower human condition.

The canonical fee_type constraint lists apc/submission/page/color/other and has no generic `publication_fee`. The human expressly refuses inferring APC from generic publication-fee absence: this explicit source observation is **OUT_OF_SCOPE**, not AMBIGUOUS. No amount/currency or no-APC claim is created. External GitHub template remains OOS; no link is followed.

Frozen gold: **2 positives / 3 OOS**: initial-submission abstract max=200 and JMLR submission URL are positives. Initial-submission stage remains in gold scope; unchanged comparator matches section/article type but does not separately score stage. The five original candidate proposals remain historical records with separate human resolutions. Coverage notes on page thresholds, invited surveys, external cover-letter template and LaTeX/PDF do not introduce extra scored observations.

Gold SHA-256: `eba4f7fdf8eeb92b1034788b4d4eadef2b8f9077a5769f0684ea29e054feda59`. Annotator: `florent-3ec-holdout-2026-10-13`. The human gold and policy were saved before the run and checked identical afterwards.

### Actual result — no retuning

| Measure | JMLR holdout |
| --- | --- |
| Proposals | 0 |
| TP | 0 |
| FP | 0 |
| FN | 2 |
| CA | 0 (no scored ambiguous observation) |
| OOS | 3 |
| AB | 0 |
| Policy holds | 1 (BMC, unacquired; never FN) |
| Extraction failures | 0 |
| Precision | Undefined (no predictions) |
| Recall | 0% |
| F1 | 0 |

The deterministic unchanged comparator identifies one FN for abstract_words and one FN for submission_url. Rule inspection shows frozen WordLimitRule does not recognize the relative-clause abstract wording, and LinkRule's explicit-label grammar does not include this electronic-system anchor label. These are frozen grammar coverage limitations, not execution failures. No rules/context/preparation/publication behavior was changed after observing the holdout. Zero FP with zero proposals does not establish useful recall or a robust no-APC distinction.

Actual ExtractEditorialSnapshot runs on stored JMLR SourceRecord only, `dry_run=true`, in PostgreSQL `fikarohana_3ec_validation` under READ ONLY then rollback, with Http::preventStrayRequests. All 37 public-table counts before/after remain identical. Snapshot content/fingerprint and all 14 frozen V3 source hashes were verified. No assertions/reviews/journal revisions/catalog revisions were written. No fresh network request, HTML redistribution, linked-resource download, dependency installation, branch, commit or push.

Results, captured outputs, frozen gold and database-count proof are in [the holdout artifact](validation/editorial-holdout-v1.json). Baseline V2 and development V3 datasets/metrics are unchanged. Comparator tests: **15 passed**; both historical datasets and current holdout reproduce their stored metrics. git diff --check and local Markdown-link checks pass. No test or benchmark command failure was encountered in this evaluation.

**Phase 4: NOT_READY.** JMLR holdout recall is 0%, only one journal is acquired, and there are only two validated positives. The independent target of at least two usable journals with three-to-five positives remains unmet. Stop after recording this benchmark; do not retune editorial-v3 using holdout evidence or acquire further pages automatically.


## Phase 3E-C3 — V4 remediation and generalization

### Frozen history and split change

V2 baseline (0 TP / 0 FP / 6 FN), V3 development remediation (6 TP / 0 FP / 0 FN), and the V3 independent JMLR failure (0 TP / 0 FP / 2 FN) are preserved without editing their JSON files or prior report sections. JMLR is now **DEVELOPMENT/REMEDIATION DATA**, never an independent holdout for V4. BMC remains DO_NOT_APPROVE; no editorial request or additional approval. Gold values, dispositions, scope, annotation policies and SHA-256 are identical in each separate V4 replay artifact. Do not improve results by excluding either expected JMLR positive.

### JMLR root cause — measured before changes

[Diagnostics](validation/editorial-jmlr-v3-v4-diagnostics.json) record normalized inputs, original locators, channel membership and context. Actual snapshot reloading and inert DOM inspection ran in the isolated validation database before code edits. A temporary diagnostic copy of PrepareDocument outside the repository logged rejected context entries without altering production code or evidence.

| FN | Preparation / EvidenceUnit | Context / eligibility | Grammar / suppression |
| --- | --- | --- | --- |
| Abstract max 200 | Raw source reached PrepareDocument. Text unit exists at `/html/body/div[2]/ul[4]/li[4]`; normalized text is unchanged and local heading empty. | Three inherited headings: document author-information title, overview and submission procedure. These are not allowed GENERAL labels. In addition, p[17] immediately precedes h3[5] and is detected as example qualifier; its 272-byte text exceeds the 128-byte retained context limit, marking ambiguousContext=true. RuleContext.allows=false. | Rule exits at eligibility; even without that exit, V3 anchored grammar rejects the abstract relative-clause construction. No proposal reaches summary/materialization or the snippet/metadata suppression pass. |
| Submission portal | Raw anchor exists at `/html/body/div[2]/p[18]/a`, with the approved gold href. Preparation computes structural context but appendLink's exact label gate rejects it, so no link EvidenceUnit exists. | LinkRule never receives this anchor in V3. Its underlying computed heading/oversize qualifier context is the same as the abstract. | V3 submission-label grammar does not recognize the electronic management-system label; the same grammar would reject it in LinkRule. No URI fetching/normalization side effect or proposal suppression occurs. |

This corrects the earlier limited grammar-only inspection: grammar is not the only cause of the JMLR miss. We do **not** remove unknown headings or drop an oversize example qualifier to meet an expected score. The page's explicit facts remain gold positives; the preparer's conservative scope representation remains a documented limitation.

### V4 changes and synthetic coverage

Advance registry/default document versions to editorial-v4/editorial-dom-v4. General anchored abstract maxima accept optional definite/indefinite article, singular/plural subject, must/does/should not exceed (including relative clause), maximum and limited-to constructions, optionally ending with required. Approximation, minimum, ranges, recommendation and conditional suffixes abstain. Existing article/section/context validation still runs first.

LinkRule and preparation share a single submission-label predicate, adding electronic/manuscript submission systems, management systems and portals. Explanatory guideline/instruction/policy/preparation labels remain ineligible. Link destinations are inert metadata. No publisher, hostname, journal or locator branches. FeeRule, RuleContext and acquisition/security limits are unchanged.

Independent synthetic V4 coverage: eight positive abstract cases and eleven negative controls; six positive system/action labels and seven negative explanatory labels; six retained-context/generic-fee safety cases. Tests verify normalized values, scope, original heading+text evidence, version, pending status, locators and no HTTP. The new PostgreSQL forward migration extends existing metadata/context version lists without modifying old migrations, provenance, reviews or revisions. Tests retain V2/V3 evidence unchanged beside V4, refuse rollback when V4 evidence exists, and exercise empty rollback/reapplication.

### Same-snapshot results — no network or retuning

| Dataset | Version | TP | FP | FN | CA | OOS | Precision | Recall | F1 |
| --- | --- | --- | --- | --- | --- | --- | --- | --- | --- |
| JMLR original independent baseline | V3 | 0 | 0 | 2 | 0 | 3 | Undefined | 0% | 0 |
| JMLR development/remediation | V4 | 0 | 0 | 2 | 0 | 3 | Undefined | 0% | 0 |
| PLOS/JOSS/eLife/PeerJ development | V3 | 6 | 0 | 0 | 1 | 9 | 100% | 100% | 1 |
| PLOS/JOSS/eLife/PeerJ development | V4 | 6 | 0 | 0 | 1 | 9 | 100% | 100% | 1 |

JMLR now has both eligible grammar constructions in synthetic safe contexts. On the actual page the abstract unit remains context-ineligible, and the newly retained link unit is also ambiguous/context-ineligible. Therefore both FN remain. This is **partial grammar remediation, not successful real-page coverage remediation**. No safe structural resolution was implemented in this pass; do not claim 2 TP or readiness.

PLOS retains four TP, JOSS two TP; eLife emits no globally generalized article types and retains domain-sensitive OOS. PeerJ has no usable static editorial text and emits nothing. No serious FP is observed. Zero FP from JMLR's empty predictions does not establish useful precision/recall. No publication-fee statement becomes APC, no language/style is invented, no external template is fetched.

Actual V4 replay uses the same five SourceRecords with fingerprint/content-hash checks, Http::preventStrayRequests, PostgreSQL READ ONLY then rollback and identical counts for all 37 public tables. No new assertions, reviews, journal/catalog revisions or source acquisition. Results are separate artifacts: [development regression](validation/editorial-real-world-v4-development-v1.json) and [JMLR remediation](validation/editorial-jmlr-v4-remediation-v1.json). The comparator is unchanged and assigns TP/FP/FN deterministically from frozen human gold.

### Tests, quality gates and encountered failures

| Check | Final result |
| --- | --- |
| Focused V4 + history | 34 tests / 294 assertions |
| EditorialExtraction | 208 / 1590 |
| Catalog/publication + ingestion | 63 / 471 |
| OfficialSource | 118 / 463 |
| OpenAlex | 64 / 376 |
| Crossref | 49 / 267 |
| DOAJ | 107 / 566 |
| Full Laravel PostgreSQL | 652 / 4042; 54.427 seconds |
| Comparator unittest | 15 passed |
| Pint --test | Passed after scoped formatting |
| Composer validate | Valid (network disabled) |
| Composer audit | Passed; zero advisories/abandoned packages |
| OpenAPI validation / contracts check | Valid / reproducible and current |
| git diff --check / local Markdown paths | Passed |
| Frozen baseline, V3 artifacts, gold and comparator | Unchanged |

Failures were read completely and not hidden:

1. First test-insertion Python command ran with apps/api cwd but repository-relative path, raising FileNotFoundError. It made no edit. An independently following PHPUnit command therefore ran only the existing history case; this is **not** credited as V4 regression coverage. Corrected the insertion to an absolute path, then ran the actual new tests.
2. Focused run failed eight new positive-test assertions because expected original_wording omitted the retained local author-instructions heading. Existing implementation intentionally stores heading plus full unit. Corrected only those new expected values; production logic and evidence were not weakened. A complete extraction run had already been launched before inspecting the focused failure and repeated the same eight failures; it was allowed to finish and fully inspected. Reran the specific abstract tests first (19 / 175 passed), then focused, complete extraction, dependent suites and the full Laravel suite. No failing test deleted/skipped.
3. Pint --test flagged three spacing/attribute fixers in the new test file. Applied Pint to that file only, reran Pint --test successfully; full Laravel execution followed. No dependency or security/model change was needed.

No SQLite, disabled tests, installation, force flags, unexpected dependency, branch, commit, push or history rewrite. Composer audit and search-index candidate discovery are network checks, not official-source acquisitions; no new provider/editorial snapshot was fetched.

### New independent holdout candidates — pending approval

V4 rule hashes are frozen in the [candidate manifest](validation/editorial-v4-new-holdout-candidates.json). Candidate search discovery occurred after rule implementation and tests; no search excerpt becomes gold and no rule is changed from these candidates. Only index search results were consulted: no candidate page open, robots check, register, approve, fetch, template download or benchmark.

| Journal | Publisher / platform | Exact candidate URL | Domain / role | Why useful |
| --- | --- | --- | --- | --- |
| Journal of Statistical Software | Foundation for Open Access Statistics | https://www.jstatsoft.org/style | www.jstatsoft.org; guidelines/templates | Indexed official HTML style guide; OJS structure, candidate template links/style requirements |
| Confluentes Mathematici | Centre Mersenne / Mathdoc platform; journal ownership to confirm | https://cml.centre-mersenne.org/page/instructions-latex_fr/ | cml.centre-mersenne.org; author instructions | Different CMS/publisher context; explicit multilingual eligibility candidate, translation condition needs human applicability review |

Official search discovery supports their candidacy: [JSS style guide](https://www.jstatsoft.org/style), [Confluentes author instructions](https://cml.centre-mersenne.org/page/instructions-latex_fr/). These are **provisional candidates**, not an acquired/approved or proven adequate corpus. Two usable static journals and 4–6 canonical positive facts remain the target. The actual current HTML/access/robots, template origins, scientific applicability/translation scope and gold count cannot be certified from search results; if human acquisition/annotation yields fewer supported positives, report an inadequate corpus rather than infer missing facts. None of the excluded development/acquisition-failure journals is reused.

Operator must explicitly approve exact URLs with reference/basis under official ownership, public access, unchanged 3E-A SSRF/DNS/TLS and applicable robots/access/retention conditions. Then register → approve → fetch → candidate gold → STOP for human validation → unchanged V4 evaluation. No linked asset/template/secondary URL is implicitly approved. **STOP before acquisition. Phase 4: NOT_READY.**

### Files changed by C3

```text
apps/api/app/Services/EditorialExtraction/LinkRule.php
apps/api/app/Services/EditorialExtraction/PrepareDocument.php
apps/api/app/Services/EditorialExtraction/RuleRegistry.php
apps/api/app/Services/EditorialExtraction/WordLimitRule.php
apps/api/database/migrations/2026_10_13_130000_allow_editorial_v4_metadata.php
apps/api/tests/Feature/EditorialExtractionTest.php
apps/api/tests/Feature/JournalCatalogTest.php
docs/development/EDITORIAL-EXTRACTION-V1.md
docs/development/EDITORIAL-REAL-WORLD-VALIDATION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/development/validation/editorial-jmlr-v3-v4-diagnostics.json
docs/development/validation/editorial-jmlr-v4-remediation-v1.json
docs/development/validation/editorial-real-world-v4-development-v1.json
docs/development/validation/editorial-v4-new-holdout-candidates.json
```

Prior uncommitted C2 files remain present; this inventory isolates C3 edits. PHPUnit's ignored local result cache was refreshed by tests; no tracked application/dependency/configuration file outside this list was changed by C3.


## C3 new independent holdout — authorized acquisition and candidate gold only

The human operator conditionally approves exactly JSS https://www.jstatsoft.org/style and Confluentes https://cml.centre-mersenne.org/page/instructions-latex_fr/ with reference `florent-3ec3-holdout-2026-10-13`. Justification: independent V4 generalization measurement, not rule development. Existing V4 manifest/code hashes are checked before/after; no extractor or comparator invocation and no rules/config/security changes.

Official journal/platform association for Confluentes is corroborated from primary indexed sources: [journal presentation](https://cml.centre-mersenne.org/page/presentation_fr/) explicitly names Centre Mersenne, and [platform directory](https://www.centre-mersenne.org/en/our-journals/) lists Confluentes Mathematici. These ownership-search results are not editorial snapshots/gold; neither ownership page was acquired as a SourceRecord. The authorized cml subdomain is the journal's official platform.

| Exact URL | Preflight | Acquisition outcome |
| --- | --- | --- |
| https://www.jstatsoft.org/style | PublicDns all-answer validation rejects link-local AAAA alongside public A; `official_prohibited_address`. Robots review cannot proceed safely. | ACQUISITION_BLOCKED before register/approve/editorial fetch; DO_NOT_APPROVE; no alternate resolver, IPv4-only selection, proxy or bypass |
| https://cml.centre-mersenne.org/page/instructions-latex_fr/ | Official association corroborated; pinned public DNS/TLS robots 200; wildcard excludes /search /series /lectures, not authorized path, delay 2 seconds covered by unchanged 30-second delay. | Existing 3E-A register → approve → fetch; HTTP 200, text/html, 21757 bytes, same requested/final URL |

DNS failure root was inspected locally: JSS advertises `fe80::21a:4aff:fe31:109c`, an IPv6 link-local address; fail-closed validation of every A/AAAA answer is intentional. No application logic is changed to recover the request. No request was issued to JSS robots/editorial URLs. Confluentes has source `01a134ba-24e2-72b6-8761-c9b1db3d9f0a`, SourceRecord `01a134ba-9f65-72c9-b6fe-c9e68cf575e6`, fingerprint `6b341f1b285f45d10a33822b2e725fec306382d34a1d2b5f5868d36a3c1217f3`; raw HTML is only in the restricted local validation database. This inactive validation-only journal has no invented canonical identifiers. No assets/scripts/templates/secondary editorial links are requested. Operator robots checks are bounded pinned TLS policy inspection, not arbitrary application fetch capabilities.

### Candidate gold — human decisions pending

The [separate independent holdout packet](validation/editorial-v4-independent-holdout-v1.json) contains seven proposed observations with IDs, exact scopes, original DOM locators and mechanically checked fragments totaling **23 quoted words**. Confidence/classification remain null, review PENDING_HUMAN; annotations/extractions empty, metrics null. Original candidate manifest stays unchanged as frozen pre-acquisition history.

| Candidate | Proposed normalized value / scope | Proposed status |
| --- | --- | --- |
| English manuscript language | en, supported=true; journal manuscript instructions | EXPLICIT |
| French manuscript language | fr, supported=true **with mandatory English title/abstract translation** | OUT_OF_SCOPE pending human applicability/representation review; never unqualified global support |
| German manuscript language | de, supported=true **with the same translation dependency** | OUT_OF_SCOPE pending human review |
| Mandatory translation | English title/abstract translation for non-English articles; structure text candidate only | OUT_OF_SCOPE: no evaluated V4 language-conditioned translation rule |
| Bibliography | BibTeX plain or plain-fr; French applicability retained for plain-fr | OUT_OF_SCOPE proposed: do not collapse alternatives or erase language scope |
| External class package | Platform-owned www-host ZIP link; manuscript preparation after acceptance | OUT_OF_SCOPE: cross-origin V1 template boundary, not downloaded |
| Required abstract | Abstract presence, no numeric words maximum | OUT_OF_SCOPE: no evaluated V4 presence rule; never invent abstract_words |

These conservative dispositions are **proposals**, not final human gold. Distinguish canonical relational possibilities (boolean languages and reference_style text) from the frozen evaluated V4 mapping's ability to preserve conditional/alternative meaning. A pure French grammar miss does not make otherwise representable English support OOS. The human must decide whether conditional languages/alternative styles have a faithful permitted gold representation. No labels are chosen from extractor outputs; none were generated.

Separately identified explicit but unscored/unrepresented formatting facts: LaTeX/documentclass after acceptance, fixed layout, title/author commands, abstract placement, graphics relative dimensions/permitted formats, theorem conventions, source encoding. The bibliography alternatives require human interpretation of exact applicability; generic submission navigation points to author instructions, not a demonstrated portal. No numeric word limit, fee/APC or accepted article-type list appears in the inspected body; this is an operator coverage proposal, not yet human attestation. No facts are inferred from external/home/linked pages. Full source-only editorial container inspection confirms useful text exists statically, including non-paragraph formula/list blocks; no browser/JS rendering limitation affects cited evidence.

Current corpus remains inadequate for the two-journal / 4–6-positive target: only Confluentes acquired, one EXPLICIT candidate before human review. JSS blockage is an acquisition boundary, not a fact FN or permission to replace its URL. Any replacement URL/journal needs explicit approval. Preserve bad/limited outcomes without retuning V4.

Integrity checks: frozen V4 hashes unchanged; no new assertions/reviews/journal/catalog revisions (all zero in validation database); source_records now six after one new snapshot; exact locators/fragments/href mechanically validated without extractor. git diff --check and local Markdown-link checks pass. No new implementation/dependency change or full suite needed for this acquisition/documentation-only stage. No benchmark, TP/FP/FN calculation, commit, push or branch. **STOP for human gold validation. Phase 4 NOT_READY.**


## Confluentes independent V4 holdout — human gold frozen and benchmark complete

The human operator validates the stored snapshot `01a134ba-9f65-72c9-b6fe-c9e68cf575e6` with exactly **one positive** (English manuscript language, supported=true) and **six explicit OOS** observations. French/German translation dependencies, mandatory translation, conditional alternative reference styles, external class package and abstract presence remain OOS. Clear source text with insufficient V4 representation is not AMBIGUOUS. No unqualified French/German support, selected unique style, numeric word limit or canonical template is invented. JSS remains ACQUISITION_BLOCKED with no annotation.

Gold was saved before extraction with operator reference `florent-3ec3-holdout-2026-10-13` and SHA-256 `e9ba5e96f637b40b62e414bf5e40d71fa6e4bbe7367a74f423f4276420373d71`. Original candidate proposed dispositions remain historical; separate human resolutions are recorded. Frozen gold/policy/digest were verified identical after the actual run. No extractor change before or after observing this independent result.

| Metric | Independent Confluentes V4 result |
| --- | --- |
| Proposals | 0 |
| TP | 0 |
| FP | 0 |
| FN | 1 (English manuscript language) |
| OOS | 6 |
| CA | 0 (no ambiguous gold) |
| AB | 1 (JSS; no fact FN) |
| Extraction failures | 0 |
| Precision | Undefined |
| Recall | 0% |
| F1 | 0 |

The existing extractor successfully completed its dry-run but returned no proposals. The unchanged deterministic comparator matches this empty output against the sole human-positive language fact, producing one FN. The six OOS observations do not become FN or CA. The result is retained without correction/retuning; empty predictions do not establish strong precision or useful recall.

Actual stored-snapshot extraction was confined to Confluentes, with snapshot hash/fingerprint validation, Http::preventStrayRequests, isolated PostgreSQL READ ONLY transaction and rollback. All 37 public-table counts remain identical. All frozen V4 source hashes remain unchanged; no assertions/reviews/journal/catalog revisions or network requests. The [gold, captured extraction and metrics](validation/editorial-v4-independent-holdout-v1.json) reproduce with the unchanged comparator. Fifteen comparator tests pass; no command/test failure encountered during this benchmark. git diff --check and local Markdown path verification pass. No implementation or dependency change; no branch/commit/push.

**Phase 4 NOT_READY.** One evaluated journal and one positive gold fact are insufficient even if extraction had succeeded; actual recall is 0%. Additional independent journals with several genuinely representable positives require separately approved exact URLs, 3E-A acquisition and human gold validation. This task stops after reporting the preserved result, without new acquisition or rule change.


## Phase 3E-C4 — Context robustness and generalization

### Historical preservation and methodology

Preserve V2 baseline 0/0/6, V3 development 6/0/0, JMLR V3 independent 0/0/2, V4 JMLR remediation 0/0/2 and Confluentes V4 independent 0/0/1 with six OOS. Prior report sections are an unchanged prefix and all pre-existing validation artifacts/comparator/tests retain their bytes. From C4 onward PLOS, JOSS, JMLR, Confluentes, eLife and PeerJ are **DEVELOPMENT DATA**. No V5 result on these journals is independent proof of generalization.

### Context root cause — measured before edits

Stored SourceRecords were loaded in the isolated validation database. Original DOM nodes, whitespace-normalized complete evidence, contexts and rule results were traced without network or writes. Full target-node bounded text is retained for private operator inspection and remains available in the original snapshot; no full page HTML or editorial paragraph is copied into Git. [Diagnostic metadata](validation/editorial-v5-context-diagnostics-v1.json) retains byte counts, content hash, locators, context labels and rejection paths.

| Fact | Snapshot / node | Actual V4 trace |
| --- | --- | --- |
| Confluentes English | 01a134ba-9f65-72c9-b6fe-c9e68cf575e6; /html/body/main/div/div/div[2]/div/div/p[2] | Complete 377-byte normalized paragraph preserved as EvidenceUnit, empty local label, one heading Instructions LaTeX, no ambiguity. LanguageRule exits because unknown heading rejects. Independently its English-only anchored grammar rejects the French list plus explicit non-English translation dependency. No proposal exists to reach later snippet/metadata suppression. |
| JMLR abstract | 01a13487-40bf-7275-936b-ddd3cf3e9ed3; /html/body/div[2]/ul[4]/li[4] | Complete 42-byte unit, no local heading. Three inherited headings (journal author-information title, Overview, Submission procedure), plus p[17] immediately before submission heading detected as example qualifier. That 272-byte context exceeds 128 and marks ambiguity. V4 grammar matches independently, but eligibility exits before grammar; no proposal. |
| JMLR submission URL | Same snapshot; /html/body/div[2]/p[18]/a | Complete 39-byte action-anchor unit with exact stored href. V4 label vocabulary recognizes it. Same heading/oversize-example context rejects before label/URI proposal creation. No target fetching or canonical write. |

The shared failure is treating unknown structural labels like unsafe qualifications and carrying a closed example before a heading into the new section. Confluentes also requires recognizing a source-language construction; context repair alone would not recover its fact. The original paragraph contains a clear translation condition for non-English articles, which must not be dropped.

### Context model change

RuleContext now accepts an explicit selfContained flag only from three rule families: complete language eligibility grammar, subject + numeric maximum grammar, and explicit submission action/system labels. Neutral unknown **headings** may coexist with these autonomous facts but never supply a missing subject/operator/value/scope. Actual grammar must still match the full bounded evidence; unknown fragments cannot become facts.

Unsafe contexts remain distinct: qualifiers/conditional or hypothetical statements, examples, optional recommendations, waivers/promotions, incompatible known article scopes, unsupported systematic-review/special/domain scopes, unknown captions/table headers, explicit minimum/exact scope and any retained overflow/ambiguity. Known article scope still applies to numeric requirements and still blocks unsafe generalization for language/link facts. Fees/styles/types/templates do not gain an unknown-context escape hatch.

A complete illustrative paragraph immediately before a heading, ending as a closed statement rather than a colon-introduction, belongs to the prior block unless it explicitly points to the next/following section or carries condition/exception vocabulary. This removes the JMLR p[17] contamination without increasing context limits. Direct examples adjacent to evidence, example headings, next-section examples, invited-only exceptions and genuine qualifiers retain strict vetoes. No arbitrary backward scan, paragraph-count window or large-context truncation.

Link units remain separate from editorial prose: a strong action/system label and safe metadata URL are autonomous under neutral wrappers, body or navigation. Its own conditional/example leaf paragraph/div/list prose, hidden/deleted/disabled ancestors, unsafe href and generic instructional labels still reject. Large ancestor containers are not joined into spurious local qualifiers. No HTTP follows an extracted link.

### V5 changes and versions

Defaults: **editorial-v5 / editorial-dom-v5**. PrepareDocument retains all resource ceilings (raw 256 KiB; 4096 DOM elements; 512 text units / 128 KiB normalized text; 128 link units / 16 KiB text; 1024-byte unit/regex input; href 2048; locator 512; eight retained contexts / 128 bytes each; ancestry 16; proposals 128). Existing context overflow still fails closed.

LanguageRule adds a publisher-neutral French eligibility-list grammar naming accepted manuscript languages and explicitly conditioning English-title/abstract translation on non-English manuscripts. Only listed, unconditioned English is emitted. French/German are not materialized. The entire paragraph and dependency remain in original wording. Unknown language tokens, conditional journal subjects, changed condition target, additional eligibility prose and unknown trailing notes abstain. Only unrelated technical-subject package/multilingual-formatting notes can follow this closed statement. No automatic translation or new language-condition model.

The forward `2026_10_13_140000_allow_editorial_v5_metadata.php` migration extends only the existing provenance document-version whitelist/context requirement. Original migrations and V1–V4 metadata remain compatible/unchanged. Populated V5 rollback refuses before destructive DDL; empty rollback/reapply and actual retained V2/V3/V4 assertions alongside V5 are tested. No architecture, canonical business model, ontology, review/publication rule, provider/client or dependency change.

### Synthetic regressions

C4 introduces 32 context cases and ten French eligibility cases. Coverage includes autonomous language under neutral unknown headings; incomplete fragments; examples/hypotheticals/optional/conditional statements; abstract under unknown headings/unrelated long text; complete prior examples versus direct/introducing/next-section examples; body/nav/utility links; misleading guidelines; conditional/example/disabled link wrappers; unsupported/contradictory scope; no generic fee→APC inference; no inverted negative article support; no flattened reference alternatives. French tests use synthetic journal names/technical notes, reject conditions targeting English or extra restrictions, and verify only one en membership, full evidence, pending review and V5 provenance. Historical assertions remain unchanged; known sealed publication, idempotency, atomic rollback and dry-run tests still pass.

Two earlier V4 conservative test cases now explicitly expect recovery under V5 (neutral unknown headings and a complete prior example before a new heading). They are moved into the V5 positive/negative context matrix, not deleted to hide failures. Counterexamples retain direct/introduction/conditional/example abstention.

### Development corpus replay — actual results

| Journal | TP | FP | FN | OOS |
| --- | --- | --- | --- | --- |
| PLOS ONE | 4 | 0 | 0 | 2 |
| JOSS | 2 | 0 | 0 | 0 |
| JMLR | 2 | 0 | 0 | 3 |
| Confluentes Mathematici | 1 | 0 | 0 | 6 |
| eLife | 0 | 0 | 0 | 7 |
| PeerJ | 0 | 0 | 0 | 0 |
| Total | 9 | 0 | 0 | 18 |

Precision/recall 100%, F1=1 on nine **development** positives only. CA=1; three mixed-output ambiguous observations remain not evaluated for CA under unchanged conservative comparator policy. The three acquired-failure cases and three policy holds are retained historical acquisition outcomes, not expected-fact FN. eLife/PeerJ have no positive gold and must not be described as 100% recall journals.

[Captured V5 development replay](validation/editorial-v5-development-v1.json) is the unmodified union of three source human annotation sets, with their original policies/digests retained under source_gold. Each original annotation object is identical, including JMLR initial-submission stage and conditional/OOS dispositions. Aggregate evaluation metadata identifies the development split without altering source gold. Same six SourceRecords/content hashes/fingerprints, Http::preventStrayRequests, PostgreSQL READ ONLY then rollback, unchanged counts for all 37 public tables. No assertion/review/revision is written. Comparator source/tests remain unchanged; metrics are deterministic, not LLM labels.

### False-positive audit

Full original DOM evidence was inspected privately for 22 annotated nonpositive observations; summary records source IDs/locators/dispositions without source-body redistribution. Actual output audit confirms:

- PLOS APC assistance/waivers: no fee/APC-absence proposal; four expected positives only.
- PLOS article-type examples: no article_type proposal.
- eLife domain-conditioned research/review types: no type proposal; seven OOS preserved.
- JMLR generic no-publication-fees: no APC or fee proposal.
- JMLR rejected review_article: no supported=true proposal.
- Confluentes French/German: neither membership emitted, only en.
- Confluentes plain/plain-fr: no arbitrarily selected or independently duplicated style.

No serious FP observed in this small development corpus; this is not a proof that arbitrary unknown-heading semantics are safe. Classifier/vocabulary/grammar coverage remains deliberately bounded and new domains require independent gold evaluation. The source-language grammar is narrow rather than a general multilingual NLP system.

### Tests and complete encountered failures

| Check | Final result |
| --- | --- |
| Focused V5 contexts/French/history | 44 tests / 291 assertions |
| EditorialExtraction | 250 / 1851 |
| Catalog/publication + ingestion | 63 / 471 |
| OfficialSource | 118 / 463 |
| OpenAlex | 64 / 376 |
| Crossref | 49 / 267 |
| DOAJ | 107 / 566 |
| Full Laravel PostgreSQL | 694 / 4303; 54.164 seconds |
| Comparator unittest | 15 passed |
| Pint --test | Passed after scoped test-file formatting |
| Composer validate | Valid; network disabled |
| Composer audit | Passed; zero advisories/abandoned packages |
| OpenAPI / generated contracts | Valid / reproducible and current |
| git diff --check / local Markdown targets | Passed |
| Historical artifacts, source gold, prior report prefix | Unchanged |

Failures and their actual roots:

1. Focused run had three new test errors: unqualified JournalLanguage model name resolved into Tests\Feature. Corrected only the test namespace. One negative French subject test also failed: conditional-subject vocabulary was not vetoed by the new grammar. Added conservative French condition/exception vocabulary rejection; no model/security bypass. Reran the affected French test method first (10 / 65), then all focused tests.
2. Complete extraction found the existing plain-text Systematic Reviews scope test emitting a numeric requirement. The new neutral-heading route had incorrectly treated an unsupported article-type scope as neutral. Added unsupported systematic-review/letter/correspondence vocabulary to the unsafe classification. Reran that exact dataset first (1 / 5), then focused and complete extraction (250 / 1851). No expectation weakened or test skipped.
3. Pint flagged import/attribute/unary spacing in the edited test file. Applied formatter to that file only, then Pint --test passed. Full dependent and PostgreSQL suites ran after final formatting/code edits.
4. Operator candidate discovery later observed Acta Acustica 403 in the public web reader. It is not an application/test failure; candidate excluded without retry/bypass. No extraction or transport code changed in response.

No SQLite, test disable/delete, installation, force flags, secret/cookie/header material, HTML/template committed, branch, commit, push or history rewrite. Application SSRF/DNS/TLS/body/cadence logic is untouched.

### New independent V5 validation candidates — rules frozen before discovery

All V5 rule hashes are frozen in the [new candidate manifest](validation/editorial-v5-holdout-candidates-v1.json). Discovery is bounded public operator research after implementation/tests, including indexed searches and web-reader HTML inspection; not a Fikarohana crawler, source approval or acquisition. No rule edits follow candidate research. Search/browser contents are not gold or snapshots. No assets/templates/submission targets are fetched by application code.

| Journal | Publisher/platform | Exact URL | Role / potential diversity |
| --- | --- | --- | --- |
| Biology Open | The Company of Biologists / Silverchair | https://journals.biologists.com/bio/pages/manuscript-prep | Manuscript instructions: submission action, scoped abstract maximum, complex stage/context layout |
| Biology Open | Same | https://journals.biologists.com/bio/pages/article-types | Journal-domain link from official instructions; type evidence and scope distinctions |
| Systematic Biology | Society of Systematic Biologists / Oxford University Press | https://academic.oup.com/sysbio/pages/Manuscript_Preparation_Guidelines | Language and submission-system evidence; custom style/POV/software exceptions |
| Discrete Analysis | Journal editorial team / Scholastica overlay association to attest | https://discreteanalysisjournal.com/for-authors | Third journal/platform; arXiv overlay and submission-action evidence |

Primary domain evidence: [Biology Open manuscript instructions](https://journals.biologists.com/bio/pages/manuscript-prep), [Oxford journal instructions](https://academic.oup.com/sysbio/pages/Manuscript_Preparation_Guidelines), [Discrete Analysis author page](https://discreteanalysisjournal.com/for-authors). Biology Open and Oxford web reader expose text/html instructions; Discrete Analysis has indexed text but the browser reader returned no parsed lines, so usable static raw content remains uncertain. The Biology Open article-types candidate is linked on the official page but has not been separately opened/acquired. Applicable robots/access/retention, journal ownership and unchanged 3E-A guards must all be reviewed before approval.

Target: **three journals**, six potential canonically representable facts, across language/abstract maximum/submission URL/article membership. Provisional semantic opportunities: BiO instruction submission+abstract (2), BiO type membership (1), Systematic Biology English+submission (2), Discrete Analysis submission (1). These are not approved labels, guaranteed grammar matches or a proven six-fact corpus. Human scope/static-access/rights review can reduce this count; record inadequacy instead of manufacturing facts. Do not choose only easy pages or reuse development/blocked journals. Acta discovery access refusal is recorded as rejected, not circumvented.

**STOP before Fikarohana acquisition.** Human operator must approve exact URLs with reference, applicability/robots/access/internal-retention basis. Then existing 3E-A acquisition, candidate evidence, human gold validation, frozen V5 benchmark and complete metrics. Any bad holdout result must be recorded before future retuning. No numeric pass threshold invented. **Phase 4 NOT_READY.**

### C4 files changed

```text
apps/api/app/Services/EditorialExtraction/LanguageRule.php
apps/api/app/Services/EditorialExtraction/LinkRule.php
apps/api/app/Services/EditorialExtraction/PrepareDocument.php
apps/api/app/Services/EditorialExtraction/RuleContext.php
apps/api/app/Services/EditorialExtraction/RuleRegistry.php
apps/api/app/Services/EditorialExtraction/WordLimitRule.php
apps/api/database/migrations/2026_10_13_140000_allow_editorial_v5_metadata.php
apps/api/tests/Feature/EditorialExtractionTest.php
apps/api/tests/Feature/JournalCatalogTest.php
docs/development/EDITORIAL-EXTRACTION-V1.md
docs/development/EDITORIAL-REAL-WORLD-VALIDATION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/development/validation/editorial-v5-context-diagnostics-v1.json
docs/development/validation/editorial-v5-development-v1.json
docs/development/validation/editorial-v5-holdout-candidates-v1.json
```

Earlier uncommitted C/C2/C3 work remains intact; list isolates C4 edits. Ignored PHPUnit cache updated through test runs only.


## V5 independent corpus — four-page conditional authorization, acquisition only

Operator reference `florent-3ec4-v5-holdout-2026-10-13` conditionally authorizes only the four URLs already frozen in the V5 candidate manifest. The operator expressly requires whole-corpus human gold before any extraction/metrics; no page-by-page benchmark/remediation cycle. The original candidate manifest and V5 source hashes remain unchanged. Acquisition outcomes are recorded separately in the [independent V5 packet](validation/editorial-v5-independent-holdout-v1.json); this is not a completed evaluation dataset.

### Ownership and policy preflight

Biology Open ownership is corroborated by [the publisher's official OA journal listing](https://www.biologists.com/open-access/) and journal masthead. The two `/bio/pages/...` paths are separately reviewed/approved, not blanket domain authorization. Systematic Biology is explicitly identified on its Oxford Academic author page as the Society of Systematic Biologists journal; an official policy nevertheless restricts this acquisition. Discrete Analysis owns the journal domain, corroborated by its [editorial announcement](https://discreteanalysisjournal.com/post/40-welcome-to-discrete-analysis) and author-page identity; Scholastica is only the technical platform and none of its URLs is authorized/fetched. Ownership search results are not source snapshots or gold.

Pinned HTTPS robots checks for all three domains succeed after existing PublicDns all-A/AAAA validation; no prohibited address is selected/dropped. Biology Open robots leaves both exact public author paths permitted and explicitly allows named user-facing AI agents; no applicable longer crawl delay. Oxford robots leaves the journal path permitted but [Oxford Academic special website terms](https://academic.oup.com/pages/legal-notice?login=true) expressly prohibit automated tools accessing/obtaining/copying Content; no specific exception for these instructions has been established. Therefore **DO_NOT_APPROVE**, no register/approve/editorial request, no requested/final redirect substitution. Discrete robots excludes structured export suffixes, not `/for-authors`. Public unauthenticated requests remain subject to actual 3E-A access/redirect/body checks. Operator robots inspection is finite policy research outside the application, not a new generic fetcher or bypass.

### Actual controlled outcomes

| Requested exact URL | Outcome | SourceRecord / final URL |
| --- | --- | --- |
| https://journals.biologists.com/bio/pages/manuscript-prep | ACQUISITION_BLOCKED; actual 3E-A HTTP 403 / official_forbidden | None; no response body retained or retry |
| https://journals.biologists.com/bio/pages/article-types | ACQUISITION_BLOCKED; independent 3E-A HTTP 403 / official_forbidden | None; no response body retained or retry |
| https://academic.oup.com/sysbio/pages/Manuscript_Preparation_Guidelines | DO_NOT_APPROVE / NOT_ATTEMPTED_POLICY_HOLD | None; no editorial request or manual substitution |
| https://discreteanalysisjournal.com/for-authors | ACQUIRED; HTTP 200, text/html, 41473 bytes | 01a13510-0af0-71bc-98e0-3902ea22b837; final URL identical to requested |

The existing OfficialSources register → approve → fetch workflow was used only for the three preflight-eligible exact pages, sequentially and with unchanged 30-second delays/timeouts/all-address DNS/TLS/pinned transport/redirect/size security. Original operator approval references and failed check outcomes remain auditable; no automatic retry, alternative UA, proxy, browser-content substitution or deactivation/history rewrite. Biology Open's approvals preceded the observed access failure; a persisted approval does not authorize bypass or imply a successful public snapshot. No new app code or security relaxation is introduced to make these live failures pass.

Discrete source ID `01a1350f-90b4-734b-a7ab-3090cac16ed8`; snapshot fingerprint `28cbea9453c40a837ecbf3b65359117ca80de1c15a9b8c4407cfd729de3942b8`. Its full raw HTML stays in restricted `fikarohana_3ec_validation`, never copied into Git. Only inactive validation-only journals were created, no fabricated canonical identifiers. No URL discovered from snapshots is approved. No templates/assets/scripts/iframes/submission destinations/third-party platform requested.

### Candidate annotation packet — no usable editorial evidence

Actual original-DOM inspection of the acquired Discrete snapshot yields **zero p/h1/h2/h3/h4/li/a/td evidence nodes**, body text 27 bytes of whitespace and **zero non-whitespace bytes**. Body children are empty `app-root` plus three script elements; four script elements exist in the document overall. This is an application shell without usable static editorial content, despite discoverable search-index text. No script, browser rendering, secondary data/API request or fallback capture is executed. Acquired HTML is not malformed; static evaluation is limited by the retained rendering state.

Accordingly the requested annotation table has **no candidate rows**: no source-backed fact type/value/scope/locator/excerpt can be supplied from usable editorial DOM. Do not manufacture EXPLICIT/AMBIGUOUS/OOS labels from external search snippets or script data, and do not interpret inability to acquire/render as missing journal facts. Separately:

- Explicit but unrepresentable facts: none established from retained static editorial evidence.
- Conditions/qualifiers: not assessable from this shell; no conditional rule inferred.
- Ambiguous factual observations: none proposed; rendering failure is not an ambiguous journal claim.
- Potential missing V5 drafts: not assessable; no gold coverage is attested.
- Acquisition limitations: two live 403 refusals, one applicable policy restriction.
- Rendering limitation: sole acquired snapshot has no static editorial body.

Packet annotations/extractions are empty and metrics null. No PrepareDocument/LanguageRule/RuleRegistry/ExtractEditorialSnapshot or comparator invocation occurred for this independent corpus. **No TP/FP/FN calculated.** Whole-corpus human review remains pending; there is currently no usable journal/positive evidence for the three-journal/six-fact target. Do not run an empty benchmark or silently replace any page. New acquisition permissions/corpus changes must be explicit human decisions.

### Integrity and stop

All fourteen frozen V5 source hashes and original manifest unchanged. Source records total seven after one new snapshot; assertions/reviews/journal/catalog revisions remain zero. Manual DOM locators/node counts and snapshot hash were checked without rule execution; no public raw HTML redistributed. Existing deterministic implementation suites passed during C4 and app code is unchanged during this acquisition-only step; no implementation tests rerun to mask live 403s. Documentation/JSON integrity and local Markdown targets plus git diff --check pass. Actual live failures are retained as unavailable, not logic fixes.

Only the independent packet, this appended report and TODO change. No dependency installation, branch, commit or push. **STOP after acquisition/candidate-evidence presentation. V5 remains frozen and Phase 4 NOT_READY.**

## Phase 3E-C5 — independent holdout acquisition preflight V1

Date: 2026-10-13, Indian/Antananarivo. This task addresses acquisition and corpus viability. It introduces no extractor remediation. `editorial-v5` and `editorial-dom-v5` remain frozen: all fourteen SHA-256 digests match the original candidate manifest. Development success remains **9/9**: PLOS ONE 4, JOSS 2, JMLR 2, Confluentes 1 TP; zero development FP/FN. These journals have influenced development and cannot establish independent V5 generalization.

The first independent V5 attempt produced **no usable gold**: two separate Biology Open HTTP 403 refusals, a Systematic Biology automated-access policy hold, and a Discrete Analysis snapshot with no static editorial body. No independent V5 benchmark was possible or executed. This is an **acquisition/corpus problem**, not an extractor benchmark result. Its original [acquisition packet](validation/editorial-v5-independent-holdout-v1.json), empty annotations and null metrics are preserved. No failed request was retried for C5.

### Discovery method and independence

Bounded public web research only: **24 search queries and 48 explicit page operations** (selected reader opens, navigation clicks and in-page finds), stopping within a 24-query / 50-operation budget. Searches targeted university/OJS guidance, Centre Mersenne, journal-owned lightweight pages and nonprofit publishers. Search results identified possibilities; selected official homepages, About pages and actual author pages established journal/domain relationships. No recursively followed links, PDF downloads, script execution, asset/template downloads, Fikarohana registry calls or database operations.

The [C5 metadata manifest](validation/editorial-v5-holdout-preflight-v1.json) contains eleven candidate pages from eleven journals, with homepages, exact URLs, domains, page roles, potential categories, short descriptive notes, officiality evidence, rendering observations, warnings and acquisition-only suitability. No gold labels, normalized fact values, predictions or candidate source records are created. Exclusions include all thirteen requested journals **and Scientific Reports / Sensors**, found in the earlier local development corpus. A bounded local search found no prior candidate-journal identity in the existing docs, application code, fixtures, AI service or contracts. This records local independence evidence; it is not an external guarantee about undocumented history.

All inspected author pages exposed editorial text in the public reader. This supports an **apparent** static/server-rendered assessment only. Reader content may be cached or transformed and does not establish raw HTML bytes, absence of JS dependence, permitted automated acquisition, DNS safety, TLS, redirect compliance or successful 3E-A acquisition. Search-text flags distinguish an observed editorial search result from a page whose text was established only by direct reader inspection. No robots/access permission is inferred from open access or platform membership; current robots and automated-access terms remain **NOT_REVIEWED for every candidate**.

### Candidate pool and acquisition suitability

Ranks use official-source confidence, apparent static accessibility, platform diversity and editorial richness. They do not use whether V5 could parse any observed wording. Each source link below is an exact editorial candidate, not an approved source. Full metadata and official homepage URLs are in the manifest.

| Candidate / exact author page | Publisher or institution / officiality | Apparent structure / role | Suitability and rationale |
| --- | --- | --- | --- |
| [Medicine Anthropology Theory](https://www.medanthrotheory.org/mat/about/submissions) | University of Edinburgh / Edinburgh Diamond identified in journal footer; journal-domain page and homepage linked | OJS; public guidelines/submissions | **HIGH** — broad preparation and citation sections, multiple manuscript scopes; university alias/own-domain distinction recorded |
| [SIGMA](https://sigma-journal.com/about.html) | Journal homepage links How to Submit an Article to this combined page; founding institution and editorial team named | Lightweight static HTML; combined author instructions/policies | **HIGH** — compact page, overlay and email workflow, direct official navigation |
| [Volcanica](https://www.jvolcanica.org/ojs/index.php/volcanica/about/submissions) | Self-published journal; official About page names Strasbourg institutional backing; homepage links instructions | OJS; checklist and guidelines | **HIGH** — earth-science formats, proposal conditions and preparation stages; no observed reader challenge |
| [Beilstein Journal of Organic Chemistry](https://www.beilstein-journals.org/bjoc/authorInstructions) | Publisher journal page names Beilstein-Institut; same publisher masthead | Custom publisher HTML with JS UI; author instructions | **MEDIUM** — rich chemistry guidance and non-OJS structure; generic JS/cookie notice and longer page increase acquisition uncertainty |
| [Algebraic Combinatorics](https://alco.centre-mersenne.org/page/instructions-for-authors/) | Homepage states editorial-board ownership and links exact instructions; policy names Centre Mersenne service | Centre Mersenne; author instructions | **HIGH** — short linked official page and staged production instructions; narrow preferred-category coverage is disclosed |
| [Journal of Artificial Intelligence Research](https://www.jair.org/index.php/jair/about/submissions) | Official About page identifies AI Access Foundation; same-domain journal navigation | OJS; submission requirements/guidelines | **HIGH** — moderate page, foundation publisher, AI manuscript/track diversity; www/bare-origin links require care |
| [Quantum](https://quantum-journal.org/instructions/authors/) | Journal-domain About and Impressum identify the community publisher; journal-branded guidelines | Apparently server-rendered WordPress-style site; author/workflow instructions | **HIGH** — non-OJS structure and overlay workflow; external Scholastica submission does not authorize that platform |
| [Journal of Pollination Ecology](https://www.pollinationecology.org/index.php/jpe/about/submissions) | Official homepage/About and same journal navigation identify independent nonprofit journal | OJS; checklist/guidelines | **HIGH** — compact ecological guidance with diverse formats and qualified fee-policy wording; separate full formatting page excluded |
| [European Journal of Taxonomy](https://europeanjournaloftaxonomy.eu/index.php/ejt/about/submissions) | Journal homepage navigation and guideline VISION identify natural-history consortium | OJS; extensive author guidelines | **MEDIUM / reserve** — rich taxonomic structure, but 528 reader lines and unknown raw size; OJS already well represented |
| [Edinburgh Student Journal of Science](https://journals.ed.ac.uk/esjs/about/submissions) | University journal directory and journal-specific homepage/navigation | University OJS; writing and eligibility | **MEDIUM / reserve** — readable guidance; restricted student/supervisor scope and Edinburgh publisher duplication |
| [Journal of Eye Movement Research — former Bern site](https://bop.unibe.ch/JEMR/about/submissions) | Former official university homepage links guidance and announces transfer to MDPI | Archived OJS; legacy guidelines | **LOW / not recommended** — readable historical source, closed submissions and publisher transfer; current applicability unproven |

The [former Bern homepage](https://bop.unibe.ch/JEMR) announces a 2025-01-01 transfer; the separate legacy own-domain homepage now points to MDPI. No MDPI page was acquired/opened as a replacement. The [BJOC instructions](https://www.beilstein-journals.org/bjoc/authorInstructions) remain readable in the reader despite a JavaScript/cookie notice; this warning is retained, not dismissed as a successful raw-body check. Login/register links on OJS pages concern actual manuscript submission, while the inspected guidance text was publicly visible.

### Recommended human approval batch — exactly eight URLs

Proposed order interleaves structures and publishers. **Eight URLs, eight journals, eight domains, one URL per journal.** This recommendation grants no approval.

1. https://www.medanthrotheory.org/mat/about/submissions
2. https://sigma-journal.com/about.html
3. https://www.beilstein-journals.org/bjoc/authorInstructions
4. https://www.jvolcanica.org/ojs/index.php/volcanica/about/submissions
5. https://quantum-journal.org/instructions/authors/
6. https://alco.centre-mersenne.org/page/instructions-for-authors/
7. https://www.jair.org/index.php/jair/about/submissions
8. https://www.pollinationecology.org/index.php/jpe/about/submissions

Four pages are OJS across separate editorial organizations/instances; others use Centre Mersenne, lightweight static HTML, a WordPress-style journal site and custom publisher HTML. Institutional, community, independent foundation and journal-owned publishers are represented. No society ownership is asserted merely from community ties. Half the batch still shares OJS dependence. ALCO shares Centre Mersenne with excluded Confluentes, which is disclosed rather than treating platform independence as journal independence.

### Potential category coverage, not annotations

The proposed pages offer opportunities to review manuscript language, abstract word limits, article categories, explicit publication-fee absence, named reference guidance and submission destinations. Targets remain **6–10 human-reviewable representable positives across at least three categories**, not measured facts or guaranteed gold. The manifest maps categories to candidate pages without normalized values, locators, excerpts or labels.

Qualified fee-policy material may also be present in Quantum/JPE. Voluntary-charge wording does **not** establish a mandatory APC or a usable mandatory fee amount. Linked payment pages are not part of this batch. SIGMA email/arXiv instructions are not automatically a journal submission URL. ALCO bibliography/template instructions are not automatically a named reference style. Conditional manuscript categories and submission/acceptance stages must later retain their actual scopes. No positive tally or extraction prediction is made from discovery.

### Human approval and later acquisition stop rule

**STOP before 3E-A registration, approval or fetch.** The previous `florent-3ec4-v5-holdout-2026-10-13` reference authorizes only its old four pages and does not extend to C5. Human operator approval is required for the eight exact URLs above, with journal/publisher applicability, current robots/access terms and restricted internal-retention basis. Reserves, ownership references, aliases, scripts, templates, submission destinations, fees pages and any discovered URL receive no implicit approval.

Later, only after explicit approval, apply unchanged 3E-A to eligible URLs in the proposed order until **(A) at least three distinct journals have usable static editorial snapshots AND at least six candidate positive facts plausibly exist for human review, OR (B) every approved URL is exhausted**. Record all attempted outcomes. Do not discover/crawl automatic replacements or fetch secondary resources. Stop each blocked page with `ACQUISITION_BLOCKED` or `DO_NOT_APPROVE` according to cause; no bypass or automatic retry. Cross-origin redirections, including www/bare-host changes, stay prohibited by the existing workflow; record compliant requested/final URLs without manual substitution.

HTTPS, every resolved IPv4/IPv6 answer, private/link-local rejection, TLS validation, SSRF/IP pinning, approved-origin redirect restrictions, 256 KiB bound, applicable robots/access policy, cadence and timeouts stay unchanged. Raw body size is unknown for every candidate; 408/528 reader lines are only warning signals, never byte measurements. Full HTML, if later acquired, belongs only in restricted validation storage and never in Git.

After acquisition stops, prepare the human gold candidate packet for the **complete acquired corpus**. Human validation must freeze all labels before a single unchanged V5 whole-corpus benchmark. No page -> benchmark -> correction cycle. C5 discovery itself executes no preparer, extractor, rule registry or comparator and assigns no TP/FP/FN/CA.

### C5 verification and changed files

Metadata/documentation checks cover JSON parsing, candidate/batch counts, exact-URL membership/uniqueness, one page per journal, exclusions, evidence URLs, no annotation/prediction fields, whole-corpus gates, local Markdown links and whitespace. Fourteen V5 source hashes, OfficialSources code and prior validation JSON are unchanged. No implementation changes warrant Laravel/extractor test reruns; no database operations, dependency changes, branch, commit or push.

Executed results: **11 candidate pages; 8 proposed exact URLs / 8 journals / 8 domains; 14 matching V5 hashes; 35 pre-existing baseline files checked, with changes only to the two intended documents; 38 local Markdown targets, zero missing; no gold/prediction keys; JSON and `git diff --check` pass.** Existing uncommitted implementation work predates C5 and remains intact; branch remains `master`.

Only these files are created/updated for C5:

```text
docs/development/validation/editorial-v5-holdout-preflight-v1.json
docs/development/EDITORIAL-REAL-WORLD-VALIDATION-V1.md
docs/development/TODO.md
```

**Phase 4 NOT_READY. Human exact-URL approval pending. STOP after discovery/preflight presentation.**
