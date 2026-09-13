# Self-hosting

> Status: the open core (measurement engine + schema) is published and
> tested. A turnkey `docker compose up` distribution of the full
> self-hosted stack is the next release of this work — see the roadmap
> below rather than assuming it's done.

## What you can self-host today

The **core** — the deterministic cost engine, event validation, budgets
math, and usage aggregation — is MIT-licensed and fully usable as a library:

```ts
import { computeCost, explainCost } from "@ondari/core";
```

It has no runtime dependencies. If you want to build your own cost
instrumentation, this is the part you can vendor forever, for free.

## What the full stack will include

The Cloud product runs a Next.js application + MySQL. The self-hosted
distribution is being extracted from that codebase and will ship:

- the ingestion API (`POST /api/ingest`)
- the dashboards (overview, cost explorer, agents, budgets)
- migrations + seed for the core schema (`schema/`)
- `docker compose up` with the app + MySQL + a scheduler

## Architecture (Cloud and self-hosted share it)

```
client ──POST /api/ingest──▶ ingestion ──▶ pricing resolver ──▶ usage_events
                                  │
                                  └─▶ budgets evaluator ──▶ alerts
```

- **Ingestion** validates + enforces idempotency, then prices the event.
- **Pricing resolver** picks the verified version in effect at the event
  timestamp (never "current" pricing).
- **Budgets** evaluate current-period spend against warning/critical
  thresholds and a linear forecast.

## Security

- API keys are stored hashed (sha256); plaintext is shown once at creation.
- Tenant data is scoped by `organizationId` at every query boundary.
- Events are append-only; corrections are represented explicitly, never by
  destructive mutation.

See [SECURITY.md](../SECURITY.md) for how to report a vulnerability.
