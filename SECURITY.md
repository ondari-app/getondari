# Security

Ondari measures spending. Trust is the product. We take reports
seriously.

## Reporting a vulnerability

Do **not** open a public issue. Email `security@ondari.dev`. Include a
clear description, reproduction steps, and impact. We'll acknowledge within
48 hours and coordinate a fix + disclosure.

## What we consider in-scope

- tenant isolation (cross-tenant data access)
- API-key handling (recoverability, auth bypass)
- pricing correctness / silent mispricing
- idempotency violations that cause double-counting

## Our own rules

- API keys are stored hashed; plaintext is shown once at creation.
- Events are append-only; corrections are explicit, never destructive.
- No prompts/completions collected by default.
- Secrets are never committed; see `.gitignore`.

## Supported versions

Only the latest release. Older versions receive no fixes.
