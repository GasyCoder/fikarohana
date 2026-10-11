# Fikarohana — Agent Instructions

## 1. Project identity

Fikarohana is an API-first SaaS platform designed to help researchers discover suitable scientific journals, understand publication requirements, prepare manuscripts, and progressively move toward submission readiness.

The product is not a simple chatbot and not merely an AI article generator.

Core journey:

Research topic
→ Journal discovery
→ Journal recommendation
→ Journal comparison
→ Journal selection
→ Requirements and templates
→ Research project workspace
→ Manuscript preparation
→ Compliance checking
→ Submission readiness

## 2. Architecture

Fikarohana is a monorepo.

- apps/api: Laravel 13 central business API
- apps/web: Next.js + TypeScript web application
- apps/mobile: Expo + React Native mobile application
- services/ai: Python/FastAPI scientific and AI services
- packages/contracts: shared OpenAPI-generated contracts
- docs: product and technical specifications
- infra: deployment and infrastructure configuration

The platform is API-first.

Canonical API prefix:

/api/v1

Web, mobile and future clients must consume the same central API.

## 3. Architectural rules

Laravel is the canonical business layer.

Do not place canonical business rules only in Next.js or Expo.

Next.js and Expo are presentation clients.

FastAPI is reserved for tasks that genuinely benefit from Python, such as:

- embeddings
- semantic search
- NLP
- scientific document extraction
- RAG
- recommendation computation when appropriate
- manuscript analysis

Do not duplicate Laravel business logic inside FastAPI.

Frontend applications must never access PostgreSQL directly.

Expected flow:

Next.js / Expo
→ Laravel API
→ PostgreSQL / Redis / Object Storage / FastAPI

## 4. Database

Target database:

PostgreSQL + pgvector

Do not build production functionality around SQLite.

Use PostgreSQL for:

- relational data
- full-text search where appropriate
- vector embeddings with pgvector
- journal metadata
- projects
- recommendations
- manuscript metadata
- audit data

## 5. Journal data

Journal information must come from verifiable data sources.

Expected sources may include:

- DOAJ
- OpenAlex
- Crossref
- official journal websites
- official publisher websites
- other validated academic sources documented in DATA-SOURCES.md

AI models are never the source of truth for:

- APC
- publication fees
- accepted languages
- indexing
- author guidelines
- submission requirements
- templates
- publication timelines

Important journal information must store provenance whenever possible:

- source
- source URL
- retrieved_at
- verified_at

Never fabricate missing journal metadata.

## 6. Recommendation engine

The LLM must not directly invent or select journals from general model knowledge.

Journal recommendation must operate on journals stored in Fikarohana's verified data layer.

Expected pipeline:

User research input
→ preprocessing
→ candidate retrieval
→ semantic matching
→ hard filters
→ ranking
→ recommendation results
→ optional LLM explanation

A recommendation score represents relevance/matching.

It must NEVER be presented as a probability of article acceptance.

## 7. AI principles

AI assists researchers.

AI must not misrepresent generated content as verified scientific fact.

AI-generated explanations must distinguish:

- verified source information
- derived calculations
- AI interpretation

Use RAG when answering journal-specific questions whenever authoritative source documents are available.

Never silently invent missing publication rules.

## 8. API

All new public application endpoints belong under:

/api/v1

API design must support:

- Web
- Mobile
- future institutional clients
- future integrations

Prefer stable resource-oriented endpoints.

Use OpenAPI as the canonical machine-readable API contract.

Avoid breaking existing API contracts without an explicit documented decision.

## 9. Authentication

Laravel Sanctum is the current authentication foundation.

Authentication must support web and mobile clients.

Never store secrets in source control.

Never expose tokens in logs.

## 10. Security and privacy

Fikarohana may store unpublished research.

Treat manuscripts and research projects as confidential by default.

Requirements:

- private-by-default files
- authorization checks
- tenant/user isolation
- secure temporary file URLs
- HTTPS in production
- auditability for sensitive operations
- explicit deletion capabilities
- no public manuscript URLs by default

## 11. Dependency security

Never automatically run:

- npm audit fix --force
- npm install --force
- npm install --legacy-peer-deps

without explicit human approval.

Before changing dependencies, classify advisories as:

1. direct or transitive
2. runtime or development/build tooling
3. actually exploitable in Fikarohana
4. compatible upstream fix available or unavailable

For Expo:

- preserve the Expo SDK compatibility matrix
- `npx expo-doctor` must pass
- `npx expo install --check` must pass

Do not downgrade Expo to satisfy npm audit.

## 12. Development discipline

Before implementing a feature:

1. read AGENTS.md
2. read relevant files under docs/
3. inspect existing code
4. identify affected components
5. implement the smallest coherent solution
6. add or update tests
7. run relevant checks

Do not perform broad rewrites unrelated to the current task.

Do not introduce a new framework or infrastructure dependency without a documented reason.

## 13. Git

Primary branch:

master

The project currently uses a single main development branch.

Do not create branches unless explicitly requested.

Do not commit automatically unless explicitly instructed.

Never rewrite Git history unless explicitly requested.

## 14. Testing

Changes must be tested at the layer they affect.

Laravel:
- PHPUnit

Next.js:
- lint
- type checking
- tests when introduced
- production build

Expo:
- TypeScript checks
- Expo Doctor when dependencies change

Python:
- pytest

API contract:
- OpenAPI validation

## 15. Documentation

Relevant architectural or product decisions must be reflected in docs/.

Important documents include:

- docs/product/CDC.md
- docs/product/PRODUCT.md
- docs/product/MVP.md
- docs/product/ROADMAP.md
- docs/architecture/ARCHITECTURE.md
- docs/architecture/DECISIONS.md
- docs/data/DATA-SOURCES.md
- docs/data/DATA-MODEL.md
- docs/data/JOURNAL-SCHEMA.md
- docs/ai/RECOMMENDATION-ENGINE.md
- docs/ai/RAG.md
- docs/security/SECURITY.md

Documentation and implementation must not knowingly contradict each other.

## 16. Current development priority

Do not attempt to build the entire final platform at once.

Priority:

1. foundation
2. journal data model
3. journal ingestion
4. journal discovery
5. recommendation engine V1
6. journal profiles
7. comparison
8. research projects
9. contextual journal assistant
10. manuscript analysis
11. advanced publication workflow
