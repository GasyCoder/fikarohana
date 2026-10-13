# Privacy design

Status: initial design, not a final legal policy or compliance certification. Jurisdiction, controller identity, residency, legal bases and retention deadlines require human/legal validation before production.

## Data inventory and minimization

| Data | Purpose | Default handling |
| --- | --- | --- |
| Anonymous topic/title/abstract/preferences | Compute discovery | Transient only; no permanent personal history or raw-content logs |
| Authenticated transient queries | Compute discovery | Not automatically saved; same content minimization |
| Account identity and authentication metadata | Sign-in, security and ownership | Minimum necessary fields; restricted access |
| Explicitly saved searches/journals | Resume personal discovery | Owner-only; users can delete |
| Public journal metadata/source artifacts | Sourced catalog and audit | Retain under source rights/approved policy; distinguish from private research |
| MVP V1 project metadata and authors | Lightweight journal-specific preparation | Confidential owner scope; explicit creation/edit/deletion; descriptive authors are not collaborators |
| MVP V1 journal questions/answers | Contextual assistance | Transient by default; no raw-content telemetry or automatically attached abstract/authors |
| Future manuscript files and analyses | Manuscript processing/checking after V1 | Confidential project scope; originals/derivatives tracked; explicit retention/deletion |
| Security/operational events | Abuse detection, audit, reliability | Sanitized IDs/metrics; restricted and time-bounded |

Even a title or abstract can expose unpublished research. Do not put it in URLs, third-party analytics, shared public caches, crash payloads or routine support exports. Basic public catalog access does not imply permission to collect a research profile.

## User control

Explain what is saved, what is sent to AI/providers and why. Anonymous discovery does not create a server-side personal history. Saving is explicit and requires authentication. MVP V1 project/assistant flows disclose processing and retention before collection; file-processing disclosure is added before later uploads.

Provide deletion for saved records and MVP V1 projects/author metadata, clear transient assistant context, and support later file deletion; provide account deletion and access/export of owned data according to the validated policy. Deleting a bookmark does not delete public journal facts. Retain only minimized security/legal exceptions with a documented reason, access restriction and deadline.

No training on private research by default. Any later research-data donation/training program needs separate opt-in; ordinary account use or accepting generic terms must not be treated as that opt-in.

## External processing

Before sending research text to an external embedding/LLM/parser service, validate contractual retention, training use, security, regions/subprocessors, deletion and transfer/legal requirements. Document the selected provider and user-facing basis/choice. If not validated, disable external processing or use a reviewed local computation path.

Send minimal snippets instead of whole manuscripts whenever possible. Avoid transmitting personal author metadata unless necessary. Provider failure never permits switching to an unapproved service. Public source retrieval must not include private research input in provider query URLs.

## Retention and deletion design

No numeric deadlines are approved yet. Before production, choose and publish a retention matrix for accounts, saved content, private projects/authors and transient assistant questions, anonymous transient processing, logs/audits, source artifacts, future files/derivatives, conversation history, external providers and backups. Record lifecycle triggers, purge interval, maximum completion time and exceptions.

Deletion must cover database rows or retained tombstones, objects, previews, parsed text, embeddings, cached outputs and external copies where applicable. Track a deletion job until completed/retried; soft deletion alone is insufficient. Backup copies expire under a documented schedule, and restoration must replay deletion records so removed content does not become active again. Signed URL maximum lifetimes limit the period of previously issued access.

Immutable public provenance is subject to source rights and retention policy; do not use “immutable” to justify keeping private research forever. Anonymous operational run metadata is minimized and time-bounded, never a hidden saved-search store.

## Analytics, support and incidents

Prefer aggregate counters for query success, empty-result causes and catalog coverage. Do not capture research text to improve recommendations without separate informed permission. Optional nonessential analytics choices and applicable legal basis require review; telemetry vendors are external recipients too.

Support access to confidential content requires a documented authorized workflow, least privilege and an audit trail. Use permitted/synthetic fixtures for debugging; private data must not be copied into issues or developer machines by default.

Maintain an incident process with privacy/security contacts, impact assessment and notification rules appropriate to the chosen jurisdiction. Production is blocked on unresolved privacy controls, not on selecting an arbitrary jurisdiction in documentation. See [DECISIONS](../architecture/DECISIONS.md) for the validation owners.

## Official-page evidence (Phase 3E-A)

Explicitly approved public unauthenticated pages may be retained only as bounded restricted internal verification evidence, under reviewed rights. Bodies may contain incidental public page markup/volatile tokens; do not log/render/index them publicly or treat them as authentication. No cookies, arbitrary response headers, private research, signed/query URLs, JS execution or linked resources. Default model serialization hides raw snapshots.

Registry approval/check metadata records non-sensitive operator references and operational times, not researcher activity. Dry-run retains no diagnostics/content. Official content is not presumed CC0; source-specific retention, takedown/purge, backup expiry and redistribution need legal/operations review before production. Future large artifacts use private object storage only after approved lifecycle design. See [official-source policy](../development/OFFICIAL-SOURCES-V1.md).

## Phase 3E-B minimum extraction evidence

Keep only short complete supporting units (at most 1 KiB), snapshot reference, locator and extraction rule/version on internal assertions. Raw official pages retain the Phase 3E-A rights/access restrictions. CLI summaries omit snippets/page bodies and display normalized proposed facts only. No private research or external AI processing. Future public presentation, source-specific retention/purge and permitted excerpts still need reviewed policy; extraction grants no redistribution license. See [evidence minimization](../development/EDITORIAL-EXTRACTION-V1.md).
