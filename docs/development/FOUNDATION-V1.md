# Foundation V1 — implementation and verification

Completed 2026-10-11. This report distinguishes the implemented foundation from the complete MVP V1; journal discovery/recommendation/comparison, lightweight projects/workspace and contextual assistant still belong to MVP but are not implemented here.

## Implementation

Laravel 13 now serves seven Foundation business endpoints under `/api/v1`: GET health, POST auth/register, POST auth/login, POST auth/logout, POST auth/tokens, DELETE auth/tokens/current and GET me. `/sanctum/csrf-cookie` is the framework CSRF exception; `/up` is process liveness. The unconsumed `/api/user` and broken welcome route were removed. No journal model, vector business columns, frontend/mobile UI, Redis, FastAPI, S3/R2 or billing was added. AGENTS.md, CLAUDE.md, the real `.env`, Composer lock and existing web/mobile manifests are unchanged. No branch or commit was created.

Web uses first-party Sanctum sessions, HttpOnly cookies and CSRF; successful login regenerates session, logout invalidates session and regenerates CSRF. Registration returns 201 without login/token issuance. Mobile uses device-named opaque Bearer tokens with configurable expiry (default 43200 minutes / 30 days), shown once; revocation deletes only the current token. Session-only token revocation and token-only browser logout are rejected. Password hashing remains Laravel bcrypt: registration minimum 12 characters, reusable maximum 72-byte rule across credential endpoints, matching confirmation and null-byte rejection. Missing mobile accounts use a precomputed cost-12 dummy hash through Hash::check rather than skipping verification. No micro-timing equality is claimed; work-factor changes require reviewing dummy/stored hash costs. Email normalization is trim/lowercase. No JWT. Form Requests bound and validate input. Register/login/token issuance have IP/email-hash limits.

Readiness checks PostgreSQL and installed vector, returning safe 503 without internal details on failure. It bypasses SPA session startup so database-session failure cannot prevent readiness reporting. Global server-generated request IDs appear in headers and normalized errors. API responses use no-store; unexpected API/Sanctum reporting omits exception messages, SQL and traces. Environment-driven CORS validates an explicit origin; production requires first-party HTTPS, Secure HttpOnly cookies and SameSite=Lax. Exact behavior is in [API](../api/API.md) and [AUTHENTICATION](../api/AUTHENTICATION.md).

## Database and startup

`infra/docker/compose.yml` has separate development and test PostgreSQL 17 instances using pgvector 0.8.2. Development is a persistent named volume on localhost:5433; test is ephemeral tmpfs on localhost:5434. Credentials are explicitly local-only. Image tags are pinned for this tested baseline; production image patch/security selection remains unverified. The migration enables `CREATE EXTENSION IF NOT EXISTS vector`; down deliberately preserves it. No embedding columns exist.

```bash
# Repository root
docker compose -f infra/docker/compose.yml up -d --wait
npm run contracts:validate
npm run contracts:generate
npm run contracts:check
# Provision existing dependencies if needed, using npm ci / composer install.
# Configure apps/api/.env manually; preserve existing secrets.
# For a newly configured local environment only, generate APP_KEY.
cd apps/api
php artisan migrate
php artisan serve
# Separate terminal in apps/api
php artisan test
vendor/bin/pint --test
# Stop databases from the repository root
docker compose -f infra/docker/compose.yml stop
```

PHP needs pdo_pgsql. At initial verification this host PHP lacked that driver, so tests were run in the existing `sail-8.4/app:latest` image, with its PHP 8.4.23/pdo_pgsql and host networking. No host PHP packages were installed; no SQLite fallback occurred. Exact test invocation from this workspace:

```bash
docker run --rm --network host --user 1000:1000 --entrypoint php   -v /home/reinia/projects/fikarohana:/workspace   -w /workspace/apps/api sail-8.4/app:latest artisan test
```

The same invocation with approved development DB environment overrides ran migrations on the new development instance. Tests force PostgreSQL/test database and refuse non-*_test or DB_URL configurations before migration. Runtime containers are verification tools, not new production application services.

## Contract and dependency changes

OpenAPI 3.1.0 is canonical at `packages/contracts/openapi.yaml`. Reusable errors, users, request bodies, response headers, cookie/CSRF and Bearer methods cover all implemented endpoints plus the CSRF framework route. Generated declarations are `packages/contracts/generated/api.d.ts`; validate/generate/check scripts use npm workspaces/root lockfile.

Added direct dev-only tooling: `openapi-typescript@7.13.0` for TypeScript generation and `@redocly/openapi-core@1.34.6` for schema linting. Core is also a generator transitive dependency, explicitly declared because the script imports it. No Laravel dependency/version change. The contracts workspace contains metadata/generated types only. npm deduplicated existing entries; comparison of name/version sets confirms every previously locked dependency version remains present. Web/mobile manifests remain unchanged. Composer setup scripts no longer invoke nonexistent Laravel frontend builds or create SQLite files; no Composer dependency changes occurred.

Audit before and after installation: 32 advisories, 11 moderate and 21 high; no new advisory names or severity-count changes. These are existing Expo/React Native/Next lint dependency chains, not added contract tooling. No forced fix, peer bypass or Expo downgrade was run.

| Advisory group | Direct/transitive | Runtime/tooling | Foundation exposure / compatible fix |
| --- | --- | --- | --- |
| New generator/validator | Direct dev + transitive dependencies | Build tooling | No added advisory reported; validates trusted local contract, never deployed in API runtime |
| eslint-config-next/plugin, fast-glob, micromatch/braces | Direct lint configuration and transitive utilities | Development/build | Not exercised by Laravel request handling; actual client/build input exploitation not audited here; npm's proposed Next downgrade is not an approved compatible fix |
| Expo CLI/config/metro/certificates/forge/xcode/uuid chains | Direct Expo umbrella plus transitive tooling | Primarily development/build | Not Foundation PHP runtime; no untrusted foundation content reaches it; SDK-compatible upstream resolution not validated; proposed Expo 44 downgrade is unacceptable |
| React Native/reanimated/worklets and Expo router/splash/query utilities | Direct client packages and transitive libraries | Client runtime and build | No client UI added, but existing app/build exposure needs a separate targeted review; compatible Expo-matrix fix not established |

This is scope triage, not a finding that existing clients are safe. Production container scanning, client advisory exploitability and compatible SDK fixes remain unverified; record them separately rather than running broad dependency fixes.

## Initial executed results (before security corrections)

| Check | Result |
| --- | --- |
| Docker Compose up --wait / config --quiet | Passed; both databases healthy, localhost-only ports |
| Development migrations | All five migrations passed; vector confirmed in pg_extension |
| Laravel tests in PostgreSQL-capable container | 20 passed, 155 assertions, 3.32 seconds on final run |
| Pint --test | Passed after focused formatting of changed PHP files |
| composer validate --no-check-publish | composer.json valid |
| npm run contracts:validate | OpenAPI 3.1 contract valid, no warnings/errors |
| npm run contracts:generate | Generated TypeScript declarations successfully |
| npm run contracts:check | Exact reproducibility/current-output check passed |
| npx --no-install tsc --noEmit --skipLibCheck packages/contracts/generated/api.d.ts | Exit 0 |
| artisan route:list --path=api | Exactly seven approved Foundation routes |
| Real multi-request HTTP smoke | Readiness, CSRF cookies, rejected missing CSRF, register, login, authenticated me, logout and unauthenticated me passed using PostgreSQL-backed sessions |
| git diff --check | Passed |

Early checks exposed and resolved: missing welcome view, default guest redirect without JSON Accept, and an incorrect CORS assertion (single-origin library returns its configured origin rather than omitting the header). The first external-env HTTP server attempt returned 503 because artisan serve reload discarded overrides in favor of the preserved developer environment; rerunning with --no-reload verified the isolated configuration. These were not SQLite substitutions. Final relevant checks passed. The transient HTTP server stopped and its synthetic user fixture was deleted; development/test database containers remain available.

Host PHP now has both pdo_pgsql and pgsql, and the security correction suite runs directly on the host; the container results above remain the original verification record. No frontend/mobile UI build/Expo Doctor was run because client manifests/SDK versions were unchanged. Production HTTPS/domain deployment, backups/restoration, CI wiring, image scanning and token-cleanup scheduling remain unverified/unfinished. Existing real `.env` must be manually configured by its owner for host local startup; it was not overwritten.

## Security correction verification

2026-10-11 — All required Foundation corrections implemented after the read-only review. Configurable mobile token duration is now explicitly approved (D-16), default 43200 minutes. The value is validated/cast in config/sanctum.php, and the controller reads config rather than env. Web sessions remain independent. Missing-account checks use a fixed valid bcrypt hash, not per-request hashing or a database dummy user. Registration, web login and token issuance share the reusable 72-byte/null-byte rule; registration retains its 12-character minimum and matching confirmation.

OpenAPI now describes Origin OR Referer, required CSRF evidence with preferred X-XSRF-TOKEN and framework alternatives, non-empty strings, the server-enforced bcrypt byte bound, configurable explicit expires_at and framework-managed CSRF-route caching. Types are generated only from YAML. Production HTTPS/Secure-cookie/trusted-origin/session-domain/stateful-domain checks are documented deployment gates; local HTTP remains supported. CI is explicitly planned and no pipeline was added.

Correction verification ran directly with host PHP, with both pdo_pgsql and pgsql confirmed by `php -m`. `docker compose -f infra/docker/compose.yml up -d --wait` confirmed healthy separate PostgreSQL instances. The suite uses the forced PostgreSQL `fikarohana_test` database on 127.0.0.1:5434 with RefreshDatabase, array sessions/cache and a test-only APP_KEY. No SQLite fallback or development database migration occurred in this correction run. Original HTTP smoke results above remain historical; that smoke was not rerun for this correction.

| Check | Correction result |
| --- | --- |
| Host `php artisan test` in apps/api | 43 passed, 309 assertions; zero failures/skips; 9.566 seconds |
| `vendor/bin/pint --test` | Passed |
| `composer validate --no-check-publish` | Passed |
| `composer audit` | No security vulnerability advisories found |
| OpenAPI validate / generate / check | Passed; declarations reproducible |
| Generated declarations TypeScript check | Passed with tsc --noEmit --skipLibCheck |
| git diff --check / local Markdown links | Passed; 74 local links checked, zero broken |
| Scope | No journal/publisher/ingestion/recommendation/project/workspace/assistant, Redis, FastAPI, S3/R2, billing or frontend auth UI added |

Regression tests verify password checking on both mobile account paths without asserting micro-timing equality; ASCII and multibyte limits on all credential endpoints; minimum/confirmation/null-byte behavior; expiry configuration validation, issuance overrides and frozen-clock expiry; existing-token preservation after web logout; precise stored token hash/current-token deletion; session/TransientToken and simultaneous-session-plus-Bearer rejection; Referer-only sessions; and case/trim email limits across IPs. An early test run exposed mock forwarding and database timestamp precision problems; both tests were corrected before the successful complete suite.

Remaining release work: production cookie/domain/HTTPS verification, CI, container/security scanning, backups/restoration and expired-token cleanup. If bcrypt cost changes from the default 12, review the precomputed dummy hash cost together with stored-password costs. Existing client advisory triage above remains separate; no dependency change or forced audit fix was made during correction. AGENTS.md, CLAUDE.md, real .env, package manifests/lockfiles and client code remain untouched by these corrections. No branch or commit was created.

## Recommended next phase

Implement journal identity, source records, field assertions/review and immutable published revisions on PostgreSQL with permitted fixtures. Then validate pilot/source rights and build idempotent ingestion. Do not jump to recommendations/projects/assistant before the data/provenance gates. Complete MVP V1 still requires the lightweight workspace and journal-grounded assistant in their ordered phases.

## Exact changed files

- `README.md`
- `apps/api/.env.example`
- `apps/api/app/Http/Controllers/Api/V1/AuthController.php`
- `apps/api/app/Http/Controllers/Api/V1/HealthController.php`
- `apps/api/app/Http/Middleware/RequestId.php`
- `apps/api/app/Http/Middleware/RequireSpaSession.php`
- `apps/api/app/Http/Requests/Auth/LoginRequest.php`
- `apps/api/app/Http/Requests/Auth/RegisterRequest.php`
- `apps/api/app/Http/Requests/Auth/TokenRequest.php`
- `apps/api/app/Http/Resources/UserResource.php`
- `apps/api/app/Models/User.php`
- `apps/api/app/Rules/BcryptPassword.php`
- `apps/api/app/Providers/AppServiceProvider.php`
- `apps/api/bootstrap/app.php`
- `apps/api/composer.json`
- `apps/api/config/cors.php`
- `apps/api/config/database.php`
- `apps/api/config/session.php`
- `apps/api/config/sanctum.php`
- `apps/api/database/migrations/2026_10_11_160000_enable_vector_extension.php`
- `apps/api/phpunit.xml`
- `apps/api/routes/api.php`
- `apps/api/routes/web.php`
- `apps/api/tests/Feature/ExampleTest.php`
- `apps/api/tests/Feature/FoundationTest.php`
- `apps/api/tests/TestCase.php`
- `docs/api/API.md`
- `docs/api/AUTHENTICATION.md`
- `docs/api/OPENAPI.md`
- `docs/architecture/ARCHITECTURE.md`
- `docs/architecture/DECISIONS.md`
- `docs/development/CONVENTIONS.md`
- `docs/development/FOUNDATION-V1.md`
- `docs/development/TESTING.md`
- `docs/development/TODO.md`
- `docs/security/SECURITY.md`
- `infra/docker/README.md`
- `infra/docker/compose.yml`
- `package-lock.json`
- `package.json`
- `packages/contracts/README.md`
- `packages/contracts/generated/api.d.ts`
- `packages/contracts/openapi.yaml`
- `packages/contracts/package.json`
- `packages/contracts/scripts/contracts.mjs`
