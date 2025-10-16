<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Site;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Traits\SiteHelpersTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Delete a site from the inventory.
 */
#[AsCommand(name: 'site:delete', description: 'Delete a site from the inventory')]
class SiteDeleteCommand extends BaseCommand
{
    use SiteHelpersTrait;

    //
    // Configuration
    // -------------------------------------------------------------------------------

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('site', null, InputOption::VALUE_REQUIRED, 'Site domain')
            ->addOption('yes', 'y', InputOption::VALUE_NONE, 'Skip confirmation prompt');
    }

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->io->hr();

        $this->io->h1('Delete Site');

        //
        // Select site

        $selection = $this->selectSite();

        if ($selection['site'] === null) {
            return $selection['exit_code'];
        }

        $site = $selection['site'];
        $this->displaySiteDeets($site);

        //
        // Confirm deletion

        $this->io->writeln('');

        /** @var bool $confirmed */
        $confirmed = $this->io->getOptionOrPrompt(
            'yes',
            fn (): bool => $this->io->promptConfirm(
                label: 'Are you sure you want to delete this site?',
                default: true
            )
        );

        if (!$confirmed) {
            $this->io->warning('Cancelled deleting site');
            $this->io->writeln('');

            return Command::SUCCESS;
        }

        //
        // Delete site

        $this->sites->delete($site->domain);

        $this->io->success("Site '{$site->domain}' deleted successfully");
        $this->io->writeln('');

        //
        // Show command hint

        $this->io->showCommandHint('site:delete', [
            'site' => $site->domain,
            'yes' => $confirmed,
        ]);

        return Command::SUCCESS;
    }
}
