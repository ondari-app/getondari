"""The Ondari client."""

import json
import time
from datetime import datetime
from typing import Any, Dict, List, Optional

from .errors import OndariError
from .http import HttpResponse, UrllibHttpClient
from .idempotency import idempotency_key

DEFAULT_BASE_URL = "https://ondari.dev"


class Ondari:
    """Send usage events and read back itemized, explainable costs.

    Cost is server-computed — you never send a price.
    """

    def __init__(
        self,
        api_key: str,
        base_url: str = DEFAULT_BASE_URL,
        retries: int = 3,
        timeout: float = 10.0,
        http: Optional[UrllibHttpClient] = None,
    ):
        if not api_key:
            raise OndariError("apiKey is required")
        self.api_key = api_key
        self.base_url = base_url.rstrip("/")
        self.retries = retries
        self.http = http or UrllibHttpClient(timeout=timeout)

    def track(self, event: Dict[str, Any]) -> Dict[str, Any]:
        """Send one event and return its pricing result."""
        results = self._ingest([event])
        first = results[0] if results else None
        if first is None:
            raise OndariError("empty ingest response")
        if first.get("error"):
            raise OndariError(str(first["error"]))
        return first

    def track_batch(self, events: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        """Send many events in one request. Results match input order."""
        if not events:
            return []
        return self._ingest(events)

    def _ingest(self, events: List[Dict[str, Any]]) -> List[Dict[str, Any]]:
        body = [self._serialize(e) for e in events]
        payload = json.dumps(body)
        url = f"{self.base_url}/api/ingest"
        headers = {
            "Content-Type": "application/json",
            "Authorization": f"Bearer {self.api_key}",
        }

        attempt = 0
        while True:
            try:
                response = self.http.post(url, headers, payload)
            except OndariError:
                if attempt >= self.retries:
                    raise
                attempt += 1
                self._backoff(attempt)
                continue

            if response.status == 429 or response.status >= 500:
                if attempt >= self.retries:
                    raise OndariError(f"HTTP {response.status} after {self.retries} retries")
                attempt += 1
                self._backoff(attempt)
                continue

            try:
                data = json.loads(response.body)
            except ValueError:
                raise OndariError(f"invalid JSON in {response.status} response") from None

            if not isinstance(data, dict):
                raise OndariError(f"invalid JSON in {response.status} response")

            if response.status >= 400:
                raise OndariError(str(data.get("error") or f"HTTP {response.status}"))

            return data.get("results") or []

    @staticmethod
    def _serialize(event: Dict[str, Any]) -> Dict[str, Any]:
        ts = event.get("timestamp")
        if isinstance(ts, datetime):
            ts = ts.isoformat()
        return {
            "idempotency_key": event.get("idempotencyKey") or idempotency_key(),
            "provider": event.get("provider"),
            "model": event.get("model"),
            "inputTokens": event.get("inputTokens"),
            "outputTokens": event.get("outputTokens"),
            "cachedInputTokens": event.get("cachedInputTokens"),
            "reasoningTokens": event.get("reasoningTokens"),
            "timestamp": ts,
            "environment": event.get("environment"),
            "agentId": event.get("agentId"),
            "workflowId": event.get("workflowId"),
            "taskId": event.get("taskId"),
            "latencyMs": event.get("latencyMs"),
            "status": event.get("status"),
            "source": event.get("source") or "sdk-python",
            "customMetadata": event.get("customMetadata"),
        }

    @staticmethod
    def _backoff(attempt: int) -> None:
        time.sleep(min(1.0, 0.1 * (2 ** attempt)))
