"""Errors raised by the Ondari SDK."""


class OndariError(RuntimeError):
    """Deterministic failure (client error, malformed response). Not retried."""
