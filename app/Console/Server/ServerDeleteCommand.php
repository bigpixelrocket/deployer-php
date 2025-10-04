<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\Traits\ServerHelpersTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Delete a server from the inventory.
 */
#[AsCommand(name: 'server:delete', description: 'Delete a server from the inventory')]
class ServerDeleteCommand extends BaseCommand
{
    use ServerHelpersTrait;

    //
    // Configuration
    // -------------------------------------------------------------------------------

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('name', null, InputOption::VALUE_REQUIRED, 'Server name')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->hr();

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

            return Command::SUCCESS;
        }

        // Extract server names from DTOs for promptSelect
        $serverNames = array_map(fn (ServerDTO $server) => $server->name, $allServers);

        //
        // Select server to delete

        $this->h1('Delete Server');

        $name = (string) $this->getOptionOrPrompt(
            'name',
            fn () => $this->promptSelect(
                label: 'Select server:',
                options: $serverNames,
            )
        );

        //
        // Find server and display info

        $server = null;
        foreach ($allServers as $s) {
            if ($s->name === $name) {
                $server = $s;
                break;
            }
        }

        if ($server === null) {
            $this->error("Server '{$name}' not found in inventory");
            return Command::FAILURE;
        }

        $this->displayServerInfo($server);

        //
        // Confirm deletion

        /** @var bool $confirmed */
        $confirmed = $this->getOptionOrPrompt(
            'yes',
            fn (): bool => $this->promptConfirm(
                label: 'Are you sure you want to delete this server?',
                default: true
            )
        );

        if (!$confirmed) {
            $this->warning('Cancelled deleting server');
            $this->writeln('');

            return Command::SUCCESS;
        }

        //
        // Delete server

        $this->servers->delete($name);

        $this->success("Server '{$name}' deleted successfully");
        $this->writeln('');

        //
        // Show command hint

        $this->showCommandHint('server:delete', [
            'name' => $name,
            'yes' => $confirmed,
        ]);

        return Command::SUCCESS;
    }
}
