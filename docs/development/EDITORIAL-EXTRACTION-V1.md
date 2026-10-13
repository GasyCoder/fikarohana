# Phase 3E-B — Deterministic editorial extraction V1

Status: internal Laravel implementation; current defaults are editorial-v5/editorial-dom-v5 after Phase 3E-C4. V1–V4 sections retain historical behavior and verification; C2/C3/C4 describe subsequent changes. Stored-snapshot proposals only; no LLM, network acquisition, verification, automatic publication or public endpoint. Phase 3E-A transport and provider mappings are unchanged. Laravel remains canonical; Next.js/Expo remain API clients and FastAPI remains optional specialized compute.

## Architecture and lifecycle

Persisted approved official snapshot → inert document preparation → fixed deterministic rules → bounded evidence/locators → immutable field-level proposals and typed values → conflict comparison → explicit operator review → existing Phase 2 journal/catalog publication.

ExtractEditorialSnapshot reloads SourceRecord by persisted ID, rejecting caller-mutated bodies, aggregator records, missing snapshots, wrong journal and revoked/inactive sources. It prepares/runs rules before a short transaction. Publication/review advisory lock 170001 serializes proposal materialization; source approval/ownership is checked again under a row lock. All assertions, typed values, vocabulary additions and conflicting reviews for one snapshot commit atomically. A failed typed insertion rolls them all back; the original snapshot survives and retry is safe. No durable extraction run/table or failed-attempt history is added; failures return fixed diagnostic codes. Review/publication workflows retain their existing invariants.

## Source authority and extraction status

Official journal/publisher source authority describes provenance and applicability, not interpretation accuracy. Each proposal uses deterministic_explicit, a grammar-match status, never a numerical confidence/acceptance percentage or verified truth. Unreviewed proposals remain pending; contradictory values receive a conflicting comparison review. No rule creates a verified review, activates a journal, overwrites working identity or seals a revision.

## Supported rules and deliberately narrow grammar

RuleRegistry editorial-v2 registers seven implementations through EditorialRule (id, field, extract). Each accepts prepared bounded EvidenceUnit objects and returns zero or more typed proposal values. Rule IDs are immutable grammar identities; future grammar changes must advance the registry version. The CLI does not accept arbitrary rules, versions or regexes.

| Rule ID / implementation | Supported evidence | Mapping |
| --- | --- | --- |
| explicit_fee / FeeRule | Exact labeled APC, Article Processing Charge, Publication fee, Submission fee, Page charge, Color charge; explicit USD/EUR/GBP or euro symbol and one amount; three exact absence statements | JournalFee; generic publication fee is other, not assumed APC |
| explicit_languages / LanguageRule | Accepted manuscript/publication languages; Manuscripts must be submitted in; Articles are accepted in | Explicit English/French/German/Spanish/Portuguese map to en/fr/de/es/pt JournalLanguage |
| explicit_article_types / ArticleTypeRule | Article types / Accepted article types labeled list or immediate heading/list | Explicit mapping to research_article, review_article, short_communication, case_report, editorial |
| explicit_word_limit / WordLimitRule | Abstract, Manuscript or mapped article-type label + numeric words; maximum/max/up to; Manuscript should/must not exceed | abstract_words versus word_count, max integer; article context on assertion |
| explicit_reference_style / ReferenceStyleRule | Reference style / Citation style + Vancouver, APA, APA 7th edition, AMA, Chicago | reference_style text requirement |
| explicit_template_link / LinkRule | Exact manuscript/article template anchor, optional Download / DOCX, Word, LaTeX, PDF label | Same-origin official template link; non-fragment target required; format hint only after explicit label |
| explicit_submission_link / LinkRule | Submit your manuscript / Online submission / Submit an article | Evidenced submission_url scalar; external submission systems may be metadata |

Unknown language lists yield no language assertions. Unknown article labels remain in the original snapshot/supporting unit; known explicit labels in the same list can still map. No fuzzy vocabulary matching. Repeated equivalent labels deduplicate by normalized proposal identity while original evidence remains intact. Table/dl labels and immediately adjacent heading/content are supported; arbitrary prose interpretation, generic div layouts and site-specific schemas are not.

Maximum semantics must be explicit: maximum/max/up to/no more than, should/must not exceed, or an explicit Maximum word counts table caption/header. Bare numeric word counts yield no assertion; the previous default-to-max grammar was incorrect. Minimum/exact/ranges, compound scoped limits and reference/table inclusion are unsupported; no number is inferred from surrounding prose. Abstract and manuscript requirements remain distinct. The existing requirement_type/operator/value_number plus article_type_id suffice; extraction metadata states abstract/manuscript scope. Bare APA does not imply edition 7. Citation samples/custom publisher styles produce no invented named style.

## APC and fee semantics

Money is exact decimal text fitting numeric(14,4), never float or conversion. Supported formats include 1500, 1,500.25, 1 500.25, 1500.25 and 1500,25. Comma grouping with three digits is explicitly a thousands format; mixed 1.200,50 and unsupported ambiguous forms abstain. Only explicit supported currencies map; no currency is guessed. Optional services, conditional/waived/discounted/promotional amounts, ranges or trailing qualifying prose do not match the full-unit grammar. No current-date/exchange-rate inference.

Known positive or explicit zero amounts retain currency and official_explicit_amount basis. Submission/page/color/generic publication charges retain their own kind. Unspecified article/language/tax/waiver context is explicitly recorded as unspecified, not universal applicability or total cost.

Exact No article processing charges / There is no APC / Authors are not charged publication fees preserve official_reported_no_apc with null amount/currency and unknown scalar monetary availability. This basis/wording distinguishes evidenced absence from a missing price, explicit numeric zero, waiver and not_applicable. Qualifying headings through wrappers, immediately preceding explicit qualifier blocks and trailing conditional prose prevent absence matching. Unsupported conditions produce no assertion, even when an isolated sentence would match. It does not assert that all other fee kinds are absent. The existing known-money schema requires currency, so currency-free absence remains non-publishable as monetary truth even after review; a richer fee-presence model is deferred, matching the DOAJ boundary.

## Document preparation and resource limits

PrepareDocument editorial-dom-v2 accepts stored UTF-8 HTML/plain text up to 262,144 bytes, rejecting invalid UTF-8/NUL and unsupported MIME. It never strips invalid source bytes for retention. HTML uses DOMDocument with NONET, no DTD loading/entity expansion/XInclude/resource requests, JS or browser. Scripts, styles, noscript, templates, nav/header/footer/aside, explicitly deleted/obsolete del/s/strike content, hidden/aria-hidden=true and explicit display:none/visibility:hidden nodes are removed from the prepared view only. Original snapshot bytes/hash remain unchanged. CSS classes/external CSS and a full readability engine are unsupported; the view cannot promise browser-perfect visibility.

Paragraphs/list items, two-cell table rows, definition-list entries and anchors become evidence units. Containers with nested textual blocks are skipped rather than concatenate unrelated sections. Context includes a document-order h1–h6 stack across div wrappers, conservatively bounded by section/article/main ownership, plus table/figure captions, explicit column headers and immediately associated qualifier-shaped blocks (including a qualifier directly before an intertitle). Unknown or contradictory contexts cause abstention, rather than disappear. Context is not arbitrary neighboring prose or a readability engine. Plain text uses line numbers; robots directives produce zero facts.

Limits: at most 4,096 DOM elements, 512 accepted units, 1,024 bytes per complete text unit/supporting fragment, 128-byte heading, 512-byte locator, 2,048-byte href/resolved URL and 128 distinct proposals. Oversized units are skipped whole, never truncated to hide a qualifier. Aggregate element/unit/proposal limits fail closed. Regexes are fixed anchored patterns over bounded units, never user regexes or raw HTML. Recoverable malformed HTML can parse; strict rule matching still controls proposals.

## Locators, snippets and link provenance

DOM paths are captured before filtering so they resolve in the original snapshot DOM under the recorded preparation version. Table rows and dd paths retain their associated label in original_wording; plain units use line:N. Assertions reference the immutable source snapshot, source URL/first retrieved_at, locator, short complete supporting unit, rule/version/status and scope. Link metadata additionally retains original href and anchor; resolved URL resides in the typed fact/scalar. These are internal records, not a public copied guideline corpus.

Relative URLs resolve as pure strings against the captured final URL, using existing Guzzle URI utilities. HTML base tags cannot change this base. Only syntactically valid HTTP(S) metadata without credentials/controls maps; unsafe schemes are rejected. Templates require the snapshot origin; cross-origin template ownership cannot be established by this extractor and is deferred. DOC/DOCX and TEX supply Word/LaTeX format hints only after template evidence; ZIP/PDF/unknown formats stay other rather than invent document semantics. Submission links may identify an external system but are never followed, authenticated or independently approved for acquisition.

The minimum complete supporting unit is retained, bounded at 1 KiB; large guideline sections are never copied into assertions or CLI output. Raw snapshots retain the Phase 3E-A rights/access restrictions. Extraction confers no copyright/redistribution license. Retention/purge, permitted public excerpts and any future presentation require their own policy; never render raw HTML directly.

## Idempotency, versions and conflicts

Proposal key hashes registry version, rule ID, field and normalized value/context; display labels do not create extra equivalent claims. PostgreSQL unique (source_record_id, extraction_metadata proposal_key) and shared transactional lock prevent duplicate retries. Re-extracting the same snapshot/version reuses its assertion, typed value and reviews. Reused same-content snapshots retain the same ID and proposals; retrieval and verification dates never advance.

Changed snapshots append their own source-linked assertions. Even an unchanged fact on a new snapshot needs separate evidence because an assertion references one observation; no automatic published/canonical revision is multiplied. Equal values do not create value conflicts, but duplicate corroborating verified selections still require the existing explicit publication resolution. Changed values compete by existing field/context keys. Version changes append a new auditable proposal identity; old metadata/wording/reviews are never rewritten.

DetectJournalConflicts now compares requirement operators/values, template URLs/formats and article support, in addition to existing provider comparisons, and recognizes official_reported_no_apc alongside DOAJ absence. No precedence winner, silent currency conversion or destructive overwrite. The incoming disputed claim gets a conflicting review; the older claim/review is retained unchanged. Contradictory observations require manual resolution, including article-scoped word limits and opposing APC absence/price claims.

## Data model and review/publication

One additive PostgreSQL migration adds nullable extraction_metadata JSONB (8 KiB) to journal_assertions, with required/allowed keys, bounded evidence/locator, supported-field guard, proposal uniqueness and approved official journal/snapshot attribution. Existing Phase 2 immutability/FKs protect the addition and all typed facts. Provider assertions leave it null. No new extraction tables, fee/requirement enums, identifier changes or parallel provenance. The existing uncommitted metadata migration now accepts both preparation versions and validates optional contexts; V2 requires contexts. Existing V1 evidence remains immutable and valid.

Rollback requires a transaction and takes ACCESS EXCLUSIVE on journal_assertions before checking emptiness. Populated extraction metadata refuses rollback without history loss; empty rollback restores the previous shape. Prefer forward migration over deleting history to force rollback. The catalog rollback regression removes this dependent addition first.

Existing journals:ingestion review ASSERTION_UUID --status=... --actor=... --method=... --reason=... accepts persisted extraction assertions as well as ingestion claims; it reloads the locked claim before checking eligibility. It appends manual review, never silently edits evidence. Existing PublishJournalRevision remains the internal selected, atomic, sealed journal/catalog publisher; no new automatic publication path or extraction-specific publication command is introduced. A title and all selected reviewed values are still required, unknown monetary values remain blocked, and competing verified/conflicting evidence must be resolved.

## Operator command and dry-run

From apps/api, against an intentionally configured PostgreSQL database:

```bash
php artisan journals:sources extract --record=SNAPSHOT_UUID --dry-run
php artisan journals:sources extract --record=SNAPSHOT_UUID
php artisan journals:ingestion review ASSERTION_UUID --status=verified --actor=reviewer-001 --method=manual_official --reason='Evidence and applicability reviewed.'
```

Explicit snapshot ID is required for reproducibility; optional source/journal selections must match. No file/raw-body/URL acquisition/batch/crawl mode. Exit 0 completed, including zero proposals; exit 1 safe input/operation failure. Summaries contain IDs/version, normalized values, rule/status/locators and persisted conflict IDs/review state, without supporting snippets/page bodies. Dry-run parses/extracts/returns proposed values only, with zero writes including vocabularies, diagnostics, operational freshness and revisions; it does not simulate review/publication or persist conflict comparison.

## Zero-network and live coverage

The extraction code has no HTTP/DNS/process/LLM capability. URI resolution is pure string handling; inert DOM parsing does not acquire resources. Tests prohibit Laravel stray HTTP, native DNS service calls and Process invocation, assert no requests, and monitor the libxml external entity loader. Existing OfficialSource transport remains a separate explicitly selected fetch action; extract never invokes it.

All fixtures are small synthetic documents; no substantial real publisher content or credential. Phase 3E-A retained no real editorial HTML snapshot (the robots probe was transient and is unsuitable). **Live/real editorial extraction: NOT VERIFIED.** No new live page/provider request is made for this phase. A synthetic stored robots fixture independently proves zero proposals. Offline deterministic HTML behavior is verified; broad real guideline compatibility/accuracy is not claimed.

## Unsupported and remaining decisions

Peer-review model is unsupported because no canonical field/enum safely represents it; single-blind/double-blind/vague reviewer examples yield zero assertions. Further languages/labels, multilingual rule grammar, custom styles, complex word scopes/minimum/exact limits, ranges/taxes/waivers/discounts/optional service prices, structured metadata variants, cross-origin template ownership and site-specific parsing require separate rules/versions and evidence fixtures. Arbitrary rich prose is not understood.

Pilot review owners/applicability/freshness, licensed source retention/purge, public excerpts, broader permitted real HTML coverage, correction governance and operator scale remain production decisions. Source registration/approval remains the applicability trust boundary; publisher-wide statements still require reviewer confirmation. Existing provider/canonical unknown rules are not weakened to expand coverage.

Out of scope: LLM, embeddings/RAG/vector search, arbitrary NLP/OCR/PDF/DOCX parsing, browser/crawling/new fetches, recommendations/public journal API/frontend/projects/workspaces/billing/manuscript analysis/formatting/submission automation, Redis/FastAPI. No branch, commit, push, dependency installation or development/production migration.

## Failures encountered and corrected

- One inspection rg used a repository-root path from apps/api and reported file not found. Reran the inspection with the correct relative path; no application change.
- First extraction suite: 77 tests, 75 passed, two errors. Explicit zero used a truthy regex-capture fallback, treating string 0 as absent; changed to an explicit empty-string check, preserving monetary zero. A publication test referenced a nonexistent JournalSource relation; loaded the existing Journal by its canonical FK instead. Focused affected filter passed first (3 tests / 24 assertions, including an existing matching provider case), then extraction suite passed (77 / 665).
- Five additional revocation/locator/conditional-heading/review-eligibility/cross-source regressions passed (5 / 30), followed by the complete extraction suite. No failing test disabled/deleted/skipped or SQLite fallback.

## Historical pre-correction executed gates

PostgreSQL 17 / isolated fikarohana_test only; no development or production migration. No remaining failing/skipped tests.

| Gate | Final result |
| --- | --- |
| EditorialExtractionTest | **82 tests / 695 assertions passed** |
| OfficialSourceTest + OfficialSourceRollbackTest | **118 tests / 463 assertions passed** |
| OpenAlexProviderTest | **64 tests / 376 assertions passed** |
| CrossrefProviderTest | **49 tests / 267 assertions passed** |
| DoajCsvTest + DoajProviderTest | **107 tests / 566 assertions passed** |
| Complete Laravel | **526 tests / 3147 assertions passed**, 38.280 seconds |
| Pint --test | Passed; scoped formatting applied before final checks |
| Composer validate --no-check-publish | Valid |
| Composer audit --format=json | Passed; zero advisories / abandoned packages |
| OpenAPI validation / generated contracts check | Valid / reproducible and current |
| git diff --check | Passed |
| Local Markdown links | **161 targets, zero missing** |

Package advisory lookup was the only permitted external verification for quality gates; no live journal/provider request, new editorial page or crawling. Real editorial HTML extraction remains NOT VERIFIED. Phase 3E-A fetch code, provider mappings, public routes/contracts, clients, infrastructure and dependencies are unchanged. Real .env unchanged. HEAD remains 5da5f75 on master; no staged changes, branch, commit, history rewrite or push.

Phase verdict: **PASS** for bounded deterministic proposals with explicit unsupported types/grammars and mandatory manual review. This does not approve production pilot coverage, retained source rights or public verified facts.


## Historical pre-correction files changed

45 files; all Phase 3E-B changes remain uncommitted.

```text
README.md
apps/api/app/Console/Commands/JournalSources.php
apps/api/app/Models/JournalAssertion.php
apps/api/app/Services/EditorialExtraction/ArticleTypeRule.php
apps/api/app/Services/EditorialExtraction/EditorialRule.php
apps/api/app/Services/EditorialExtraction/EditorialVocabulary.php
apps/api/app/Services/EditorialExtraction/EvidenceUnit.php
apps/api/app/Services/EditorialExtraction/ExtractEditorialSnapshot.php
apps/api/app/Services/EditorialExtraction/FeeRule.php
apps/api/app/Services/EditorialExtraction/LanguageRule.php
apps/api/app/Services/EditorialExtraction/LinkRule.php
apps/api/app/Services/EditorialExtraction/PrepareDocument.php
apps/api/app/Services/EditorialExtraction/PreparedDocument.php
apps/api/app/Services/EditorialExtraction/ReferenceStyleRule.php
apps/api/app/Services/EditorialExtraction/RuleRegistry.php
apps/api/app/Services/EditorialExtraction/WordLimitRule.php
apps/api/app/Services/JournalIngestion/DetectJournalConflicts.php
apps/api/app/Services/JournalIngestion/ReviewIngestionAssertion.php
apps/api/database/migrations/2026_10_12_110000_add_editorial_extraction_metadata.php
apps/api/tests/Feature/EditorialExtractionTest.php
apps/api/tests/Feature/JournalCatalogTest.php
apps/api/tests/Fixtures/official-sources/extraction/README.md
apps/api/tests/Fixtures/official-sources/extraction/article-types.html
apps/api/tests/Fixtures/official-sources/extraction/conflicts.html
apps/api/tests/Fixtures/official-sources/extraction/fees.html
apps/api/tests/Fixtures/official-sources/extraction/languages.html
apps/api/tests/Fixtures/official-sources/extraction/malformed.html
apps/api/tests/Fixtures/official-sources/extraction/no-apc.html
apps/api/tests/Fixtures/official-sources/extraction/peer-review.html
apps/api/tests/Fixtures/official-sources/extraction/reference-style.html
apps/api/tests/Fixtures/official-sources/extraction/submission.html
apps/api/tests/Fixtures/official-sources/extraction/templates.html
apps/api/tests/Fixtures/official-sources/extraction/word-limits.html
docs/architecture/ARCHITECTURE.md
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/DATA-SOURCES.md
docs/data/JOURNAL-SCHEMA.md
docs/development/EDITORIAL-EXTRACTION-V1.md
docs/development/OFFICIAL-SOURCES-V1.md
docs/development/TESTING.md
docs/development/TODO.md
docs/product/ROADMAP.md
docs/security/PRIVACY.md
docs/security/SECURITY.md
```

## Final semantic correction — 2026-10-13

This section records the earlier correction; the narrow structural-context correction below supersedes its PASS and gate totals. The read-only review found four P1 semantic issues and a required fragment-link fix. No history is overwritten.

### Qualifiers and scope

EvidenceUnit carries up to eight separate context objects (kind/text/locator) and an ambiguous-context flag. Text is at most 128 bytes and the locator 512 characters; excessive structural depth (eight levels), context count or text bounds cause abstention. Section headings and explicit mapped article scope share source-linked heading entries; qualifier, caption and table_header have distinct kinds. Immediate local field labels (heading) and evidence text remain separate fields. The current document-order stack and conservative plain-text state are specified in the narrow correction below. Table captions and first all-th column headers remain retained; arbitrary neighboring prose is not concatenated.

RuleContext allows only explicit supported heading labels. Unknown/example/conditional/minimum/exact contexts, incompatible nested article types or unsupported compound section scopes produce no assertion. This intentionally reduces coverage: arbitrary prose qualifiers, complex layouts and headings outside the narrow grammar remain unverified. Article-qualified non-word facts are deferred because their current rule mappings do not represent those conditions.

WordLimitRule requires an explicit maximum, including a maximum table caption/header; minimum/exact/approximate/at-least/ranges and bare counts are ignored. Explicit Original Articles / Review Articles section ancestry supplies canonical article context for Abstract or Manuscript limits, including through div wrappers. Conflicting inherited/direct article scopes abstain. Abstract_words versus word_count preserve section and words unit; the typed assertion carries article_type_id. Same values across different article types remain distinct; changed limits compete only in the matching context. Synthetic fixtures formerly relying on a bare count now state maximum explicitly, and added negative tests require bare/minimum/exact/range counts to abstain. No test was removed or weakened to preserve the old incorrect default.

Deleted del/s/strike content is excluded only from preparation; original snapshot bytes remain unchanged. Current sibling content remains extractible. This is explicit markup handling, not CSS rendering.

### Explicit absence versus unknown

JournalAssertion.isExplicitNoApc recognizes only unknown-scalar APC with null amount/currency and the controlled doaj_reported_no_apc / official_reported_no_apc bases. hasConcreteClaim distinguishes this evidenced negative statement from a fee whose price/presence is simply unknown. No enum/monetary column changes or fake 0 USD. Zero money, positive price, waiver and absence remain distinct.

Detection compares explicit absence as a semantic negative category rather than provider-specific basis prose. Official and DOAJ absence can corroborate without a false value conflict. Decimal normalized prices agree regardless of grouping; different currencies remain competing values in the existing same-fee/context slot. Section/unit/type/operator are represented by controlled requirement kind, words semantics, article context and compared operator/value. Existing PHP/PostgreSQL selection keys and sealed memberships are unchanged.

PublishJournalRevision now checks concrete negative competitors as well as known values. JournalRevision.usableSelections mirrors the same condition in PostgreSQL, with bound controlled bases. A verified old APC plus new conflicting no-APC makes the old price currently unusable and blocks publication until explicit review resolution; the old sealed revision is not rewritten. Rejected/stale contrary evidence ceases to block. Mere unknown or waiver text does not block a verified price. Reviewed currency-free absence still cannot itself be selected as known monetary truth; richer fee-presence publication remains deferred. The reverse verified-absence/new-price conflict is also retained; neither side automatically wins.

### Links, metadata and versioning

Fragment-only hrefs are rejected before pure URI resolution. Normal relative templates and external submission-system metadata remain supported and unfetched. No source approval propagates to a link target.

Grammar/preparation versions advance to editorial-v2 / editorial-dom-v2. New metadata retains bounded context text and structural locators; identity includes the resulting semantic article/section scope. V1 metadata without contexts remains valid and immutable; V2 contexts are required and their array/object/key/type/text/locator bounds are checked by PostgreSQL. Encoded metadata over 6,000 bytes is skipped before persistence; the existing JSONB ceiling stays 8 KiB. Empty rollback removes the added metadata validation function and column; populated metadata refuses rollback under lock. No second extraction system or new columns/tables.

### Failures in this correction pass

- An apply_patch contained an empty hunk and was rejected before edits. Removed the malformed hunk and reapplied the same authorized patch.
- First qualifier run: nine errors before any assertion, PostgreSQL connection refused at the dedicated test port. The existing PostgreSQL test container was stopped. Restarted only that local container; the first pg_isready check returned no response during initialization, then the complete startup logs confirmed readiness. The exact nine tests passed first (9 / 47); no application/configuration/dependency/security change or SQLite fallback.

No implementation test failure, skip or disabled test remains at the time of the focused verification. Final complete results are recorded below after all gates. No live journal/provider request or new editorial HTML. Composer audit is attempted with network explicitly disabled; unavailable advisory data must be reported as unverified rather than contacted.

### Final correction verification

PostgreSQL 17 / isolated fikarohana_test only. All suites pass; no disabled/deleted/skipped test or SQLite fallback.

| Gate | Exact result |
| --- | --- |
| Qualifiers after test DB restart | 9 tests / 47 assertions passed |
| Deleted content | 3 tests / 21 assertions passed |
| Word operators / article scope | 16 tests / 100 assertions passed |
| APC versus absence publication/usability | 3 tests / 29 assertions passed |
| Fragment-only links | 3 tests / 12 assertions passed |
| Legacy metadata / context guards / absence equivalence plus qualifiers | 11 tests / 65 assertions passed |
| Complete EditorialExtractionTest | **121 tests / 948 assertions passed** |
| Dependent JournalCatalogTest + JournalIngestionTest | **63 tests / 471 assertions passed** |
| OfficialSource suites | **118 tests / 463 assertions passed** |
| OpenAlexProviderTest | **64 tests / 376 assertions passed** |
| CrossrefProviderTest | **49 tests / 267 assertions passed** |
| DOAJ API/CSV | **107 tests / 566 assertions passed** |
| Complete Laravel | **565 tests / 3400 assertions passed**, 39.847 seconds |
| Pint --test | Passed |
| Composer validate, network disabled | Valid |
| Composer audit, network disabled | **UNVERIFIED**, exit 100: Packagist advisory request canceled because network is disabled; no request sent |
| OpenAPI / contracts check | Valid / reproducible and current |
| git diff --check | Passed |
| Markdown local links | **164 targets, zero missing** |

The audit failure is unavailable offline advisory data, not an application defect: no online retry or dependency change. Historical audit success is not a fresh correction-pass advisory result.

A final report-inventory script also failed before writing the report: it attempted UTF-8 text decoding for all repository files, including PNG binaries. Corrected only the inventory helper to hash raw bytes; no application change.

Exactly 39 regression cases were added to the existing 82-case extraction suite. Positive bare-count fixtures now state maximum explicitly, with added negative bare/minimum/exact/range cases. Version assertions distinguish corrected V2 from a subsequent V3 identity, and the legacy V1 metadata test proves backward compatibility without overwriting old history.

No live external network/provider/website call, dependency installation, branch, staging, commit, push, history rewrite or development/production migration. Tests use local PostgreSQL and existing finite local-only transport fixtures in the OfficialSource suite. Real editorial HTML remains **NOT VERIFIED**. HEAD/master stays 5da5f75; real .env is unchanged.

Correction verdict: **PASS** for the authorized semantic fixes. Narrow grammar/context coverage, production rights/retention/review policy, unavailable offline advisory data and real editorial HTML coverage remain limitations.

### Correction-pass files

26 files changed from the start of this correction, including the new RuleContext.php. The earlier 45-file inventory is historical; the complete current uncommitted Phase 3E-B inventory has 48 files.

```text
apps/api/app/Models/JournalAssertion.php
apps/api/app/Models/JournalRevision.php
apps/api/app/Services/EditorialExtraction/ArticleTypeRule.php
apps/api/app/Services/EditorialExtraction/EditorialVocabulary.php
apps/api/app/Services/EditorialExtraction/EvidenceUnit.php
apps/api/app/Services/EditorialExtraction/ExtractEditorialSnapshot.php
apps/api/app/Services/EditorialExtraction/FeeRule.php
apps/api/app/Services/EditorialExtraction/LanguageRule.php
apps/api/app/Services/EditorialExtraction/LinkRule.php
apps/api/app/Services/EditorialExtraction/PrepareDocument.php
apps/api/app/Services/EditorialExtraction/ReferenceStyleRule.php
apps/api/app/Services/EditorialExtraction/RuleContext.php
apps/api/app/Services/EditorialExtraction/RuleRegistry.php
apps/api/app/Services/EditorialExtraction/WordLimitRule.php
apps/api/app/Services/JournalCatalog/PublishJournalRevision.php
apps/api/app/Services/JournalIngestion/DetectJournalConflicts.php
apps/api/database/migrations/2026_10_12_110000_add_editorial_extraction_metadata.php
apps/api/tests/Feature/EditorialExtractionTest.php
apps/api/tests/Fixtures/official-sources/extraction/conflicts.html
apps/api/tests/Fixtures/official-sources/extraction/word-limits.html
docs/architecture/DECISIONS.md
docs/data/DATA-MODEL.md
docs/data/JOURNAL-SCHEMA.md
docs/development/EDITORIAL-EXTRACTION-V1.md
docs/development/TESTING.md
docs/development/TODO.md
```


## Narrow structural-context correction — 2026-10-13

The targeted review reproduced three context defects and returned FAIL after the earlier reported PASS: HTML qualifier loss across an intertitle, flattened plain-text qualifier/article context, and same-level headings incorrectly inherited across wrappers. This section supersedes that earlier verdict; historical results above remain historical.

### HTML heading stack and section boundaries

Preparation now traverses the filtered DOM in document order. Encountering hN removes active headings at levels >= N, retains lower-level headings and adds the new heading. Div wrappers do not reset hierarchy. Thus h2 Original Articles → h3 Word limits retains article scope, while a subsequent h2 Review Articles replaces it even in another wrapper. Headings introduced inside section/article/main are removed, together with their deeper headings, once traversal leaves that owner; they are not restored as an invented HTML5 outline. Unknown/example/illustration headings remain raw contextual evidence and veto extraction. Nav and aside content is excluded from preparation and cannot seed the stack. This is conservative markup handling, not a CSS engine.

### Narrow qualifier association

A qualifier-shaped p/div immediately before an intertitle is attached to that heading entry with its original locator and separate qualifier kind. It remains a veto within that active heading scope and disappears when the heading is superseded or its explicit owner ends. Existing direct adjacency to evidence or its wrapper remains supported. Arbitrary earlier paragraphs are not searched across headings. The bounded prefix classifier only vetoes; for/if/when/unless/except/during/only and explicit invitation/waiver/promotion/example concepts are unsupported conditions, not universal facts.

### Plain-text conservative state

There is no inferred arbitrary nesting. Immediately consecutive label-like lines (colon-ended labels, exactly mapped article headings, explicit qualifier lines) form one typed context chain for the immediately following evidence line. Original Articles + Word limits preserves research_article; invited-only + Fees preserves a qualifier and abstains. Unknown article headings are retained without inventing a code; incompatible article contexts abstain.

A blank line clears the chain and ambiguity. A new label chain after evidence starts a new explicit block. A chain scopes only one following evidence line: subsequent non-label prose abstains until a blank boundary or new label chain. Therefore unrelated prose cannot transport article scope to later requirements. After a blank boundary, an explicit new Word limits chain may yield a global abstract requirement with no inherited article code. Chains store at most eight entries of at most 128 bytes; overflow marks the whole association ambiguous, never truncating into an apparently valid scope. Document end discards local state.

### Regression coverage and containment

Database-backed tests cover qualifier/intertitle adjacency, wrappers, equal-level heading replacement, nested h2/h3/h4 ancestry, section/aside/example boundaries, unrelated prose, plain-text adjacent chains, two correctly scoped article paragraphs, chain exhaustion, incompatible/unknown article labels and bound overflow. All del/s/strike cases now test both standalone exclusion and extraction of only valid current siblings while preserving raw snapshots. The adversarial 1024-byte test is renamed to accurately claim text-rule regex coverage; link rules still exit early for href-free input.

Only PrepareDocument, EvidenceUnit comments, EditorialExtractionTest and the two development documents changed in this narrow pass. No downstream APC/conflict/publication, fee/maximum/link grammar, provider, migration, schema, dependency or architecture change. Existing editorial-v2/editorial-dom-v2 identifiers remain unchanged as requested; immutable persisted evidence/reviews are never rewritten. Previously persisted erroneous proposals require explicit reviewer disposition rather than destructive cleanup.

### Failures encountered

- The first test-edit helper used a repository-root path while running from apps/api and failed with FileNotFoundError before any edit. The subsequent command ran only the three pre-existing del cases (3 tests / 21 assertions), not the intended new regressions. Corrected the helper working directory and reran the intended filter.
- The first expanded focused run returned nine errors: a new test read operator->value, but JournalRequirement.operator is a string (only requirement_type is enum-cast). Read the complete error output and the model, corrected only that new assertion, reran its exact test method first (17 tests / 136 assertions), then the focused group (29 / 214) and extraction suite (147 / 1141). No application logic was altered to accommodate that test error.
- Three additional unrelated-prose/overflow cases were then added and final results supersede those intermediate totals below. No failed test was skipped, deleted or disabled; PostgreSQL only.

### Remaining limits

**Real editorial HTML: NOT VERIFIED.** Rules remain a narrow deterministic grammar; arbitrary prose/layout, public excerpt rights, retention and production review policy are unresolved. Composer advisory verification is not performed because this pass prohibits network requests. No live external HTTP/DNS/provider/website call, dependency install, branch, staging, commit or push. Required OfficialSource tests retain their existing finite local loopback fixture; no external source is contacted.

### Final narrow-pass verification

| Gate | Final result |
| --- | --- |
| Focused structural/deleted regressions | **32 tests / 234 assertions passed** |
| Complete EditorialExtractionTest | **150 tests / 1161 assertions passed** |
| JournalCatalogTest + JournalIngestionTest | **63 tests / 471 assertions passed** |
| OfficialSource suites | **118 tests / 463 assertions passed** |
| OpenAlexProviderTest | **64 tests / 376 assertions passed** |
| CrossrefProviderTest | **49 tests / 267 assertions passed** |
| DOAJ API/CSV | **107 tests / 566 assertions passed** |
| Complete Laravel PostgreSQL | **594 tests / 3613 assertions passed**, 53.859 seconds |
| Pint --test | Passed |
| Composer validate, network disabled | Valid |
| Composer audit | **NOT RUN / UNVERIFIED**: zero-network instruction; no advisory request attempted |
| OpenAPI validation / generated-contract reproducibility | Valid / current |
| git diff --check | Passed |
| Markdown local links | **169 targets, zero missing** |

Five files changed in this narrow correction (the larger pre-existing Phase 3E-B diff remains uncommitted):

- apps/api/app/Services/EditorialExtraction/PrepareDocument.php
- apps/api/app/Services/EditorialExtraction/EvidenceUnit.php
- apps/api/tests/Feature/EditorialExtractionTest.php
- docs/development/EDITORIAL-EXTRACTION-V1.md
- docs/development/TESTING.md

Narrow correction verdict: **PASS** for the requested deterministic context boundaries and regression coverage. No remaining identified blocker within this pass. Real editorial coverage and advisory verification remain unverified; no production readiness claim. All changes remain uncommitted on master, HEAD 5da5f75.


## Phase 3E-C2 current V3 preparation and grammar

See [preserved baseline and separate post-fix validation](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#phase-3e-c2--post-fix-editorial-v3--editorial-dom-v3). V3 scans every bounded block, retaining lexical rule candidates rather than all irrelevant prose before applying 512 candidate-unit / 128 KiB normalized-text ceilings. No first/last-unit truncation. Raw 256 KiB and 4096 DOM-element caps stay unchanged; ancestry traversal is bounded at 16 and contexts remain <=8 with 128-byte text. Per-unit input remains 1 KiB. Unknown scope, qualifiers and deleted/hidden markup remain conservative. Eligible overflow still fails rather than hide evidence.

PreparedDocument has separate links (<=128 /16 KiB anchor text) and textual units. Exact submission-action anchors can survive nav/header/footer; their prose cannot. Only LinkRule sees link units. Main-body link labels must also be explicit template/submission roles before budgeting. Fragment/unsafe/template-origin and metadata-only external submission rules remain.

Generic negative fee constructions, named-style sentences with Unicode quotes, neutral editorial section labels and structurally attached abstract maximum list items add deterministic coverage. No publisher names/domains/selectors or journal values are encoded. Conditional absence/style and domain-qualified article types remain unsupported. PeerJ JS, new source transport and larger download limits remain out of scope.

Default registry/preparer are editorial-v3/editorial-dom-v3. Forward migration 2026_10_13_120000_allow_editorial_v3_metadata changes only the existing CHECK allow-list/context obligation, preserving V1/V2 immutability; V3-bearing rollback refuses. Tests retain legacy version coexistence, dry-run and pending review/publication. The real four-snapshot replay recovered 6/6 human positives with zero FP, but is a development-set result. Independent operator-approved/human-labeled holdout is required before exposure. Existing V2 baseline is untouched.


## Phase 3E-C3 — V4 grammar remediation and unresolved context coverage

Default versions advance to editorial-v4/editorial-dom-v4. WordLimitRule adds anchored singular/plural abstract maxima with optional article, must/does/should not exceed, relative-clause construction, maximum, limited to and optional required suffix. Approximate/recommended/minimum/range/qualified prose remains unmatched. Existing article inheritance, section consistency and RuleContext guards apply unchanged. LinkRule shares one submission-label predicate with preparation and recognizes electronic/manuscript submission systems, management systems and portals. Guidelines/instructions/policies/preparation labels remain ineligible. External targets remain metadata; no fetch or approval is triggered.

No qualifier/heading/ambiguity/resource safety policy changes, no publisher/host/locator branches, no fee inference change. In particular generic no-publication-fees does not create APC absence. V4 grammar succeeds on synthetic safe contexts but does **not** recover JMLR: unknown inherited headings plus the oversize immediately preceding example context still force abstention. This retained limitation must not be concealed by changing human gold or dropping FN. See [actual diagnostics and V4 results](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#phase-3e-c3--v4-remediation-and-generalization).

The forward `2026_10_13_130000_allow_editorial_v4_metadata.php` migration extends the existing PostgreSQL document-version whitelist/context obligation; V1/V2/V3 remain compatible. No old migration or immutable stored metadata is edited. Rollback locks the table and refuses retained V4 evidence. Tests cover V2/V3 retained assertions alongside V4, idempotency, empty rollback/reapply and populated refusal. Canonical business API, Phase 2 review/publication/provenance and 3E-A acquisition are unchanged.

JMLR is development/remediation data after this change; its original V3 independent result is preserved separately. New independent candidates require operator approval then 3E-A acquisition and human gold validation before V4 evaluation. Phase 4 remains NOT_READY.


## Phase 3E-C4 — V5 context robustness

Unknown heading is now distinct from unsafe/unsupported scope. Language, numeric word-limit and submission-action rules can tolerate unknown **heading** contexts only when their own full grammar independently supplies subject, predicate, value and canonical scope. Bare language/style/number fragments cannot use unknown headings to invent scope. Fees, article lists, styles and templates keep their prior context eligibility. Unknown captions/table headers, ambiguity/overflow, qualifiers, examples, hypothetical/optional/conditional prose, incompatible article scopes and unsupported article/special/domain scopes still fail closed.

A closed illustrative paragraph immediately preceding a new heading belongs to its preceding block. It is not automatically inherited as qualification of the entire new section. Only immediate paragraph/heading relationships are inspected; no backwards search or distance window. Direct examples preceding evidence, colon introductions, next/following-section examples, explicit example headings and conditional qualifiers still veto. The 128-byte context size cap is unchanged; retained oversize relevant contexts still mark ambiguity rather than silently truncate. Link semantics have a separate evidence channel: action labels may survive neutral headings, while conditional/example prose in their own leaf block, disabled/hidden/deleted markup, unsafe URL metadata and explanatory links remain rejected. Large ancestor containers are not concatenated into pseudo-local qualification.

LanguageRule additionally understands a bounded French eligibility-list construction with an explicit English-title/abstract translation dependency for non-English manuscripts. It emits **English only** when listed; conditioned languages remain unmapped. Unknown languages, ambiguous/conditional subjects, further eligibility restrictions or unknown trailing prose abstain. Only unrelated technical-subject formatting notes may follow the closed eligibility sentence; whole unit/context remains the evidence. No journal name/host/locator special case, translation service, new conditional-language model or inference from document language.

Version defaults intentionally advance to editorial-v5/editorial-dom-v5. New forward metadata migration preserves V1–V4 and existing context constraints; populated V5 rollback refuses. No canonical schema ontology/business/publication change. All old gold/history remains immutable. Six stored development pages now yield 9 TP / 0 FP / 0 FN; this is **development remediation**, not independent validation. See [C4 results and safety audit](EDITORIAL-REAL-WORLD-VALIDATION-V1.md#phase-3e-c4--context-robustness-and-generalization). New V5 candidates require fresh human acquisition and gold approval; Phase 4 NOT_READY.
