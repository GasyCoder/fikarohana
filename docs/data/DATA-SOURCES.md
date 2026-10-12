# Journal source policy

Status: Phase 2 evidence, Phase 3A controlled ingestion and Phase 3B bounded DOAJ v4 adapter are implemented. Official DOAJ schema/source/terms were reviewed on 2026-10-11; the one-record live probe failed before an HTTP response, so live mapping is unverified. See [DOAJ adapter](../development/DOAJ-ADAPTER-V1.md). Phase 3C Crossref identity enrichment is implemented; Phase 3D OpenAlex enrichment is implemented; official-source transport and production activation remain planned. See [Crossref adapter](../development/CROSSREF-ADAPTER-V1.md).

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

Planned delivery order: **3A** common ingestion framework (adapters, staging, idempotency, validation, explicit review/publication, provenance); **3B** DOAJ API/official CSV normalization/mapping with synthetic and permitted recorded fixtures; **3C** Crossref identity enrichment; **3D** OpenAlex subjects/discovery enrichment; **3E** official journal/publisher requirements, APC and template evidence, with controlled fetching later. Synthetic local mapping and explicit bounded DOAJ retrieval are implemented; no scheduled/full-catalog synchronization is enabled. Crossref bounded existing-candidate lookup is implemented; OpenAlex bounded existing-candidate taxonomy enrichment is implemented; official-source transport remains unimplemented. See [Phase 3 plan](../development/JOURNAL-INGESTION-PLAN.md) for exit gates and source-specific boundaries.

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

Implemented review states are `pending`, `verified`, `stale`, `conflicting` and `rejected`; absent review means pending. Missing is represented by no known value and an availability reason, not a fictitious assertion. Field-specific configurable freshness windows must be approved before release. On expiry, retain history and mark stale; a new fetch cannot automatically reset verification.

Public profiles display evidence and dates for important fields. Strict constraints require current verified applicable assertions. Preferred criteria may surface unknown/stale facts with warnings; unresolved conflicts are never resolved silently.

## Operational review

Measure identifier collisions, missing scope, fee/language coverage, stale assertions, conflicts and provider failures. Assign review queues for disputed facts and source changes. Provide a correction path without allowing anonymous edits to publish immediately.

Before activating an adapter, document terms/permissions, sample records, mappings, field authority, refresh/review schedule, tests and rollback. Do not promise exhaustive indexing, trustworthy-journal classification or free document redistribution without evidence and rights.

## Implemented provenance and controlled ingestion primitives

`journal_sources` identifies providers without credentials or hidden confidence/authority percentages. `source_records` records source ID, external key, immutable revision fingerprint, source URL, first retrieval timestamp, optional SHA-256 content hash and optional permitted metadata reference. Unique `(journal_source_id, external_id, revision_fingerprint)` supports repeat-safe creation; a changed fingerprint creates a new observation. Reusing an existing observation never advances its retrieval date or verification freshness. Records are captured or quarantined; quarantined observations cannot support publication. No source document contents are downloaded or copied.

Each material claim has its own `journal_assertion` and source record, context/effective dates, optional locator/original wording and optional predecessor. Separate append-only reviews record status, reviewed/verified times, method and optional non-sensitive reviewer reference. Phase 3A provides trusted-operator Artisan review and controlled publication; no public/admin UI or external verification automation exists. Observation/review clocks are distinct; verification cannot predate retrieval.

`SourcePrecedence::preferredTypes` is an explicit review preference: official journal/publisher for current fees, languages, article types, requirements, templates, submission and scope; relevant index registry for indexation; official/directory/bibliographic sources may corroborate identity. It neither ranks trust universally nor auto-deletes losing evidence. Competing current assertions require explicit review resolution; even corroborating duplicate verified assertions must be resolved to one selected claim per context in this conservative V1 primitive. A registry claim remains specifically attributed; DOAJ/provider presence is not universal trust.

HTTP(S) syntax/userinfo is checked and hostname/scheme normalized while preserving path/query/fragment. Journal/publisher URLs remain untrusted strings and are not fetched. The DOAJ API client requests only fixed official HTTPS API roots with redirects disabled; the CSV client requests the stable official `/csv` source and allows one validated redirect to the exact observed DOAJ S3 bucket/file pattern. Both use bounded transport without credentials; CSV field URLs are never followed. This does not implement a general SSRF-safe official-site fetcher. Query strings/fragments are legitimate metadata and never authorize fetching. Optional malformed website values stay in restricted raw evidence but are omitted from mapped claims. Retained raw source evidence may contain provider-supplied query values or invalid links; never log them or treat them as credentials to use. Fixtures use only synthetic public parameters, never actual secrets/private researcher text. Before real-provider ingestion, approve provider terms/rights, field coverage, ownership verification, currency/locale/taxonomy vocabulary, retention/freshness and conflict review operations. [Phase 2 report](../development/JOURNAL-DATA-MODEL-V1.md) details the schema and deletion rules.

## DOAJ CSV/API acquisition policy

Official verification date: **2026-10-11**. [Journal CSV documentation](https://doaj.org/docs/journal-csv) identifies the stable public source `https://doaj.org/csv`, with data within 30 days; premium hourly/authenticated acquisition is outside scope. [Terms](https://doaj.org/terms/) license journal metadata under CC0. Observed live source redirects 307 to the DOAJ-owned `doaj-live-journal-csv.s3.amazonaws.com` export; the signed location is not retained as provenance. The documentation advertised 24.45 MB; operational size/date can vary. The actual observed CSV has 52 UTF-8 question-labelled columns, including human-readable language/publisher-country names, LCC codes/paths and `APC amount` entries separated by `; `.

CSV is for bootstrap/bounded batch import; API is for targeted lookup/refresh. Neither provides full-catalog synchronization here. Both retain provider, transport, stable source, first retrieval, external ID where exposed, raw payload/row fingerprint and mapper version through existing Phase 3A tables. Unknown external IDs use an explicitly local `csv.` surrogate, not an invented DOAJ ID. Exact checksum-valid ISSNs remain identity authority.

[Official fee clarification](https://blog.doaj.org/2024/11/29/more-transparent-fee-information/) separates no APC from no other fees. Explicit `No`/false APC is preserved as `doaj_reported_no_apc` basis/context with null monetary values; scalar availability remains unknown because the existing known-money model requires a currency. This is distinguishable from unknown APC, known amount, not-applicable and independent waiver metadata. No charge does not invent a currency, imply all fees are absent or automatically publish a free-journal fact. Conflicts with reported positive APCs are retained for review. See [detailed mapping and limits](../development/DOAJ-ADAPTER-V1.md).

## Crossref V1 authority and acquisition

[Crossref adapter](../development/CROSSREF-ADAPTER-V1.md) records official sources accessed 2026-10-11, singleton `/journals/{issn}`, optional real `mailto`, reuse constraints and rate/error policy. Exact known/staged ISSN inputs only; no catalog bootstrap, works/member crawling or URL fetching. Bibliographic identity/title/publisher evidence enters the same staging/assertions/review path and cannot overwrite DOAJ evidence or automatically verify/publish facts. Existing typed ISSN ownership is reused with the corroborating observation retained on staging; new declared types get field assertions. Unsupported/untyped new identifiers remain raw, never inferred.

DOAJ remains OA-directory/language/subject/APC evidence; Crossref is identity corroboration only. Official journal/publisher sources later supply current editorial requirements/APC/templates. Crossref never establishes accepted languages, fees, requirements, templates, acceptance probability, quality or Scopus/WoS membership. Disagreements remain reviewed conflicts or retained quarantine; missing matches never delete journals.

## OpenAlex V1 authority and acquisition

[OpenAlex adapter](../development/OPENALEX-ADAPTER-V1.md) records current official API/auth/limits/CC0 sources accessed 2026-10-12. Only explicit known-candidate ISSN singletons are retrieved; optional API key stays in configuration/Authorization header, never provenance. Source topics and hierarchy are provider-derived scholarly discovery evidence, not current editorial aims/scope, accepted languages, APC or submission rules. Counts stay raw descriptive observations, not ranking/quality/acceptance scores. Pending/conflicting evidence requires explicit review; identifiers and taxonomy versions never overwrite history. No concepts mapping, site/works fetching or catalog crawler.
