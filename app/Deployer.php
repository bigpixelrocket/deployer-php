<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Composer\InstalledVersions;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\Process\Process;
use Bigpixelrocket\DeployerPHP\Console\Server\ServerAddCommand;
use Bigpixelrocket\DeployerPHP\Console\Server\ServerDeleteCommand;
use Bigpixelrocket\DeployerPHP\Console\Server\ServerListCommand;

class Deployer extends Application
{
    private SymfonyStyle $io;
    private readonly Container $container;

    public function __construct()
    {
        $version = $this->getVersionFromComposer();

        parent::__construct('Deployer', $version);

        $this->setDefaultCommand('list');
        $this->container = new Container();

        // Register commands
        $this->registerCommands();
    }

    /**
     * Override to hide default Symfony application name/version display.
     */
    public function getHelp(): string
    {
        return '';
    }

    /**
     * The main execution method in Symfony Console applications.
     */
    public function doRun(InputInterface $input, OutputInterface $output): int
    {
        $this->io = new SymfonyStyle($input, $output);

        $this->displayBanner();

        return parent::doRun($input, $output);
    }

    /**
     * Display retro BBS-style ASCII art banner.
     */
    private function displayBanner(): void
    {
        $version = $this->getVersion();

        // Simple, compact banner
        $banner = [
            '',
            '  <fg=cyan>┌┬┐┌─┐┌─┐┬  ┌─┐┬ ┬┌─┐┬─┐</>',
            '  <fg=cyan> ││├┤ ├─┘│  │ │└┬┘├┤ ├┬┘</>',
            '  <fg=blue>─┴┘└─┘┴  ┴─┘└─┘ ┴ └─┘┴└─PHP</> <fg=bright-blue>'.$version.'</>',
            '',
            '  <fg=gray>The Server Provisioning & Deployment Tool for PHP</>',
            '',
            '  <fg=gray>Support this project on GitHub</> <fg=red>♥</>  <fg=magenta>https://github.com/bigpixelrocket/deployer-php</>',
            '',
        ];

        // Display the banner
        foreach ($banner as $line) {
            $this->io->writeln($line);
        }

    }

    //
    // Command registration / DI wiring
    // -------------------------------------------------------------------------------

    private function registerCommands(): void
    {
        $commands = [
            ServerAddCommand::class,
            ServerDeleteCommand::class,
            ServerListCommand::class,
        ];

        foreach ($commands as $command) {
            /** @var Command $commandInstance */
            $commandInstance = $this->container->build($command);
            $this->add($commandInstance);
        }
    }

    //
    // Version functions
    // -------------------------------------------------------------------------------

    /**
     * Get version from Composer's runtime API, git tags, or fallback.
     *
     * @return string The version string
     */
    private function getVersionFromComposer(): string
    {
        // Try Composer's InstalledVersions API first
        if (class_exists(InstalledVersions::class)) {
            try {
                $version = InstalledVersions::getPrettyVersion('bigpixelrocket/deployer-php');
                if (null !== $version) {
                    return $version;
                }
            } catch (\OutOfBoundsException) {
                // Package not found in installed.json, continue to fallbacks
            }
        }

        // Try to get version from git next
        $gitVersion = $this->getVersionFromGit();
        if (null !== $gitVersion) {
            return $gitVersion;
        }

        // Default fallback
        return 'dev-main';
    }

    /**
     * Get version from git tags.
     *
     * @return string|null The git version or null if not available
     */
    private function getVersionFromGit(): ?string
    {
        $projectRoot = dirname(__DIR__, 1);

        // Check if we're in a git repository
        if (!is_dir($projectRoot.'/.git')) {
            return null;
        }

        // Try to get the current tag
        $tagProcess = new Process(['git', 'describe', '--tags', '--exact-match'], $projectRoot);
        $tagProcess->run();
        if ($tagProcess->isSuccessful()) {
            return trim($tagProcess->getOutput());
        }

        // Get the latest tag + commit info
        $describeProcess = new Process(['git', 'describe', '--tags', '--always'], $projectRoot);
        $describeProcess->run();
        if ($describeProcess->isSuccessful()) {
            return trim($describeProcess->getOutput());
        }

        // Get current branch + short commit hash
        $branchProcess = new Process(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $projectRoot);
        $branchProcess->run();
        $commitProcess = new Process(['git', 'rev-parse', '--short', 'HEAD'], $projectRoot);
        $commitProcess->run();

        if ($branchProcess->isSuccessful() && $commitProcess->isSuccessful()) {
            $branch = trim($branchProcess->getOutput());
            $commit = trim($commitProcess->getOutput());
            return $branch.'@'.$commit;
        }

        return null;
    }
}
