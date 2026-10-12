# Fikarohana

Fikarohana is an API-first platform that helps researchers find suitable scientific journals and prepare for submission. It guides users from a research topic to a small, explainable shortlist, verified journal requirements, and a lightweight journal-specific research workspace with contextual journal assistance in MVP V1.

Foundation V1 is implemented: PostgreSQL/pgvector setup, Laravel `/api/v1`, Sanctum SPA/mobile authentication and a validated OpenAPI contract. Phase 2 journal data/provenance models and immutable revisions, plus Phase 3A controlled local ingestion, are implemented internally; Phase 3B bounded DOAJ retrieval, Phase 3C Crossref identity enrichment and Phase 3D OpenAlex taxonomy enrichment are implemented; live compatibility, other providers, public catalog endpoints and user-facing capabilities remain pending. Journal recommendations must come from stored, sourced journal records; match scores never predict acceptance.

## Repository

| Path | Responsibility | Current state |
| --- | --- | --- |
| `apps/api` | Laravel 13 canonical business API; Sanctum authentication | Foundation V1 API, Phase 2 catalog and Phase 3A ingestion framework with PostgreSQL tests |
| `apps/web` | Next.js and TypeScript presentation client | Starter application |
| `apps/mobile` | Expo and React Native presentation client | Starter application |
| `services/ai` | Internal Python/FastAPI capabilities when Python is justified | Empty service directories and metadata placeholder |
| `packages/contracts` | Clients generated from the canonical OpenAPI contract | OpenAPI 3.1 specification and generated TypeScript declarations |
| `docs` | Product, architecture, data, AI, security and development design | Initial design documentation |
| `infra` | Deployment configuration and operational scripts | Local development/test PostgreSQL Compose setup |

Target flow: web/mobile → Laravel `/api/v1` → PostgreSQL with pgvector, Redis, private object storage, and optional internal FastAPI. Clients never access the database or AI service directly.

The `/api/user` scaffold route was removed because no external consumer needs compatibility. API/environment examples and tests use PostgreSQL; vector is enabled by migration with a non-destructive extension rollback. Native database sessions/cache remain in this foundation; Redis, AI and object storage are deferred. The real developer `.env` was not modified. See [Foundation verification](docs/development/FOUNDATION-V1.md) for results and limitations.

## Documentation map

- Product: [requirements (CDC)](docs/product/CDC.md), [product behavior](docs/product/PRODUCT.md), [MVP boundary](docs/product/MVP.md), [roadmap](docs/product/ROADMAP.md).
- Architecture: [service boundaries](docs/architecture/ARCHITECTURE.md), [decision register](docs/architecture/DECISIONS.md).
- Data: [source policy](docs/data/DATA-SOURCES.md), [logical model](docs/data/DATA-MODEL.md), [journal schema](docs/data/JOURNAL-SCHEMA.md).
- AI: [recommendations](docs/ai/RECOMMENDATION-ENGINE.md), [journal RAG](docs/ai/RAG.md), [AI policy](docs/ai/AI-POLICY.md).
- Security: [controls](docs/security/SECURITY.md), [privacy](docs/security/PRIVACY.md).
- Development: [conventions](docs/development/CONVENTIONS.md), [testing](docs/development/TESTING.md), [implementation backlog](docs/development/TODO.md).

Implemented behavior: [API](docs/api/API.md), [authentication](docs/api/AUTHENTICATION.md), [OpenAPI workflow](docs/api/OPENAPI.md), [canonical specification](packages/contracts/openapi.yaml). Domain endpoints will extend this contract.

## First delivery

The PostgreSQL/versioned API/Sanctum/OpenAPI foundation is complete. Phase 2 journal identity/provenance schema is complete with synthetic test fixtures and internal publication primitives. Phase 3A is implemented with synthetic adapters, local staging, dry-run, explicit review and repeat-safe publication; see the [execution guide](docs/development/JOURNAL-INGESTION-V1.md). The [Phase 3 sequence](docs/development/JOURNAL-INGESTION-PLAN.md) continues with 3B DOAJ API/CSV → 3C Crossref identity → 3D OpenAlex subjects/discovery → 3E official requirements/APC/templates, with controlled official-site fetching later. Real-provider rights/review policy remain to validate; the DOAJ adapter is implemented with offline tests, but live mapping and a real seed catalog remain unverified; no public journal API exists. MVP V1 then delivers discovery → recommendation → comparison → journal selection → lightweight research project → journal-specific workspace → requirements/templates → contextual journal assistant. Intermediate finder-only deliveries do not complete V1.

Projects store title, abstract, scientific field, keywords, authors, journal shortlist, selected journal, status, created_at and updated_at. Workflow is `discovery` → `journal_selected` → `preparation`; workspace tabs are Overview, Requirements, Fees, Template and Assistant. The assistant answers selected-journal questions from verified data and authoritative retrieved sources, cites evidence and explicitly says when information could not be verified. It is not a generic writing chatbot.

Full DOCX/PDF analysis, manuscript uploads/versioning, automatic rewriting/formatting, full compliance scoring, reference manager, collaboration, institutional tenancy, billing implementation, automatic submission, acceptance prediction and full publication workflow automation remain outside MVP V1.

French and English are priority interface languages. Anonymous discovery is supported; saving searches/journals, creating/resuming projects and using the project assistant require an account. Pricing, quotas and the initial catalog need human validation; see the [decision register](docs/architecture/DECISIONS.md).

## Working locally

Use [AGENTS.md](AGENTS.md) and [CLAUDE.md](CLAUDE.md) before making changes. Runtime versions and commands are determined by existing manifests, not by this design document.

Existing root scripts are `npm run dev:web`, `npm run dev:mobile`, and `npm run build:web`. These assume dependencies have already been provisioned. npm workspaces with root package-lock.json is canonical; do not introduce Yarn/pnpm. The Laravel API can be started from `apps/api` with `php artisan serve` once its environment and dependencies are configured. There is no runnable AI application yet.

Start the local databases and validate contracts from the root:

```bash
docker compose -f infra/docker/compose.yml up -d --wait
npm run contracts:validate
npm run contracts:check
```

Configure `apps/api/.env` manually using the documented PostgreSQL defaults, preserving existing secrets; generate APP_KEY only for a new local environment. With a PostgreSQL-capable PHP runtime, run `php artisan migrate`, `php artisan serve` and `php artisan test` from `apps/api`. Do not overwrite an existing `.env`. See [database setup](infra/docker/README.md) for ports/credentials, stop commands and prerequisites. Host PHP now has pdo_pgsql and pgsql; current PostgreSQL tests run directly on the host. The initial Foundation verification used an existing PHP container, with no SQLite fallback.

Do not use scaffold setup scripts as a substitute for the PostgreSQL foundation plan. Secrets belong in local environment configuration or a deployment secret store. Do not commit credentials, private manuscripts, provider tokens or unlicensed source dumps.

Changes are made on `master` unless a branch is explicitly requested. No automatic commits. Check [TESTING.md](docs/development/TESTING.md) for layer-specific verification.

Journal schema, invariants, history/deletion policy and Phase 2 verification: [JOURNAL-DATA-MODEL-V1](docs/development/JOURNAL-DATA-MODEL-V1.md).

Phase 3B adds an explicitly selected, bounded DOAJ API v4 / official Journal CSV ingestion adapter through the existing Laravel staging/provenance framework. No public journal API or automatic verification/publication is added. Offline tests use synthetic current-schema fixtures; the one-record live probe failed before an HTTP response. See [DOAJ adapter V1](docs/development/DOAJ-ADAPTER-V1.md) for mappings, configuration, CLI and limits.

Phase 3C adds explicit bounded ISSN lookup for existing canonical/staged candidates through the same provenance/review pipeline. Crossref never bootstraps a catalog or establishes APC/submission rules. See [Crossref adapter V1](docs/development/CROSSREF-ADAPTER-V1.md).

Phase 3D adds bounded OpenAlex ISSN enrichment with explicit topic/subfield/field/domain Subject versions through the existing staging/review/publication pipeline. No editorial-rule or quality inference; see [OpenAlex adapter V1](docs/development/OPENALEX-ADAPTER-V1.md).
