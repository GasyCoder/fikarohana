# Phase 4A — Journal Discovery and Recommendation API V1

Status: Laravel API implemented against the existing published catalog, baseline `f5d5cda`; uncommitted. Anonymous discovery and deterministic metadata relevance only. No UI, provider request, LLM, vectors, uploaded manuscript, saved research input, new table or dependency.

**editorial-v5 independent holdout validation remains pending. Phase 4A does not depend on unvalidated V5 extraction.** Rules, preparation, protocol, audit, fingerprints and historical artifacts are untouched. Human-reviewed and explicitly published metadata alone can be exposed; raw/pending V5 proposals are never retrieval/ranking inputs.

## Canonical boundary

Each request pins the newest published `CatalogRevision` and reads only its `catalog_revision_journals` membership and published `JournalRevision`. Journals must be active and have a usable global sourced title. Use the existing `JournalRevision::usableSelections()` relation: latest verified review, effective dates and competing verified/conflicting assertions govern current usability. Never read `journals.title`, working publisher links, staging or arbitrary assertions as canonical metadata. Publication service/schema are unchanged.

Requests execute in a repeatable-read, read-only PostgreSQL transaction. Under an already open caller transaction (including RefreshDatabase tests), the caller controls isolation; normal HTTP routes open their own read-only transaction. Source observations are loaded with ID, source URL and retrieval timestamp only; no raw HTML/body is loaded into the public projection. Detail provenance exposes selected assertion IDs/source URLs, retrieval timestamps and as-published review verification timestamps, never reviewer identity, original excerpts, source bodies or ingestion diagnostics. Current usability is checked separately from the immutable publication selection.

## Endpoints and contracts

All endpoints use `/api/v1`, the existing JSON/error/request-ID/no-store conventions, and one shared anonymous IP rate limit of 60 requests/minute. Sanctum stateful-session middleware is excluded for these public read operations. No credentials are needed; recommendation input is request-scoped and never logged/stored by these handlers.

| Endpoint | Behavior |
| --- | --- |
| `GET /api/v1/journals` | UUID-ordered bounded list; default 20, maximum 100 per page; page 1–10000 |
| `GET /api/v1/journals/{journal}` | Published active journal projection with provenance; invalid/nonexistent/unusable/unpublished UUID returns safe 404 |
| `POST /api/v1/journal-recommendations` | Hard-filtered, deterministic relevance order; default 5, requested limit 1–10; valid empty/fewer-than-requested responses |

Listing supports `q` (up to 500 characters), exact `subject` UUID, `publisher` UUID, vocabulary `language`/`article_type`/`indexation` codes and `no_apc` boolean. `q` is a literal case-insensitive substring of usable canonical title, publisher name, subject name or normalized identifier. `%`, `_` and backslashes are escaped, not treated as user-controlled wildcard patterns. Subject listing does not silently include descendants.

Recommendation requires nonempty `title` (<=500 characters); optional `abstract` <=10000, unique `keywords` <=20 each <=100, unique `subject_ids` <=10, `language`, `article_type`, `no_apc_only`, unique `indexation_services` <=10, and `limit`. IDs/codes must exist in local vocabulary; a valid vocabulary entry need not have matching published journals. No automatic provider lookup. Existing Laravel 422 errors return bounded field messages without SQL or research content. JSON contracts and generated TypeScript types are canonical in `packages/contracts`.

Public journal payload: id, revision_id, published title/publisher, identifiers, subjects with parent/level, languages/article_types/indexations with explicit supported flags, APC status and exact decimal-string fee values/context. Detail adds provenance. Recommendations add relevance_score, nullable active score_components, deterministic reasons and catalog/algorithm metadata.

## Eligibility and unknown semantics

Language/article type must have applicable explicit published `supported=true`; explicit applicable negative claims veto matching. Every requested indexation must match (AND), never any one of the requested services. Only globally applicable facts or facts scoped to explicitly requested article type/language satisfy filters; scoped facts cannot become global eligibility. A negative applicable under a possible unspecified context conservatively vetoes compatibility/no-APC rather than silently choosing another context.

`no_apc=true` / `no_apc_only=true` requires published usable APC `not_applicable` evidence only; any applicable known monetary APC, including zero, vetoes the match. `no_apc=false` requires a published applicable known positive APC. Unknown APC satisfies neither. Generic publication/submission/page/color fees are not APC. Fee type, availability, amount, currency, basis, applicability and structured context remain separate. A known monetary zero retains `apc.status=known` and its exact zero/currency; it is never relabeled `no_apc`. Schema constraints ensure nonnegative money and currency, but do not establish unconditional fee absence: `basis` and `applicability` may describe waivers, promotions or conditions.

Existing unknown-scalar `official_reported_no_apc` / `doaj_reported_no_apc` proposals cannot be published by current canonical workflow. Phase 4A does not bypass that policy or promote these proposals. Reviewed `not_applicable` APC already fits the canonical schema/publication workflow as explicit inapplicability within the applicable reviewed context. Reviewed numerical zero is publishable money, not equivalent no-APC evidence. No publication redesign is introduced.

Missing usable APC => `apc.status=unknown`, null amount/currency. Multiple applicable APC entries => `context_dependent`, with preserved per-fee contexts; no arbitrary winning price. Lists/details without explicit context omit scoped facts to prevent generalization. Other mandatory fees may exist even when no APC is explicit; `no_apc` does not promise publication is entirely free. Unknown language/indexation is not false, and an empty collection means no usable published facts, not proven incompatibility.

## Deterministic score

Configuration is centralized in `config/journal_recommendation.php`, version `metadata-relevance-v1`. All score features are in [0,1]. Positive weights and configured bounds are validated by the scorer.

- **Lexical L:** normalize title + abstract + keywords with Unicode lowercase and non-letter/non-number token separation, remove tokens shorter than two characters and duplicates, keep at most the first 24 tokens in input order. L = number of distinct retained tokens appearing as literal substrings in the usable global title or global subject labels / number of retained tokens. No invented aims/scope, language detection, stemming or external search.
- **Taxonomy T:** strongest exact or direct ancestor/descendant relationship between any requested subject and any usable global published journal subject: `T = 0.7^d`, distance d=0..3; no relation =>0. Exact=1, adjacent=0.7, two edges=0.49, three=0.343. Edges use local `Subject.parent_id`, including stored OpenAlex Domain → Field → Subfield → Topic; generic local subject trees use the same bounded behavior. Shared sibling ancestor alone is not treated as a direct relation. Multiple subject selections use strongest match, not additive duplication.
- **Score:** `relevance_score = round(100 * (0.6*T + 0.4*L) / sum(active weights), 2)`. Taxonomy is active only when subject_ids are supplied; lexical is active only when normalization produces tokens. Omitted subject_ids do not penalize lexical scoring. If neither component is active, score=0. No renormalization against a journal's missing metadata. Rank by unrounded score descending, then stable journal UUID ascending; component displays are rounded to two decimal places.

Eligibility constraints provide no score or prestige/quality boosts. The score represents metadata relevance, not likelihood of manuscript acceptance. Weight values are engineering V1 configuration, not validated relevance/acceptance estimates. Zero relevance remains an eligible but unmatched result when filters pass; reasons are empty and score=0, never fabricated evidence or weakened eligibility.

## Explanations and coverage

`keyword_match` labels are actual normalized tokens in the same canonical title/subject metadata used by L. `subject_match` / `taxonomy_match` name actual linked subjects with their UUID and strongest relationship distance. No AI prose, acceptance prediction or unsupported assertion. Scoped presentation facts are not allowed to manufacture a reason for a global scoring feature.

Meta: requested_limit, exact candidates_after_filters, returned, scored_candidates_loaded, catalog_revision_id, algorithm_version, retrieval_mode (`deterministic_metadata`) and score_semantics (`metadata_relevance_only`). Payload's APC unknown/other empty fields make coverage visible. No eligible journals means an empty data array and valid metadata; no fabricated fallback.

## Performance and limitations

Filters, lexical matching, hierarchy relationships and final score order are evaluated in PostgreSQL. Related-subject recursion is bounded to three edges in either direction, inputs to ten subject IDs and 24 tokens. At most 200 ranked candidate IDs/scores enter PHP; only returned journals (<=10) have their metadata projected for recommendations. Eager loading batches selected facts/typed values; reason lookup reads only subjects linked to returned results, never the whole taxonomy into PHP. Query-count tests guard against per-result N+1.

SQL computation still scales with eligible catalog size; literal substring predicates and nested current-usability checks are not a production large-catalog full-text index strategy. No arbitrary UUID pre-limit sacrifices better-ranked matches. A measured PostgreSQL full-text/materialized verified projection may be a later improvement; no new infrastructure or unjustified index is introduced in V1. Detail/list facts per journal rely on canonical publication size; future very large metadata sets may require compact projection limits. Exact candidate counts also cost a database scan.

No real-data ranking relevance threshold, latency guarantee or production catalog coverage is claimed from synthetic tests. Persisted ingestion/publication semantics, mixed scoped fees and currency-free absence remain existing model constraints. Editorial holdout pending does not block reviewed-catalog discovery, but does block claims that V5 independently validates editorial extraction. Future UI, semantic matching and assistant work remain separate.

## Verification

PostgreSQL synthetic tests cover public routes, publication/current usability, raw/pending/conflicting data exclusion, exact filters/unknown APC, scoped applicability, pagination/order, lexical/taxonomy/active weighting, deterministic explanations/ties, validation, empty/fewer results, shared rate limit, no network and unchanged table counts. Existing catalog/publication tests and full suite verify compatibility. No test calls live providers or independent editorial benchmark.

Encountered and resolved: PostgreSQL test container was stopped (connection refused); started the existing local test container without image/dependency downloads. One new fixture tried to publish already expired evidence; PostgreSQL correctly rejected it, so the test now exercises post-publication stale review rather than bypassing publication guards. Initial OpenAPI assembly inserted a schema at an unanchored responses key and referenced a nonexistent 404 component; corrected document placement and an inline existing error schema before validation/generation. No implementation security relaxation or skipped test.

Final gates:

| Check | Result |
| --- | --- |
| Focused Phase 4A PostgreSQL tests | 18 tests / 203 assertions passed |
| Catalog/publication integration (initial focused run) | 74 tests / 587 assertions passed, including the initial 11 Phase 4A tests |
| Complete Laravel PostgreSQL suite | **712 tests / 4506 assertions passed** |
| Pint --test | Passed |
| Composer validate --no-check-publish | Valid |
| OpenAPI 3.1 validation | Valid |
| Generated TypeScript reproducibility | Current, exact check passed |
| git diff --check / local document links | Passed |
| Frozen V5 integrity | 14 fingerprints and all protected extraction/protocol/audit/validation files byte-identical to baseline |

Composer audit was not run because advisory lookup would require network access; no dependencies or lockfiles changed. Existing local PostgreSQL test container alone was started. No development/production migrations, provider requests, branch, commit, push or UI work. Phase 4B may consume these contracts; real-catalog coverage and ranking evaluation remain separate release work.

## Targeted no-APC semantic correction

Numeric zero does not prove unconditional NO-APC. `fee_amount_currency_valid` only enforces finite nonnegative money plus currency; `catalog_check_fact` ties an amount to `availability=known`. Publication requires verified known/not-applicable evidence, but does not classify a zero as unconditional. `JournalAssertion::isExplicitNoApc()` separately identifies null-money explicit absence bases, and publication currently refuses their unknown scalar availability. Phase 4A therefore matches only usable published APC `not_applicable` in its reviewed structured context, never unknown proposals or automatic zero-money equivalence. This is a filter/projection correction; canonical publication and V5 remain unchanged. `no_apc=false` still means explicit known positive APC, not the complement of true, so unknown/zero match neither boolean filter.

Targeted correction checks: Phase 4A plus relevant catalog/ingestion publication tests **82 tests / 692 assertions passed**; Pint passed; `git diff --check` passed. The earlier 712-test full-suite result above predates this correction and was not rerun. API/OpenAPI/generated contracts contain no acceptance-probability/quality-score fields; contract wording already requires explicit no-APC, so no contract changes or regeneration were needed. Published/current selection, pending-proposal exclusion and zero-write recommendation regressions passed in the targeted run. All 14 V5 fingerprints remain unchanged. No network, commit or push.
