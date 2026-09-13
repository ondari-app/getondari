# Pricing, versions, and explainability

The pricing system is the part of Ondari that must never be wrong. Three
rules govern it:

1. **Costs are deterministic.** Normalized usage × a pricing version → an
   itemized, reproducible breakdown.
2. **Pricing is versioned.** Every provider/model has a history of pricing
   versions with `effective_from` / `effective_to` boundaries.
3. **History never silently changes.** Each event records the pricing version
   used, so a cost calculated in March stays a March cost even after the
   provider raises its prices in April.

## Data model

```
provider ──< model ──< pricing_version
                        ├─ inputPrice      (per 1M tokens)
                        ├─ outputPrice
                        ├─ cachedInputPrice
                        ├─ reasoningPrice
                        ├─ effectiveFrom / effectiveTo
                        ├─ sourceUrl
                        ├─ verificationStatus  (pending | verified | rejected | superseded)
                        └─ verifiedAt
```

A `current_model_pricing` pointer holds the single active version per model.

## Cost calculation

```
cost = input/1e6 × inputPrice
     + output/1e6 × outputPrice
     + cachedInput/1e6 × cachedInputPrice   (if configured)
     + reasoning/1e6 × reasoningPrice       (if configured)
```

Categories without a configured rate are simply omitted — a model with no
cached-input rate never charges for cached input.

## Explainability

Every cost resolves to a one-line proof:

> `OpenAI → gpt-4o → version v2026-08-14 (source verified 2026-08-15): 1.2M input × $2.50 + 42K output × $10.00 = $3.42`

That line is *part of the product*, not an implementation detail. If
Ondari says $3.42, one click shows why.

## How prices get in (the hard part)

Provider prices change constantly and rarely expose a clean API. Ondari
uses a tiered model:

- **Tier 1 — official source.** Provider pricing pages/APIs.
- **Tier 2 — adapters.** A per-provider collector/parser normalizes the
  official source into the pricing schema.
- **Tier 3 — manual.** A human creates or corrects a record.

**Automated discovery never mutates production pricing.** A detected change
becomes a *pending* change; a human reviews and approves it into a new
verified version. (The OpenRouter collector is an example Tier-2 adapter,
and lives in the Cloud product.)

## Coverage KPI

The number that matters isn't "how many models" — it's:

> **% of observed usage priced within 24 hours**

Unknown models are accepted and backfilled, so coverage trends toward 100%
for what you actually use — while never pretending an unpriced model is free.
