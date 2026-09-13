"""HTTP transport. urllib-backed by default; injectable for tests."""

import urllib.error
import urllib.request

from .errors import OndariError


class HttpResponse:
    def __init__(self, status: int, body: str):
        self.status = status
        self.body = body


class UrllibHttpClient:
    """Dependency-free HTTP client. Returns an HttpResponse for any HTTP
    status (including 4xx/5xx); raises OndariError only on network
    failures, which the client treats as retryable."""

    def __init__(self, timeout: float = 10.0):
        self.timeout = timeout

    def post(self, url: str, headers: dict, body: str) -> HttpResponse:
        req = urllib.request.Request(
            url, data=body.encode("utf-8"), headers=headers, method="POST"
        )
        try:
            with urllib.request.urlopen(req, timeout=self.timeout) as resp:
                return HttpResponse(resp.status, resp.read().decode("utf-8"))
        except urllib.error.HTTPError as e:
            return HttpResponse(e.code, e.read().decode("utf-8"))
        except urllib.error.URLError as e:
            raise OndariError(f"network error: {e.reason}") from e
        except TimeoutError as e:
            raise OndariError("request timed out") from e
