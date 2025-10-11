<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
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
            ->addOption('server', null, InputOption::VALUE_REQUIRED, 'Server name')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->hr();

        $this->h1('Delete Server');

        //
        // Select server

        $selection = $this->selectServer();

        if ($selection['server'] === null) {
            return $selection['exit_code'];
        }

        $server = $selection['server'];
        $this->displayServerDeets($server);

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

        $this->servers->delete($server->name);

        $this->success("Server '{$server->name}' deleted successfully");
        $this->writeln('');

        //
        // Show command hint

        $this->showCommandHint('server:delete', [
            'server' => $server->name,
            'yes' => $confirmed,
        ]);

        return Command::SUCCESS;
    }
}
