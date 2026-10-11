# Journal source policy

Status: source-selection design. No live provider documentation, access rights, quotas or coverage were verified during this documentation task. The entries below are candidate roles, not integration or licensing guarantees. Validate current official documentation and terms before implementation.

## Candidate register

| Source | Official discovery link | Candidate use | Limitation / required validation |
| --- | --- | --- | --- |
| DOAJ | [doaj.org](https://doaj.org/) | OA journal identity and available directory metadata | Verify fields, access, reuse rights, update cadence and fee context; not proof of every indexation |
| OpenAlex | [openalex.org](https://openalex.org/) | Journal/source identity, subject and related-work signals | Verify access and entity semantics; do not infer current author rules from bibliographic coverage |
| Crossref | [crossref.org](https://www.crossref.org/) | DOI/ISSN linkage, publisher/work metadata, related articles | Deposited metadata is not automatically current journal fee or guideline evidence |
| Official journal sites | Journal-specific URL recorded per journal | Scope, languages, article types, fees, timelines, guidelines and templates | Validate ownership, page date, applicability, crawling/reuse permission and redirects |
| Official publisher sites | Publisher-specific URL recorded per journal | Publisher identity and authoritative journal policies | Publisher-wide policies may not apply to all titles or article types |
| Other academic registries | Added only after review | Specific indexation/identity evidence | Document authority, access rights and supported claim before use |

Maintain a provider register with adapter version, permitted operations, credential location, terms URL/review date, license/attribution, quota policy, contact, field coverage and enabled status. Never put API keys in this document or committed source dumps.

## Authority is field-specific

Identity can be corroborated by registries and official pages. Current submission rules and templates prefer applicable journal/publisher guidance. Indexation needs evidence from the relevant index or explicit attributed claims; an OpenAlex/Crossref record alone must not be presented as independent index membership. DOAJ inclusion is a specific directory fact, not a blanket endorsement.

Fee assertions must specify fee kind, amount/currency, article type, OA route, tax/waiver conditions and effective date where available. A missing APC entry is unknown, not free. Conflicting current fees enter review; do not pick the smaller value to improve recommendations.

Reported timelines need stage/metric and source context: first decision, review, acceptance-to-publication and submission-to-publication are different measures. Never translate an average into a guaranteed deadline.

## Ingestion and resolution

1. Fetch from an approved provider with bounded rate, timeouts and retries; record the ingestion run and retrieval time.
2. Preserve source ID, URL, content hash and permitted raw artifact. If retaining content is prohibited, retain permitted metadata and a reproducible retrieval reference.
3. Stage records and normalize ISSNs, URLs, currencies, languages and discipline mappings while keeping original values.
4. Resolve identities by valid identifiers plus supporting evidence. Similar names alone cannot merge titles; title changes, print/electronic editions and transferred publishers need review.
5. Produce field assertions with evidence references and verification state. AI extraction produces a proposed assertion, never an automatically verified fact.
6. Validate applicability and resolve conflicts under a documented field policy or human review. Publish a coherent revision only after checks pass.
7. Refresh search/vector materializations by revision; retain prior assertions for audit under approved retention rules.

A unique provider/source record key and content hash make repeat imports idempotent. Use checkpoints and transactional publication. Quarantine malformed records; report failed and skipped counts. A partial provider outage must not erase valid data.

## Provenance and freshness

Every material published fact must have an assertion ID, source/provider, source URL, `retrieved_at`, nullable `verified_at`, verification method/reviewer and applicable context. `retrieved_at` means observed; `verified_at` means checked under a stated policy. A fetch alone is not verification. Original source publication dates, if known, are separate.

States are `unverified`, `verified`, `stale`, `conflicted` and `rejected`. Missing is represented by no known value and an availability reason, not a fictitious assertion. Field-specific configurable freshness windows must be approved before release. On expiry, retain history and mark stale; a new fetch cannot automatically reset verification.

Public profiles display evidence and dates for important fields. Strict constraints require current verified applicable assertions. Preferred criteria may surface unknown/stale facts with warnings; unresolved conflicts are never resolved silently.

## Operational review

Measure identifier collisions, missing scope, fee/language coverage, stale assertions, conflicts and provider failures. Assign review queues for disputed facts and source changes. Provide a correction path without allowing anonymous edits to publish immediately.

Before activating an adapter, document terms/permissions, sample records, mappings, field authority, refresh/review schedule, tests and rollback. Do not promise exhaustive indexing, trustworthy-journal classification or free document redistribution without evidence and rights.
