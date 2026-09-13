"""Idempotency key generation."""

import uuid


def idempotency_key() -> str:
    """Return a unique, retry-safe idempotency key (UUID4 string)."""
    return str(uuid.uuid4())
