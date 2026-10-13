# Fikarohana web — Phase 4B

Next.js + TypeScript public journal discovery client. Run from the repository root:

```bash
npm --workspace apps/web run dev
npm --workspace apps/web test
npm --workspace apps/web run typecheck
npm --workspace apps/web run lint
npm --workspace apps/web run build
```

Run the existing Laravel API separately on localhost:8000 with its reviewed published PostgreSQL catalogue. `FIKAROHANA_API_ORIGIN` optionally sets another Laravel HTTP(S) origin; configure it before building/running Next. The frontend uses same-origin `/api/v1` rewrites, generated contracts, no browser credentials and no provider calls. An unavailable API produces a real error state, never demo journals.

Routes: `/`, `/research`, `/recommendations`, `/journals`, `/journals/{id}`, `/compare`. Recommendation results and a comparison shortlist remain in memory during client navigation. Research input is not written to URLs, local/session storage or logs. Reloading clears recommendations and comparisons; browse filters/pagination are in the URL. No accounts or saved research.

Tests use Node's built-in runner, installed TypeScript transpilation, synthetic contract-shaped metadata, mocked fetch and React server rendering. Temporary compiled files are created under the OS temporary directory and removed. No new testing dependency; browser interaction end-to-end tests are not included in this runner.

See [design, privacy, limitations and handoff](../../docs/development/JOURNAL-DISCOVERY-WEB-V1.md).
