# Fikarohana

Fikarohana is an API-first platform that helps researchers find suitable scientific journals and prepare for submission. It guides users from a research topic to a small, explainable shortlist, verified journal requirements, and eventually a journal-specific manuscript workspace.

This repository is in the formal design phase. The documents describe planned behavior, not released features. Journal recommendations must come from stored, sourced journal records; match scores never predict acceptance.

## Repository

| Path | Responsibility | Current state |
| --- | --- | --- |
| `apps/api` | Laravel 13 canonical business API; Sanctum authentication | Framework scaffold with authenticated user route |
| `apps/web` | Next.js and TypeScript presentation client | Starter application |
| `apps/mobile` | Expo and React Native presentation client | Starter application |
| `services/ai` | Internal Python/FastAPI capabilities when Python is justified | Empty service directories and metadata placeholder |
| `packages/contracts` | Clients generated from the canonical OpenAPI contract | Placeholder; no contract generation yet |
| `docs` | Product, architecture, data, AI, security and development design | Initial design documentation |
| `infra` | Deployment configuration and operational scripts | Directory scaffolding |

Target flow: web/mobile → Laravel `/api/v1` → PostgreSQL with pgvector, Redis, private object storage, and optional internal FastAPI. Clients never access the database or AI service directly.

The Laravel scaffold still uses the default `/api/user` route and SQLite/database-backed cache and queue defaults in `.env.example`. Migrating these to the target architecture is future foundation work. PostgreSQL is required for production functionality and database integration tests. No application changes were made during design.

## Documentation map

- Product: [requirements (CDC)](docs/product/CDC.md), [product behavior](docs/product/PRODUCT.md), [MVP boundary](docs/product/MVP.md), [roadmap](docs/product/ROADMAP.md).
- Architecture: [service boundaries](docs/architecture/ARCHITECTURE.md), [decision register](docs/architecture/DECISIONS.md).
- Data: [source policy](docs/data/DATA-SOURCES.md), [logical model](docs/data/DATA-MODEL.md), [journal schema](docs/data/JOURNAL-SCHEMA.md).
- AI: [recommendations](docs/ai/RECOMMENDATION-ENGINE.md), [journal RAG](docs/ai/RAG.md), [AI policy](docs/ai/AI-POLICY.md).
- Security: [controls](docs/security/SECURITY.md), [privacy](docs/security/PRIVACY.md).
- Development: [conventions](docs/development/CONVENTIONS.md), [testing](docs/development/TESTING.md), [implementation backlog](docs/development/TODO.md).

The existing `docs/api/API.md`, `AUTHENTICATION.md`, and `OPENAPI.md` remain placeholders. Their completion and a machine-readable OpenAPI contract are foundation tasks, before domain endpoints are implemented.

## First delivery

Build the foundation and journal data layer first: PostgreSQL, versioned API, Sanctum flows, contract validation, provenance, a reviewed seed dataset and an idempotent ingestion path. Then deliver discovery, configurable recommendations, journal profiles and basic comparison. Research projects, the contextual assistant and manuscript analysis follow separate gates.

French and English are priority interface languages. Anonymous discovery is supported; saving searches or journals requires an account. Pricing, quotas and the initial catalog need human validation; see the [decision register](docs/architecture/DECISIONS.md).

## Working locally

Use [AGENTS.md](AGENTS.md) and [CLAUDE.md](CLAUDE.md) before making changes. Runtime versions and commands are determined by existing manifests, not by this design document.

Existing root scripts are `npm run dev:web`, `npm run dev:mobile`, and `npm run build:web`. These assume dependencies have already been provisioned. The root manifest declares Yarn; package-manager standardization is unresolved, so do not create competing lockfiles. The Laravel API can be started from `apps/api` with `php artisan serve` once its environment and dependencies are configured. There is no runnable AI application yet.

Do not use scaffold setup scripts as a substitute for the PostgreSQL foundation plan. Secrets belong in local environment configuration or a deployment secret store. Do not commit credentials, private manuscripts, provider tokens or unlicensed source dumps.

Changes are made on `master` unless a branch is explicitly requested. No automatic commits. Check [TESTING.md](docs/development/TESTING.md) for layer-specific verification.
