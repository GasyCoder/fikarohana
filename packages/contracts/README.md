# Shared API contracts

`openapi.yaml` is the OpenAPI 3.1 canonical Foundation V1 API contract. `generated/api.d.ts` is generated with openapi-typescript; do not hand-edit it.

From the repository root:

```bash
npm run contracts:validate
npm run contracts:generate
npm run contracts:check
```

The workspace exposes generated types as `@fikarohana/contracts`. No runtime HTTP client or UI integration is implemented. Read [API](../../docs/api/API.md), [authentication](../../docs/api/AUTHENTICATION.md) and [contract tooling](../../docs/api/OPENAPI.md) before extending the contract.
