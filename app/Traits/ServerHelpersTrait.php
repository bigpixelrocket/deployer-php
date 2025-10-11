<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Symfony\Component\Console\Command\Command;

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
     * Display server details.
     */
    protected function displayServerDeets(ServerDTO $server): void
    {
        $this->writeln([
            "  Name: <fg=gray>{$server->name}</>",
            "  Host: <fg=gray>{$server->host}</>",
            "  Port: <fg=gray>{$server->port}</>",
            "  User: <fg=gray>{$server->username}</>",
            '  Key:  <fg=gray>'.($server->privateKeyPath ?? 'default (~/.ssh/id_ed25519 or ~/.ssh/id_rsa)').'</>',
            ' '
        ]);
    }

    /**
     * Select a server from inventory by server option or interactive prompt.
     *
     * @return array{server: ServerDTO|null, exit_code: int} Server DTO and exit code (SUCCESS if empty inventory, FAILURE if not found)
     */
    protected function selectServer(string $optionName = 'server', string $promptLabel = 'Select server:'): array
    {
        //
        // Get all servers

        $allServers = $this->servers->all();
        if (count($allServers) === 0) {
            $this->warning('No servers found in inventory');
            $this->writeln([
                '',
                'Use <fg=cyan>server:add</> to add a server',
                '',
            ]);

            return ['server' => null, 'exit_code' => Command::SUCCESS];
        }

        //
        // Extract server names and prompt for selection

        $serverNames = array_map(fn (ServerDTO $server) => $server->name, $allServers);

        $name = (string) $this->getOptionOrPrompt(
            $optionName,
            fn () => $this->promptSelect(
                label: $promptLabel,
                options: $serverNames,
            )
        );

        //
        // Find server by name

        $server = null;
        foreach ($allServers as $s) {
            if ($s->name === $name) {
                $server = $s;
                break;
            }
        }

        if ($server === null) {
            $this->error("Server '{$name}' not found in inventory");

            return ['server' => null, 'exit_code' => Command::FAILURE];
        }

        return ['server' => $server, 'exit_code' => Command::SUCCESS];
    }

}
