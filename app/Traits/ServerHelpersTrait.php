<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\SSHService;

/**
 * Reusable server-related helpers for commands.
 *
 * Requires the using class to use the ConsoleOutputTrait and have:
 * - protected ServerRepository $servers
 * - protected SSHService $ssh
 */
trait ServerHelpersTrait
{
    /**
     * Display server information.
     */
    protected function displayServerInfo(ServerDTO $server, string $color = 'black'): void
    {
        $this->writeln([
            "  <fg={$color}>Name: </><fg=gray>{$server->name}</>",
            "  <fg={$color}>Host: </><fg=gray>{$server->host}</>",
            "  <fg={$color}>Port: </><fg=gray>{$server->port}</>",
            "  <fg={$color}>User: </><fg=gray>{$server->username}</>",
            "  <fg={$color}>Key:  </><fg=gray>".($server->privateKeyPath ?? 'default (~/.ssh/id_ed25519 or ~/.ssh/id_rsa)')."</>",
            " "
        ]);
    }

}
