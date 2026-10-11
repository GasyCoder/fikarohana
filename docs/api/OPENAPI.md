# Canonical OpenAPI contract

Foundation V1 uses OpenAPI 3.1.0 in [packages/contracts/openapi.yaml](../../packages/contracts/openapi.yaml). This is the authoritative machine-readable contract for the shared Laravel API. Future endpoints must extend it before implementing clients; no competing handcrafted web/mobile API models.

The contract contains health, account registration, web login/logout, mobile token issuance/current revocation and me, plus the `/sanctum/csrf-cookie` framework exception. `/up` remains operational process liveness outside the business contract. Cookie/session and opaque Sanctum Bearer authentication are documented separately; no JWT scheme. Reusable user/request/result/error schemas, status codes, validation fields, correlation headers and CSRF headers are included. Cookie name can be configured; the contract documents the example environment's default.

## Tooling and commands

npm workspaces is canonical. Root dev dependencies are pinned `openapi-typescript@7.13.0` and `@redocly/openapi-core@1.34.6`; core is also the generator's validator dependency and is declared directly because our scripts import it. No UI/runtime dependency was added. The contracts workspace is `@fikarohana/contracts` with TypeScript declarations under `generated/api.d.ts`.

```bash
npm run contracts:validate
npm run contracts:generate
npm run contracts:check
```

Validation uses Redocly's recommended OpenAPI rules, fails on both errors and warnings and disables only public-license and non-local-server recommendations for this private/local foundation. Generation validates first and uses openapi-typescript's AST serializer. Check regenerates in memory and compares exact output, failing for stale/missing committed declarations without modifying files. No remote schema fetch is needed.

Edit YAML, validate, regenerate, check and review both diffs. Do not edit generated output directly. Consumers will import generated declarations when API integration is introduced; this task does not add frontend/mobile API clients or UI. Root package-lock.json remains canonical; npm deduplication retains all previously locked package versions while adding minimal contract tooling. See [official openapi-typescript documentation](https://openapi-ts.dev/node).

Numeric user IDs match the existing Laravel user model; future opaque domain IDs do not require changing account IDs. JSON Schema request objects allow ignored additional input fields to match Form Request behavior, while response schemas restrict exposed fields. Schema validation alone does not prove runtime compatibility; PHP behavior tests and real HTTP verification cover the implemented authentication paths.

Foundation security corrections document Origin OR Referer for stateful login/logout and mandatory CSRF evidence with the preferred X-XSRF-TOKEN header or framework-supported alternatives, non-empty required strings, registration minimum 12 characters and the server-enforced 72-byte bcrypt constraint. Password descriptions reject null bytes without pretending JSON Schema maxLength measures bytes. The CSRF bootstrap route has a framework-managed cache policy, with no no-store guarantee; versioned API responses retain no-store. Token expiry is explicit and configurable (default 43200 minutes); generated declarations derive only from YAML.
