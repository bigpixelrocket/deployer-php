<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Bigpixelrocket\DeployerPHP\Console\HelloCommand;
use Bigpixelrocket\DeployerPHP\Services\VersionService;
use Symfony\Component\Console\Application as SymfonyApplication;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

/**
 * The Symfony application entry point.
 */
final class SymfonyApp extends SymfonyApplication
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly Container $container,
        private readonly VersionService $versionService,
    ) {
        $name = 'Deployer PHP';
        $version = $this->versionService->getVersion();
        parent::__construct($name, $version);

        $this->registerCommands();

        $this->setDefaultCommand('list');
    }

    //
    // Public
    // -------------------------------------------------------------------------------

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

        if (!$output->isQuiet()) {
            $this->displayBanner();
        }

        return parent::doRun($input, $output);
    }

    //
    // Private
    // -------------------------------------------------------------------------------

    /**
     * Display retro BBS-style ASCII art banner.
     */
    private function displayBanner(): void
    {
        $version = $this->getVersion();

        // Simple, compact banner
        $banner = [
            '',
            '<fg=cyan>╭───────</><fg=blue>─────────</><fg=bright-blue>─────────</><fg=magenta>─────────</><fg=gray>────────</>',
            ' <fg=cyan>┌┬┐┌─┐┌─┐┬  ┌─┐┬ ┬┌─┐┬─┐</>',
            ' <fg=cyan> ││├┤ ├─┘│  │ │└┬┘├┤ ├┬┘</>',
            ' <fg=blue>─┴┘└─┘┴  ┴─┘└─┘ ┴ └─┘┴└─PHP</> <fg=bright-blue>'.$version.'</>',
            '',
            ' <fg=gray>The Server & Site Deployment Tool for PHP</>',
            '<fg=cyan>╰───────</><fg=blue>─────────</><fg=bright-blue>─────────</><fg=magenta>─────────</><fg=gray>────────</>',
            ''
        ];

        // Display the banner
        foreach ($banner as $line) {
            $this->io->writeln($line);
        }
    }

    /**
     * Register commands with auto-wired dependencies.
     */
    private function registerCommands(): void
    {
        $commands = [
            HelloCommand::class,
        ];

        foreach ($commands as $command) {
            /** @var Command $commandInstance */
            $commandInstance = $this->container->build($command);
            $this->add($commandInstance);
        }
    }
}
