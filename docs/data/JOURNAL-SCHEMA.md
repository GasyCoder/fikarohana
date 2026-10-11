# Journal schema and fact semantics

Status: logical specification, not an implemented API contract. Public fields are projections of reviewed assertions, not unsupported model outputs.

## Identity and discovery fields

| Field | Logical type | Semantics |
| --- | --- | --- |
| `id`, `revision_id` | Opaque identifiers | Stable journal and immutable published revision |
| `name`, `alternate_names` | Text, text list | Sourced current title and historical/alternate names |
| `publisher` | Identity reference | Publisher with dated evidence; not necessarily journal owner |
| `issn`, `eissn`, `other_identifiers` | Validated identifiers | Print/electronic/provider distinctions; checksum/format validation |
| `country` | Country code plus original value | Define source meaning; publisher location is not silently journal country |
| `disciplines`, `specializations` | Vocabulary references | Versioned, sourced classification and original provider labels |
| `scope` | Text with language | Authoritative aims/scope; derived summaries separately labeled |
| `languages` | Language entries | Accepted manuscript languages, article-type context; site language is not acceptance evidence |
| `article_types` | Normalized entries + original terms | Accepted types with context and evidence |
| `open_access` | Model + conditions | Known model such as full/hybrid/subscription/other; unknown remains explicit |
| `lifecycle` | Enum | Active, inactive, merged or withdrawn; active does not mean endorsed |

## Publication information

| Field group | Required structure when known |
| --- | --- |
| `fees` | Kind (APC/submission/page/color/other), exact amount or range, currency, basis, article type, OA route, tax status, waivers/conditions, effective date and evidence |
| `timelines` | Stage, metric (mean/median/range/reported estimate), value/unit, population/context, source reporting period |
| `indexations` | Registry name/ID, membership status, coverage/effective dates, verification and evidence; separate publisher claims from index-confirmed membership |
| `requirements` | Rule kind, article/language context, section, operator/limit, unit, original wording, severity when explicitly defined, evidence |
| `templates` | Document kind, official URL, applicable article type, language, format, revision/hash if available and access/reuse status |
| `guidelines` | Official URL, document language, retrieval/verification and document revision |
| `official_urls` | Journal home, publisher page, author instructions, submission system; validated role and ownership |

Requirement kinds include manuscript/abstract word limits, abstract structure, keyword counts, reference style, figure/table rules, submission formats and required declarations. A suggested recommendation must not become a mandatory rule. Unsupported complex wording remains sourced text rather than a fabricated numeric limit.

APC is not total publication cost. Zero APC requires explicit applicable evidence; other fees must be shown. Fee waivers are conditional. Cross-currency comparison requires a separately sourced dated conversion and user-visible calculation; MVP avoids automatic conversion. Strict budget eligibility is indeterminate if currency, applicable fee range or mandatory costs are unresolved.

## Fact envelope

A material field or list item includes:

- `value`: typed value, nullable when not known.
- `availability`: `known`, `unknown`, `not_applicable` or `conflicted`.
- `verification`: `verified`, `unverified`, `stale`, `conflicted` or `rejected` for supporting assertions; public projections do not expose rejected claims as facts.
- `evidence`: assertion/source IDs, provider, official/source URL, `retrieved_at`, nullable `verified_at`, method and document locator where relevant.
- `context`: article type, language, OA route and effective dates as applicable.
- `derivation`: only for derived calculations/interpretations, with method/version and underlying assertion IDs.

Unknown fields have no pretend citation. Conflicts can cite competing assertions without presenting one as the resolved value. Publication APIs should expose public-safe verification metadata, not internal reviewer personal information.

Illustrative shape only; this is not a real journal fee or finalized OpenAPI:

```json
{
  "apc": {
    "value": null,
    "availability": "unknown",
    "verification": null,
    "evidence": [],
    "context": {"article_type": "research_article"}
  }
}
```

## Validation and publication

Validate identifier formats/checksums, bounded strings/lists, supported currency/language codes, nonnegative monetary values, valid dates, fee ranges, unique applicable memberships and URL schemes. Do not derive language from country, fees from OA status or indexation from a DOI. An inactive guideline link requires review rather than inventing a replacement.

A journal becomes eligible for discovery after sourced identity and usable scope evidence have passed review. Strict constraints need current applicable verified facts. Incomplete metadata does not disqualify every journal automatically; it limits which filters/categories can truthfully include it. Recommendation results explicitly expose important unknowns.

A material source change creates a new revision and invalidates related cached/derived data. Future projects retain their selected snapshot and show a newer-guidance notice rather than silently replacing requirements.
