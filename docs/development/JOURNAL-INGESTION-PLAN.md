# Phase 3 — Journal source ingestion plan

Status: planned, following implemented [Phase 2](JOURNAL-DATA-MODEL-V1.md). The user-defined delivery order is 3A → 3B → 3C → 3D → 3E. Phase 3A is now implemented with a transport-free synthetic adapter; [JOURNAL-INGESTION-V1](JOURNAL-INGESTION-V1.md) records its actual scope and verification. Bounded Phase 3B code/offline tests are now implemented; the small live connection failed, leaving deployed-schema/recorded-fixture validation pending. See [DOAJ adapter](DOAJ-ADAPTER-V1.md). Phases 3C–3E remain planned.

Laravel owns ingestion orchestration, validation, identity resolution, review and canonical publication. PostgreSQL stores durable staging and provenance. Next.js and Expo remain API clients; FastAPI is optional specialized compute. These phases do not require Redis, embeddings, object storage or a new public API. Any later infrastructure activation needs its own documented scope.

## 3A — Source ingestion framework

Build the common pipeline with synthetic provider fixtures before connecting a real provider:

provider adapter → durable staging → normalization/validation → identity resolution → pending assertions → explicit review → transactional publication → provenance

- Define a small provider adapter boundary for source identity, adapter/mapping version, external record key and bounded record input. Provider-specific transport and mappings stay in adapters; canonical rules stay in Laravel.
- Record runs and staged items with source, original retrieval time, external key, revision fingerprint, adapter/mapping version, outcome and sanitized validation errors. Retain only permitted, bounded payloads; specify retention before storing real provider content. Staging is separate from verified catalog facts.
- Make repeat processing and publication idempotent. Reuse the Phase 2 source observation key `(journal_source_id, external_id, revision_fingerprint)` without resetting its retrieval time. Distinguish source-content revision from mapping version: a mapping change must not masquerade as a fresh retrieval. Replaying the same processed proposal must not create duplicate assertions, reviews or catalog revisions.
- Validate schema, types, checksum-valid identifiers, URLs, vocabularies, provenance and field applicability before creating canonical claims. Missing information remains unknown; zero/false/not-applicable require explicit evidence. Do not default missing APC to free or infer publication language from website language.
- Quarantine invalid items with actionable error codes. Keep valid items recoverable during partial failures; missing records in a failed or partial batch never trigger withdrawal. Define bounded retries and checkpoints before real transport is enabled.
- Resolve journals using valid identifiers and supporting evidence. Similar titles never auto-merge. Preserve conservative global ISSN ownership; collisions, transfers and edition ambiguity enter review. Repeated observations of an existing identifier must respect the immutable identifier ownership model rather than insert duplicate identifier rows.
- Materialize accepted proposals as field-level pending assertions and typed facts using Phase 2 primitives. Retrieval and structural validation are not verification. Record review method, actor/reference, outcome and dates; do not add a public administrative API or full roles UI merely for this slice.
- Publish only explicitly selected, currently verified applicable assertions through the existing journal/catalog revision service. Unresolved conflicts block publication; source precedence remains review guidance. Record publication results so a retry cannot publish the same selection twice. Failed publication must roll back its revision and leave the proposal recoverable.

Exit gate: PostgreSQL tests cover repeat/concurrent processing, duplicate publication prevention, changed fingerprints, mapping-version replay, unknown versus explicit zero, invalid ISSNs, identifier collisions, quarantine, partial failure/resume, pending versus verified, conflict rejection, publication rollback and field-level provenance. Synthetic fixtures exercise the adapter boundary. No live provider calls are part of 3A.

## 3B — DOAJ, first provider

Implement the first real adapter using the approved DOAJ API access method. Verify current official documentation, terms, reuse/attribution rights, quotas and supported fields before recording samples or enabling calls; API details are not settled by this plan.

- Add bounded API transport, pagination/checkpoints, timeouts, rate limits and retry handling using the 3A pipeline.
- Specify a versioned mapping from actual provider fields to staged proposals and canonical vocabulary. Preserve original values and field locators alongside normalized identifiers, names, URLs and explicit fee/language context.
- Use DOAJ for attributed directory/OA metadata and identity; coverage is neither universal indexation nor blanket journal endorsement. Aggregated fees or rules remain source-attributed claims subject to review and conflicting official evidence.
- Keep synthetic fixtures for missing, malformed, zero, paid, conflicting and repeat records. Add small recorded provider fixtures only when retention/reuse is permitted; sanitize credentials and request metadata, record origin, capture date, fixture hash, mapping version and permitted-use basis. Recorded fixtures are not automatically verified journal facts.
- Make ordinary tests deterministic and offline. Live checks, if later enabled, are explicit and separate; never require credentials for the normal test suite.

Exit gate: reviewed mapping and permitted recorded fixtures, offline normalization/transport-failure/pagination/replay tests, provenance preserved through review/publication, and a bounded pilot run with coverage and quarantine counts. Provider activation and public redistribution each require the applicable rights/review decisions.

## 3C — Crossref identity enrichment

Add Crossref through the same framework to corroborate journal identifiers and publisher/bibliographic identity where the approved API and evidence support them. Validate entity semantics and provider terms first. Treat disagreements, title changes and publisher transfers as identity-review proposals; do not silently replace existing ownership.

Crossref does not become the authority for current APC, accepted languages, author guidelines, templates or submission rules. A missing match cannot delete a journal. Offline synthetic and permitted recorded fixtures must cover identifier linkage, collisions, missing matches and repeat enrichment.

Exit gate: explicit identity-only field mapping, auditable corroboration, collision handling and idempotency tests; no submission-rule enrichment.

## 3D — OpenAlex subjects/discovery enrichment

Add OpenAlex through the same framework for supported source identity and subject/discovery signals. Validate current provider entities, field coverage and terms rather than assuming work-level subjects are journal editorial scope.

Document taxonomy/version mappings, distinguish derived bibliographic signals from verified journal scope, and retain their provenance. Unmapped subjects remain unresolved. OpenAlex does not establish accepted publication languages, current requirements, APC or independent index membership. This adapter phase does not implement recommendation, embeddings or discovery UI.

Exit gate: reviewed subject mappings and signal semantics, offline fixtures for missing/unmapped subjects and identity ambiguity, repeat-safe enrichment and provenance tests.

## 3E — Official journal/publisher sources

Add approved official evidence for requirements, APC and official guideline/template/submission links. Confirm page ownership, journal/article/language applicability, permitted use and retrieval dates. Publisher-wide guidance must not silently become journal-specific policy. Preserve conflicting aggregator evidence and resolve it explicitly through review.

Begin with reviewed links and permitted evidence supplied through staging. Store official template links without mirroring files. Controlled fetching is a later implementation within this phase, after its access and security gate: approved destinations, scheme and DNS/IP validation, private/internal/metadata address denial, redirect revalidation, timeout/size/parser limits, and respect for access restrictions. Phase 2 URL syntax normalization alone is not SSRF protection.

Exit gate: verified, attributable pilot requirements/fees/template links with explicit unknowns and conflict handling. Before enabling controlled fetching, test SSRF/redirect defenses and bounded parsing separately. Retrieved text remains untrusted data; it cannot instruct tools or verify itself.

## Decisions still open

Provider-specific access/rights/quotas; pilot coverage and review owners; raw/staged payload retention; real-provider pagination/checkpoints and mapping migration policy beyond the implemented local replay rules; identity correction/transfer handling; approved vocabularies; review capabilities/methods and fact-specific freshness windows; controlled-fetch operations and production deployment controls. Track their owners in [DECISIONS](../architecture/DECISIONS.md). None permits fabricated facts or automatic verification.

Phase 3 delivers the catalog ingestion foundation. Public discovery, recommendation, comparison, lightweight projects, journal-specific workspace and the contextual journal assistant follow the [MVP roadmap](../product/ROADMAP.md). Manuscript automation, collaboration, institutional tenancy, billing implementation, automatic submission and acceptance prediction remain outside MVP V1.
