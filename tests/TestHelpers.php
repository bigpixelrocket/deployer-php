<?php

declare(strict_types=1);

/**
 * Set or unset environment variables for testing.
 */
function setEnv(string $key, ?string $value): void
{
    if ($value === null) {
        unset($_ENV[$key]);
        putenv("{$key}");
    } else {
        $_ENV[$key] = $value;
        putenv("{$key}={$value}");
    }
}
