<?php

declare(strict_types=1);

namespace Ondari;

/**
 * Thrown for deterministic failures (client errors, malformed responses)
 * that should NOT be retried. Network errors and 429/5xx are retried.
 */
class OndariException extends \RuntimeException
{
}
