<?php

declare(strict_types=1);

namespace Ondari;

final class Idempotency
{
    /** A unique, retry-safe idempotency key (32 hex chars). */
    public static function key(): string
    {
        return bin2hex(random_bytes(16));
    }
}
