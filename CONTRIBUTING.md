# Contributing

Thanks for caring about Ondari.

## Getting started

```bash
git clone https://github.com/ondari-app/getondari.git
cd ondari
npm install
npm test        # core test suite (vitest)
npm run typecheck
```

## What to work on

The open core is deliberately small and pure. Good first contributions:

- new provider/model pricing adapters (as a *collector* design, not code that
  mutates production pricing)
- more unit tests for the cost engine edge cases
- documentation fixes
- SDK examples in more languages

## Guidelines

- The core stays **dependency-free** — no HTTP, no DB, no framework.
- Costs must stay **deterministic** and **explainable**.
- Money arithmetic uses fixed rounding (`roundMoney`); do not introduce
  floating-point drift.
- Every behavior change ships with a test.
- No prompts/completions, no secrets, no personal data in commits.

## PRs

Keep them small and scoped. Link an issue. The maintainer reviews for
correctness, determinism, and test coverage before merge.
