# Ondari

**Know what every agent costs. Know why. Know what it accomplished.**

Ondari is the economic intelligence layer for AI-powered software. It
measures what your agents, models, tools, and workflows consume and cost —
then helps you understand and optimize that spend. Every number is
server-computed from versioned, verified pricing and explained down to the
arithmetic.

This repository is the **open-source core** of Ondari:

- the canonical **event schema**
- the deterministic **cost engine** (itemized, versioned, explainable)
- **event validation** and **idempotency**
- **budgets** and **forecast** math
- **usage aggregation**

[ondari.dev](https://ondari.dev) is the **commercial product** built on
top of it: managed hosting, managed pricing intelligence (automated
provider-price change detection), long retention, forecasting, anomaly
detection, team collaboration, and enterprise governance.

---

## What this repo contains

```
.
├── packages/core/   # the measurement engine (TypeScript, pure + tested)
├── packages/sdk-js/ # the JavaScript/TypeScript SDK
├── packages/sdk-php/ # the PHP SDK
├── packages/sdk-python/ # the Python SDK
├── schema/          # the core database schema (Prisma, MySQL/SQLite)
├── docs/            # event spec, pricing model, self-hosting, API
└── examples/        # event payloads + curl
```

The core is intentionally **pure and dependency-free** — no HTTP, no
database, no framework. Everything in `packages/core/src` is deterministic:
same inputs, same cost, every time.

## Quick start

```bash
git clone https://github.com/ondari-app/getondari.git
cd ondari
npm install
npm test          # run the core + SDK test suites
```

Send your first event with the SDK:

```ts
import { Ondari } from "@ondari/sdk";

const ondari = new Ondari({ apiKey: process.env.ONDARI_KEY });
const { cost, explanation } = await ondari.track({
  provider: "openai",
  model: "gpt-4o",
  inputTokens: 1_200_000,
  outputTokens: 42_000,
});
console.log(cost, explanation);
```

The heart of it — turn raw usage into an explainable cost:

```ts
import { computeCost, explainCost } from "@ondari/core";

const breakdown = computeCost(
  { inputTokens: 1_200_000, outputTokens: 42_000, cachedInputTokens: 0, reasoningTokens: 0 },
  { inputPrice: 2.5, outputPrice: 10.0, cachedInputPrice: 0.625, reasoningPrice: 1.25 },
  { provider: "OpenAI", model: "gpt-4o", pricingVersionId: "v2026-08-14", verifiedAt: "2026-08-15" }
);

console.log(explainCost(breakdown));
// OpenAI → gpt-4o → version v2026-08-14 (source verified 2026-08-15):
//   1.2M input × $2.50 + 42K output × $10.00 = $3.42
```

If Ondari tells you a number, it can always show you the arithmetic.

---

## Open core vs. Ondari Cloud

| | Open source (this repo) | Ondari Cloud |
|---|---|---|
| Event spec | ✅ | ✅ |
| Ingestion engine | ✅ | ✅ (managed) |
| Deterministic cost engine | ✅ | ✅ |
| Provider/model/version abstraction | ✅ | ✅ |
| Basic budgets & forecasting math | ✅ | ✅ |
| SDKs | ✅ | ✅ |
| Self-hosting | ✅ | — |
| Managed infrastructure | — | ✅ |
| Managed pricing intelligence (auto price-change detection) | — | ✅ |
| Historical pricing | — | ✅ |
| Advanced analytics, anomaly detection, optimization | — | ✅ |
| Teams, RBAC, enterprise governance | — | ✅ |
| Long-term retention | — | ✅ |

The measurement infrastructure is open. The intelligence and managed
service are commercial.

## Why pricing is versioned

Provider prices change constantly. Ondari never silently re-prices your
history: every cost is calculated against the **pricing version** that was
in effect at the time, and the version is recorded on every event. See
[docs/pricing.md](docs/pricing.md).

## Links

- **Try Ondari Cloud** — [ondari.dev](https://ondari.dev)
- **Live model pricing** — [ondari.dev/pricing](https://ondari.dev/pricing)
- **Docs** — [ondari.dev/docs](https://ondari.dev/docs)
- **Event spec** — [docs/events.md](docs/events.md)
- **Pricing & explainability** — [docs/pricing.md](docs/pricing.md)
- **Self-hosting** — [docs/self-hosting.md](docs/self-hosting.md)
- **API** — [docs/api.md](docs/api.md)

## License

MIT — see [LICENSE](LICENSE).
