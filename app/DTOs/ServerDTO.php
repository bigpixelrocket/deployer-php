<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\DTOs;

/**
 * Data transfer object for server configuration.
 */
class ServerDTO
{
    public function __construct(
        public readonly string $host,
        public readonly int $port,
        public readonly string $user,
        public readonly ?string $key = null,
    ) {
    }

    /**
     * Convert to a plain array suitable for YAML serialization.
     *
     * @return array{host:string, port:int, user:string, key:?string}
     */
    public function toArray(): array
    {
        return [
            'host' => $this->host,
            'port' => $this->port,
            'user' => $this->user,
            'key' => $this->key,
        ];
    }
}
