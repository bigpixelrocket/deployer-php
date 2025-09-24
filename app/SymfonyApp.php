<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Bigpixelrocket\DeployerPHP\Console\HelloCommand;
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

    public function __construct()
    {
        $name = App::getName();
        $version = App::getVersion();
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

        $this->displayBanner();

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
        $envFileStatus = App::getEnvService()->getEnvFileStatus();

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
            '  <fg=yellow>Environment:</> <fg=gray>'.$envFileStatus.'</>',
            '',
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
            $commandInstance = App::build($command);
            $this->add($commandInstance);
        }
    }

}
