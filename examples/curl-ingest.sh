#!/usr/bin/env bash
# Ingest one event into AgentBurn Cloud.
set -euo pipefail

ONDARI_KEY="${ONDARI_KEY:?set ONDARI_KEY to a project API key}"
URL="${AGENTBURN_URL:-https://agentburn.dev}"

curl -sS -X POST "$URL/api/ingest" \
  -H "Authorization: Bearer $ONDARI_KEY" \
  -H "Content-Type: application/json" \
  -d @examples/ingest-event.json
echo
