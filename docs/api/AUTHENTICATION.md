# Foundation V1 authentication

Implemented with Laravel Sanctum and the existing web session guard. No JWT, OAuth/social login, MFA, SSO, password-reset endpoint or frontend/mobile sign-in screen is introduced.

## Account creation

`POST /api/v1/auth/register` accepts name, email, password and password_confirmation. Registration password minimum is 12 characters; registration/login/token passwords are limited to 72 UTF-8 bytes, reject null bytes and are never truncated. Confirmation must exactly match. Hashing uses Laravel's hashed model cast. Emails are trimmed/lowercased before validation/storage. Success is 201 wrapped user data. Registration does not sign in and does not issue a mobile token. Stateful browser calls need CSRF; a non-browser registration is stateless.

## Web: first-party SPA/session

Use the same site for API and frontend in production (for example sibling subdomains under one domain). Local defaults are API `http://localhost:8000` and frontend `http://localhost:3000`; do not mix localhost and 127.0.0.1 between clients. Cross-site third-party cookie authentication is not supported by this configuration.

1. Call `GET /sanctum/csrf-cookie` from the approved frontend with credentials included.
2. Read and URL-decode the readable `XSRF-TOKEN` cookie; send it as `X-XSRF-TOKEN` on writes, with credentials and `Accept: application/json`. Sanctum establishes first-party context through Origin or Referer.
3. POST email/password to `/api/v1/auth/login`. Success regenerates session ID and returns wrapped user data. No access token is returned.
4. GET `/api/v1/me` with cookies. `auth:sanctum` checks the session.
5. POST `/api/v1/auth/logout` with CSRF. It logs out the web guard, invalidates session and regenerates CSRF. Bootstrap CSRF again before a later login if needed.

Login/logout without stateful session context return 400; missing/invalid credentials/authentication return 401; CSRF failure returns 419. A mobile Bearer token does not log out a browser session. Web logout does not delete mobile tokens.

| Variable | Development example / production requirement |
| --- | --- |
| `FRONTEND_URL` | `http://localhost:3000`; one explicit origin, no wildcard/path/query/userinfo |
| `SANCTUM_STATEFUL_DOMAINS` | `localhost:3000`; comma-separated hosts with ports, no scheme; list only owned first-party clients |
| `SESSION_DOMAIN` | `null` locally; parent domain such as `.your-domain.example` for sibling production subdomains |
| `SESSION_SECURE_COOKIE` | false only for local HTTP; true in production HTTPS (configuration default is true) |
| `SESSION_HTTP_ONLY` | true; Sanctum also enforces HttpOnly session cookies |
| `SESSION_SAME_SITE` | lax; installed Sanctum's stateful middleware enforces Lax |
| `SESSION_DRIVER` | database; PostgreSQL sessions table |
| `SESSION_COOKIE` | Optional override; default from APP_NAME is `fikarohana-session` for the example environment |

CORS credentials are enabled only with the explicit configured origin. Invalid/wildcard origin configuration fails startup. With one allowed origin, the CORS library may return that fixed origin on another Origin's request; the browser refuses credentialed access because they differ. CORS does not authenticate a request. CSRF and stateful-domain configuration protect browser writes independently.

## Mobile: personal access tokens

POST `/api/v1/auth/tokens` with email, password and device_name; do not send SPA Origin/Referer or cookie credentials. Success is 201:

```json
{"data":{"token":"<returned once>","token_type":"Bearer","expires_at":"<UTC timestamp>","user":{"id":1,"name":"Researcher","email":"<normalized email>","email_verified_at":null,"created_at":"<UTC timestamp>","updated_at":"<UTC timestamp>"}}}
```

Issued tokens have a configurable expiry and standard Sanctum abilities for this foundation. `SANCTUM_MOBILE_TOKEN_EXPIRATION` defaults to 43200 minutes (30 days); `config("sanctum.mobile_token_expiration")` supplies the controller with a validated integer from 1 to 2147483647 minutes. Invalid, empty, zero, fractional or out-of-range configuration fails startup. Production can set a different positive duration in its environment and rebuild Laravel configuration cache. Each token receives an explicit `expires_at` at issuance; changing the setting affects newly issued tokens, not existing tokens or web session lifetime. Sanctum global `expiration` remains null so it does not impose a conflicting global cutoff. Only the hash is stored; plaintext is returned once in a no-store response. Store it in platform secure storage. Send `Authorization: Bearer <token>` to `/me` and other future authorized APIs. Expired/revoked tokens return 401.

DELETE `/api/v1/auth/tokens/current` with that token deletes only that current token; other devices survive. Cookie-only requests get 403. If both a valid first-party session and bearer token are supplied, Sanctum prioritizes the session; mobile clients must omit SPA cookies/context. No token listing/all-device revocation UI/API exists yet. Token cleanup scheduling and production device/session policies remain future operational work.

Rate limits, sanitized errors/logging and password/input bounds are specified in [API](API.md) and [OpenAPI](../../packages/contracts/openapi.yaml). Tests cover session and bearer paths, expiry, current-token-only deletion, CSRF, session regeneration and rate limiting.

Implementation follows [Laravel Sanctum's official SPA/token guidance](https://laravel.com/framework/docs/13.x/sanctum). The full MVP project/workspace/assistant scope remains required but unimplemented in this foundation phase.

## Production deployment gate

Before release, verify HTTPS, `SESSION_SECURE_COOKIE=true`, a trusted explicit HTTPS `FRONTEND_URL`, the correct `SESSION_DOMAIN` for the chosen same-site topology, and an owned first-party `SANCTUM_STATEFUL_DOMAINS` list. Keep HttpOnly/Lax session cookies. Rebuild cached configuration after environment changes and verify browser login/CSRF/logout in the deployed topology. The local HTTP example deliberately keeps Secure=false; copying it into production is not safe. Explicit-origin validation already exists; these deployment-specific checks are a release gate rather than brittle APP_ENV-based runtime enforcement. No CI/deployment infrastructure is supplied.

Missing mobile accounts still perform Laravel password verification against a precomputed bcrypt dummy hash at the default cost of 12. Existing-account wrong passwords and missing accounts return identical 401 error fields apart from unique request IDs. No hash is generated per request, no dummy user is stored and no credentials are logged. If the bcrypt work factor changes, review the dummy hash cost alongside the stored-password policy before deployment; tests verify both verification paths, not micro-timing equality.
