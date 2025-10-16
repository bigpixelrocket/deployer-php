<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Server;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Traits\ServerHelpersTrait;
use Bigpixelrocket\DeployerPHP\Traits\SiteHelpersTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List all servers in the inventory.
 */
#[AsCommand(name: 'server:list', description: 'List all servers in the inventory')]
class ServerListCommand extends BaseCommand
{
    use ServerHelpersTrait;
    use SiteHelpersTrait;

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->io->hr();

        //
        // Get all servers

        $allServers = $this->servers->all();
        if (count($allServers) === 0) {
            $this->io->warning('No servers found in inventory');
            $this->io->writeln([
                '',
                'Use <fg=cyan>server:add</> to add a server',
                '',
            ]);

            return Command::SUCCESS;
        }

        //
        // Display servers with their sites

        $this->io->h1('All Servers');

        foreach ($allServers as $count => $server) {
            $this->displayServerDeets($server);

            // Get sites for this server
            $serverSites = $this->sites->findByServer($server->name);

            if (count($serverSites) > 0) {
                $this->io->writeln(['  Sites:']);
                foreach ($serverSites as $site) {
                    $this->io->writeln(["    • <fg=gray>{$site->domain}</>"]);
                }
                $this->io->writeln('');
            }

            if ($count < count($allServers) - 1) {
                $this->io->writeln([
                        '  ───',
                        '',
                    ]);
            }
        }

        return Command::SUCCESS;
    }

}
