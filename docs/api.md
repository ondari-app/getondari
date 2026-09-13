# API

The Cloud API (and the future self-hosted distribution) is HTTP + JSON.

## Ingest

```
POST /api/ingest
Authorization: Bearer <project API key>
Content-Type: application/json
```

Single event or a JSON array (batch, up to 1,000).

```bash
curl -X POST https://ondari.dev/api/ingest \
  -H "Authorization: Bearer $ONDARI_KEY" \
  -H "Content-Type: application/json" \
  -d '{
    "idempotency_key": "op-0001",
    "provider": "openai",
    "model": "gpt-4o",
    "inputTokens": 1200000,
    "outputTokens": 42000
  }'
```

Response: `{ accepted, pricingStatus, cost, explanation }`. See
[docs/events.md](events.md) for the full schema.

## Budgets

```
GET    /api/budgets            # list budgets with live computed state
POST   /api/budgets            # create
GET    /api/budgets/:id        # one budget + state
PATCH  /api/budgets/:id        # update
DELETE /api/budgets/:id        # delete
GET    /api/budget-events      # fired-threshold history
```

## Pricing (admin)

```
GET  /api/pricing/changes             # pending/approved/rejected review queue
POST /api/pricing/changes             # submit a proposed change (pending)
POST /api/pricing/changes/:id/approve # mint a verified version
POST /api/pricing/changes/:id/reject  # close the change
GET  /api/pricing/coverage            # model coverage + 24h usage-priced KPI
```

## API keys (admin)

```
GET  /api/api-keys               # list (hashes never returned)
POST /api/api-keys               # create — plaintext returned once
POST /api/api-keys/:id/rotate    # atomic create-new + revoke-old
POST /api/api-keys/:id/revoke    # soft revoke (audited)
```

## Versioning

The API is unversioned today (`/api/…`). A formal `/v1/` namespace is on the
roadmap; breaking changes will be announced before they ship.
