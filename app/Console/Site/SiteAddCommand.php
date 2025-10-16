<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Console\Site;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Bigpixelrocket\DeployerPHP\Traits\SiteHelpersTrait;
use Bigpixelrocket\DeployerPHP\Traits\SiteValidationTrait;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Add and register a new site to the inventory.
 *
 * Prompts for site details and saves to inventory.
 */
#[AsCommand(name: 'site:add', description: 'Add a new site to the inventory')]
class SiteAddCommand extends BaseCommand
{
    use SiteHelpersTrait;
    use SiteValidationTrait;

    //
    // Configuration
    // -------------------------------------------------------------------------------

    protected function configure(): void
    {
        parent::configure();

        $this
            ->addOption('domain', null, InputOption::VALUE_REQUIRED, 'Domain name')
            ->addOption('type', null, InputOption::VALUE_REQUIRED, 'Site type: git or local')
            ->addOption('repo', null, InputOption::VALUE_REQUIRED, 'Git repository URL (for git sites)')
            ->addOption('branch', null, InputOption::VALUE_REQUIRED, 'Git branch name (for git sites)')
            ->addOption('servers', null, InputOption::VALUE_REQUIRED, 'Comma-separated server names');
    }

    //
    // Execution
    // -------------------------------------------------------------------------------

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        parent::execute($input, $output);

        $this->io->hr();

        $this->io->h1('Add New Site');

        //
        // Check if there are any servers

        if (count($this->servers->all()) === 0) {
            $this->io->warning('No servers available');
            $this->io->writeln([
                '',
                'You must add at least one server before adding a site.',
                'Run <fg=cyan>server:provision</> to provision your first server,',
                'or run <fg=cyan>server:add</> to add an existing server.',
                '',
            ]);

            return Command::FAILURE;
        }

        //
        // Gather site details

        /** @var string|null $domain */
        $domain = $this->io->getValidatedOptionOrPrompt(
            'domain',
            fn ($validate) => $this->io->promptText(
                label: 'Domain name:',
                placeholder: 'example.com',
                required: true,
                validate: $validate
            ),
            fn ($value) => $this->validateDomainInput($value)
        );

        if ($domain === null) {
            return Command::FAILURE;
        }

        //
        // Select site type

        /** @var string $siteType */
        $siteType = $this->io->getOptionOrPrompt(
            'type',
            fn (): string => (string) $this->io->promptSelect(
                label: 'Deploy from:',
                options: ['git' => 'Git Repository', 'local' => 'Local files'],
                default: 'git'
            )
        );

        $isLocal = $siteType === 'local';

        //
        // Gather git-specific details

        $repo = null;
        $branch = null;

        if (!$isLocal) {
            $defaultRepo = $this->git->detectRemoteUrl() ?? '';

            /** @var string|null $repo */
            $repo = $this->io->getValidatedOptionOrPrompt(
                'repo',
                fn ($validate) => $this->io->promptText(
                    label: 'Git repository URL:',
                    placeholder: 'git@github.com:user/repo.git',
                    default: $defaultRepo,
                    required: true,
                    validate: $validate
                ),
                fn ($value) => $this->validateRepoInput($value)
            );

            if ($repo === null) {
                return Command::FAILURE;
            }

            $defaultBranch = $this->git->detectCurrentBranch() ?? 'main';

            /** @var string|null $branch */
            $branch = $this->io->getValidatedOptionOrPrompt(
                'branch',
                fn ($validate) => $this->io->promptText(
                    label: 'Git branch:',
                    placeholder: $defaultBranch,
                    default: $defaultBranch,
                    required: true,
                    validate: $validate
                ),
                fn ($value) => $this->validateBranchInput($value)
            );

            if ($branch === null) {
                return Command::FAILURE;
            }
        }

        //
        // Select servers

        try {
            $selectedServers = $this->selectServers();
        } catch (\RuntimeException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        //
        // Validate selections

        try {
            $this->validateServers($selectedServers);
        } catch (\RuntimeException $e) {
            $this->io->error($e->getMessage());

            return Command::FAILURE;
        }

        //
        // Create DTO and display site info

        $site = new SiteDTO(
            domain: $domain,
            repo: $repo,
            branch: $branch,
            servers: $selectedServers
        );

        $this->io->hr();

        $this->displaySiteDeets($site);

        //
        // Save to repository

        try {
            $this->sites->create($site);
        } catch (\RuntimeException $e) {
            $this->io->error('Failed to add site: ' . $e->getMessage());

            return Command::FAILURE;
        }

        $this->io->success('Site added successfully');
        $this->io->writeln('');

        //
        // Show command hint

        $hintOptions = [
            'domain' => $domain,
            'type' => $siteType,
            'servers' => implode(',', $selectedServers),
        ];

        if (!$isLocal) {
            $hintOptions['repo'] = $repo;
            $hintOptions['branch'] = $branch;
        }

        $this->io->showCommandHint('site:add', $hintOptions);

        return Command::SUCCESS;
    }
}
