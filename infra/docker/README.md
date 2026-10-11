# Foundation PostgreSQL development setup

Development only. The two pgvector-enabled PostgreSQL 17 containers use pinned `pgvector/pgvector:0.8.2-pg17`. This is a local tested baseline, not a production image/security recommendation. Verify/update container patches under the dependency policy before production.

```bash
docker compose -f infra/docker/compose.yml up -d --wait
docker compose -f infra/docker/compose.yml ps
docker compose -f infra/docker/compose.yml stop
# Remove containers/network, keeping development data:
docker compose -f infra/docker/compose.yml down
```

Development: `127.0.0.1:5433`, database `fikarohana`, user `fikarohana_dev`, password `fikarohana_dev_only`. Data persists in the named volume. Test: `127.0.0.1:5434`, database `fikarohana_test`, user `fikarohana_test`, password `fikarohana_test_only`; tmpfs is ephemeral. The separate test instance cannot truncate development data. Use `FIKAROHANA_DB_PORT` / `FIKAROHANA_TEST_DB_PORT` to resolve local conflicts and adjust Laravel/test variables accordingly. Bindings are localhost-only; never reuse these credentials/configuration for production. Health checks use pg_isready; API readiness also requires vector to be enabled by migrations.

Prerequisites: Docker Compose, PHP >= 8.3 compatible with the locked dependencies, PostgreSQL PDO extension and Composer dependencies. Do not use SQLite when PostgreSQL or PDO is unavailable.

Configure `apps/api/.env` yourself from the example only if it does not already exist; preserve existing secrets, generate your local APP_KEY, then:

```bash
cd apps/api
php artisan migrate
php artisan serve
# In another terminal, same directory:
php artisan test
vendor/bin/pint --test
```

Tests select PostgreSQL and a forced `_test` database; test connection host/port/credentials are configurable through environment. The base test class refuses a non-PostgreSQL connection, a database without `_test` suffix or DB_URL before migration/truncation. Clear stale config caches before tests. Production/development credentials must never be used for tests.

The extension migration executes `CREATE EXTENSION IF NOT EXISTS vector`; rollback intentionally does not drop a shared extension. No vector business columns or embeddings exist. Application deployment needs a role permitted to enable the extension or a DBA-managed installation. Image provenance: [official pgvector repository](https://github.com/pgvector/pgvector).

A host missing pdo_pgsql can run checks using a PostgreSQL-capable PHP container; see [Foundation verification](../../docs/development/FOUNDATION-V1.md) for the exact environment used. Such a runtime is not a new production component.
