# Claude Code Instructions — Fikarohana

Read `AGENTS.md` first.

`AGENTS.md` contains the mandatory project-wide engineering rules.

Before performing substantial work, also read the relevant documentation under `docs/`.

## Working method

For every substantial task:

1. Inspect the repository and relevant files.
2. Read the applicable project documentation.
3. State the implementation plan internally before editing.
4. Make focused changes.
5. Run relevant tests and checks.
6. Report:
   - what changed
   - files changed
   - tests executed
   - remaining risks or TODOs

Do not invent requirements that contradict the documentation.

If documentation is incomplete or ambiguous, prefer documenting the assumption instead of silently introducing architecture.

## Important restrictions

Do not:

- replace Laravel with another backend framework
- merge Next.js into Laravel
- introduce Inertia
- make Next.js the canonical backend
- let mobile bypass the Laravel API
- access PostgreSQL directly from frontend clients
- use an LLM as the source of journal metadata
- present recommendation scores as acceptance probabilities
- run destructive dependency fixes
- run `npm audit fix --force`
- downgrade Expo outside its SDK compatibility matrix
- create Git branches unless explicitly requested
- commit unless explicitly requested

## Current state

This repository has recently been rebuilt as an API-first monorepo.

The previous Fikarohana implementation must not be restored.

The new architecture is defined by the root documentation.

When implementing API functionality, use `/api/v1`.

When working on journal-related functionality, provenance and source verification are mandatory.

When working on AI functionality, preserve traceability between answers and authoritative sources.
