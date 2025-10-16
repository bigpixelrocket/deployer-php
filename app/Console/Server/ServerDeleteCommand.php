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

        $this->io->hr();

        $this->io->h1('Delete Server');

        //
        // Select server

        $selection = $this->selectServer();

        if ($selection['server'] === null) {
            return $selection['exit_code'];
        }

        $server = $selection['server'];
        $this->displayServerDeets($server);

        // Get sites for this server
        $serverSites = $this->sites->findByServer($server->name);

        if (count($serverSites) > 0) {
            $this->io->writeln(['  Sites:']);
            foreach ($serverSites as $site) {
                $this->io->writeln(["    • <fg=gray>{$site->domain}</>"]);
            }

            $this->io->writeln('');

            $this->io->error("Cannot delete server '{$server->name}' because it has one or more sites.");

            return Command::FAILURE;

        }

        //
        // Confirm deletion

        $this->io->writeln('');

        /** @var bool $confirmed */
        $confirmed = $this->io->getOptionOrPrompt(
            'yes',
            fn (): bool => $this->io->promptConfirm(
                label: 'Are you sure you want to delete this server?',
                default: true
            )
        );

        if (!$confirmed) {
            $this->io->warning('Cancelled deleting server');
            $this->io->writeln('');

            return Command::SUCCESS;
        }

        //
        // Delete server

        $this->servers->delete($server->name);

        $this->io->success("Server '{$server->name}' deleted successfully");
        $this->io->writeln('');

        //
        // Show command hint

        $this->io->showCommandHint('server:delete', [
            'server' => $server->name,
            'yes' => $confirmed,
        ]);

        return Command::SUCCESS;
    }
}
