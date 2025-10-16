<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Site;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Traits\SiteHelpersTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * List all sites in the inventory.
 */
#[AsCommand(name: 'site:list', description: 'List all sites in the inventory')]
class SiteListCommand extends BaseCommand
{
    use SiteHelpersTrait;

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->io->hr();

        //
        // Get all sites

        $allSites = $this->sites->all();
        if (count($allSites) === 0) {
            $this->io->warning('No sites found in inventory');
            $this->io->writeln([
                '',
                'Use <fg=cyan>site:add</> to add a site',
                '',
            ]);

            return Command::SUCCESS;
        }

        $this->io->h1('All Sites');

        foreach ($allSites as $site) {
            $this->displaySiteDeets($site);
        }

        return Command::SUCCESS;
    }

}
