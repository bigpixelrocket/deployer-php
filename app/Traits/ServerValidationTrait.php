<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

/**
 * Validation helpers for server configuration.
 */
trait ServerValidationTrait
{
    /**
     * Validate host is a valid IP or domain.
     *
     * @throws \InvalidArgumentException When host is invalid
     */
    protected function validateHost(string $host): void
    {
        $isValidIp = filter_var($host, FILTER_VALIDATE_IP) !== false;
        $isValidDomain = filter_var($host, FILTER_VALIDATE_DOMAIN, FILTER_FLAG_HOSTNAME) !== false;

        if (!$isValidIp && !$isValidDomain) {
            throw new \InvalidArgumentException(
                "Invalid host '{$host}'. Must be a valid IP address or domain name.\n".
                'Examples: 192.168.1.100, example.com, server.example.com'
            );
        }
    }

    /**
     * Validate port is in valid range.
     *
     * @throws \InvalidArgumentException When port is out of range
     */
    protected function validatePort(int $port): void
    {
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException(
                "Invalid port {$port}. Port must be between 1 and 65535.\n".
                'Common SSH ports: 22 (default), 2222, 22000'
            );
        }
    }
}
