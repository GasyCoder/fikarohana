# Foundation V1 API

Status: implemented foundation. Laravel 13 is the canonical business API. Phase 4A public journal discovery/detail and deterministic metadata recommendation APIs are implemented; project/workspace/assistant APIs remain unimplemented. See [Phase 4A](../development/JOURNAL-DISCOVERY-API-V1.md). Phase 2 implements only the internal journal/provenance schema and revision primitives; see [Phase 2](../development/JOURNAL-DATA-MODEL-V1.md). The approved complete MVP still includes them.

Phase 3A adds trusted-operator Artisan ingestion/review/publication commands, without changing routes or OpenAPI. See [Phase 3A](../development/JOURNAL-INGESTION-V1.md); Phase 3B now adds explicit bounded DOAJ retrieval through the same command; public catalog discovery is now documented in Phase 4A. See [DOAJ adapter](../development/DOAJ-ADAPTER-V1.md).

## Routes

| Method and path | Access | Success |
| --- | --- | --- |
| `GET /api/v1/health` | Public | 200 when PostgreSQL connects and vector is installed; safe 503 otherwise |
| `POST /api/v1/auth/register` | Public; CSRF for stateful SPA | 201 user; no session login or token issued |
| `POST /api/v1/auth/login` | Stateful first-party SPA with CSRF | 200 user; regenerated web session |
| `POST /api/v1/auth/logout` | Authenticated web session with CSRF | 200 message; session invalidated and CSRF regenerated |
| `POST /api/v1/auth/tokens` | Email/password plus device name | 201 token, type, expiry and user; mobile flow |
| `DELETE /api/v1/auth/tokens/current` | Current Sanctum personal access token | 200 message; only current token deleted |
| `GET /api/v1/me` | `auth:sanctum`: web session or Bearer | 200 authenticated user |

Phase 4A anonymous routes, shared rate limit 60/minute per IP:

- `GET /api/v1/journals` — bounded published catalogue and filters.
- `GET /api/v1/journals/{journal}` — canonical detail and provenance.
- `POST /api/v1/journal-recommendations` — request-scoped deterministic relevance; score is not acceptance probability.

`GET /sanctum/csrf-cookie` is the documented framework exception outside `/api/v1`, returning 204 and SPA cookies. `/up` is process liveness, not database readiness. The scaffold `/api/user` and broken welcome-page route were removed; there are no external consumers requiring compatibility. Root `/` has no application page.

## JSON and correlation

Single resources use `{ "data": { ... } }`. Users expose only ID, name, normalized email, verification date and creation/update dates. Secrets/passwords and token lists are never returned. Foundation API responses use `Cache-Control: no-store`.

```json
{
  "error": {
    "code": "validation_error",
    "message": "The given data was invalid.",
    "fields": {"email": ["The email field is required."]},
    "request_id": "00000000-0000-4000-8000-000000000000"
  }
}
```

Every HTTP response has server-generated `X-Request-ID`; incoming IDs are deliberately ignored. API errors include that same ID. Empty fields are `{}`, not `[]`. The ID is exposed through CORS. Client apps should display/support this ID without logging credentials or private research input.

| Status | Error code | Meaning |
| --- | --- | --- |
| 400 | `bad_request` | Web login/logout lacks a first-party session context |
| 401 | `unauthenticated` | Authentication missing, invalid or expired; generic credential message |
| 403 | `forbidden` | Permission/authentication method not allowed, including cookie-only token revocation |
| 404 | `not_found` | Route/resource absent |
| 405 | `method_not_allowed` | Wrong method |
| 419 | `csrf_mismatch` | SPA CSRF invalid/missing |
| 422 | `validation_error` | Field validation errors |
| 429 | `rate_limited` | Limit exceeded; `Retry-After` preserved |
| 500 | `server_error` | Unexpected failure, no stack/exception/SQL detail even with debug on |
| 503 | `not_ready` | Readiness dependency unavailable/misconfigured |

Validation uses Form Requests. Email strings are trimmed and lowercased without provider-specific alias rewriting. Registration passwords require at least 12 characters with matching confirmation. All password inputs (registration, web login and mobile token issuance) are limited to 72 UTF-8 bytes using a reusable Laravel rule; null bytes are rejected and values are never truncated. Login/token passwords must be non-empty. JSON Schema character lengths do not enforce the byte limit; OpenAPI describes the server constraint. Duplicate normalized email returns 422, including concurrent unique-constraint collisions. Login/token failures use the same generic 401 contract. Mobile issuance always calls Laravel Hash::check, using a precomputed bcrypt cost-12 dummy hash for missing users and never creating a dummy database account or a web session. This mitigates the skipped-hashing timing distinction; it does not guarantee identical micro-timings.

Register limit: 5/minute per IP and normalized-email hash. Login and token issuance: 10/minute each per IP and normalized-email hash. These are initial application defaults, not subscription quotas. Native database cache backs limits locally; Redis is deferred. Health responses never expose hostnames, database names, credentials or exception details.

API exception logging stores a fixed event, exception class and request ID, suppressing default message/trace logging for API/Sanctum requests. Expected errors use Laravel's report suppression. No request-body logging is added. Future logging/instrumentation must preserve these restrictions.

The canonical machine-readable contract is [openapi.yaml](../../packages/contracts/openapi.yaml); [AUTHENTICATION](AUTHENTICATION.md) describes cookie/token usage. No frontend/mobile UI was implemented.

Mobile expiry is configured through `SANCTUM_MOBILE_TOKEN_EXPIRATION` (default 43200 minutes / 30 days), read via Laravel config and persisted as explicit `expires_at`. Web session lifetime is independent. See [AUTHENTICATION](AUTHENTICATION.md) for configuration and production gates.
