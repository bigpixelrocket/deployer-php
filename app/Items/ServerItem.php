<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Items;

use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;

/**
 * Server item encapsulating server-specific CRUD and validation rules.
 */
class ServerItem
{
    public function __construct(private readonly InventoryService $inventory)
    {
    }

    /**
     * Create a server record after validation.
     */
    public function create(string $name, ServerDTO $server): void
    {
        $this->assertValidName($name);
        $this->assertValidServer($server);

        if ($this->exists($name)) {
            throw new \RuntimeException("Server '{$name}' already exists.");
        }

        $this->inventory->set('servers', $name, $server->toArray());
    }

    /**
     * Check if a server exists.
     */
    public function exists(string $name): bool
    {
        return $this->inventory->has('servers', $name);
    }

    /**
     * Validate server payload shape and values.
     */
    private function assertValidServer(ServerDTO $server): void
    {
        if ($server->host === '') {
            throw new \InvalidArgumentException('Invalid host.');
        }

        $port = $server->port;
        if ($port < 1 || $port > 65535) {
            throw new \InvalidArgumentException('Invalid port.');
        }

        if ($server->user === '') {
            throw new \InvalidArgumentException('Invalid user.');
        }
    }

    private function assertValidName(string $name): void
    {
        if ($name === '' || !preg_match('/^[a-zA-Z0-9_.-]+$/', $name)) {
            throw new \InvalidArgumentException('Invalid server name. Use letters, numbers, dots, dashes, underscores.');
        }
    }
}
