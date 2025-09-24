<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Bigpixelrocket\DeployerPHP\Console\HelloCommand;
use Bigpixelrocket\DeployerPHP\Services\VersionDetectionService;
use Symfony\Component\Console\Application;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Deployer extends Application
{
    private SymfonyStyle $io;

    public function __construct(
        private readonly VersionDetectionService $versionService,
    ) {
        $version = $this->versionService->getVersion();
        parent::__construct('Deployer', $version);

        $this->registerCommands();

        $this->setDefaultCommand('list');
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
            HelloCommand::class,
        ];

        foreach ($commands as $command) {
            /** @var Command $commandInstance */
            $commandInstance = App::build($command);
            $this->add($commandInstance);
        }
    }

}
