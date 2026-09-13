# Changelog

All notable changes to the Ondari open core are recorded here.

## [Unreleased]

- `@ondari/sdk`: JavaScript/TypeScript SDK — `track()` / `trackBatch()`,
  automatic idempotency keys, retry on 429/5xx, pluggable base URL.
- `ondari/sdk` (PHP): same surface for PHP 8.1+ — curl-backed client,
  injectable transport, idempotency helper.
- `ondari-sdk` (Python): same surface for Python 3.8+ — urllib-backed client,
  stdlib-only, injectable transport.

## [0.1.0] — 2026-09-09

Initial public release of the open core:

- `@ondari/core`: deterministic cost engine (`pricing`), pricing
  verification helpers (`pricing-workflow`), event validation (`ingest`),
  usage aggregation (`usage`), and budget math (`budgets`) — all pure,
  dependency-free, and unit-tested.
- Canonical event spec (`docs/events.md`).
- Pricing & explainability model (`docs/pricing.md`).
- Core database schema (`schema/schema.core.prisma`).
- Examples and contribution/security docs.
