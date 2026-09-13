# Canonical Usage Event

Every AI action Ondari records is one **usage event**. This document is
the source of truth for that shape — the SDKs and the ingestion API accept
exactly this.

## Example

```json
{
  "idempotency_key": "9f8c2a1e-6d4b-4c3a-9e1b-5a7d8f2c4e6a",
  "timestamp": "2026-09-09T14:00:00.000Z",
  "provider": "openai",
  "model": "gpt-4o",
  "inputTokens": 1200000,
  "outputTokens": 42000,
  "cachedInputTokens": 0,
  "reasoningTokens": 0,
  "environment": "prod",
  "agentId": "research-agent",
  "workflowId": "wf_123",
  "taskId": "task_456",
  "latencyMs": 1234,
  "status": "ok",
  "source": "sdk-js",
  "customMetadata": { "repo": "acme/app", "pr": 42 }
}
```

## Fields

| Field | Type | Required | Notes |
|---|---|---|---|
| `idempotency_key` | string | ✅ | Unique per logical operation. Reusing it returns the original event — retries never double-count. |
| `timestamp` | ISO-8601 | — | Defaults to now. Used to select the pricing version in effect at that moment. |
| `provider` | string | ✅ | Provider slug (`openai`, `anthropic`, `google`, …). |
| `model` | string | — | Exact model identifier. Unknown models are accepted and stored `unpriced` — never rejected. |
| `inputTokens` | int ≥ 0 | — | Default 0. |
| `outputTokens` | int ≥ 0 | — | Default 0. |
| `cachedInputTokens` | int ≥ 0 | — | Default 0. Billed at the cached-input rate when priced. |
| `reasoningTokens` | int ≥ 0 | — | Default 0. Billed at the reasoning rate when priced. |
| `environment` | string | — | Free-text dimension for scoping (e.g. `prod`, `staging`). |
| `agentId` | string | — | Free-text agent identifier — the grouping key for agent dashboards. |
| `workflowId` | string | — | Optional workflow/correlation id. |
| `taskId` | string | — | Optional task id — the future "economic action" grouping key. |
| `latencyMs` | int ≥ 0 | — | Optional. |
| `status` | string | — | Default `ok`. |
| `source` | string | — | SDK/collector name. |
| `customMetadata` | object | — | Arbitrary JSON — the extension point for repo, branch, PR, etc. |

## Cost is server-computed

You do **not** send a cost. Ondari resolves the pricing version in effect
for `provider` + `model` at `timestamp`, computes the itemized cost, and
returns it with its provenance.

```json
{
  "accepted": true,
  "pricingStatus": "priced",
  "cost": 3.42,
  "explanation": "OpenAI → gpt-4o → version v2026-08-14 (source verified 2026-08-15): 1.2M input × $2.50 + 42K output × $10.00 = $3.42"
}
```

If the model isn't known yet, the event is accepted with
`"pricingStatus": "unpriced"` and `"cost": null` — it is never silently
zero-cost, and it is backfilled automatically once pricing is verified.

## Privacy

Ondari records **metadata, not prompts or completions**. Prompt/completion
capture is off by default and opt-in. `customMetadata` is yours to control.
