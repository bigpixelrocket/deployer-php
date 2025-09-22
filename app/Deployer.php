<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Symfony\Component\Console\Application;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

class Deployer extends Application
{
    private SymfonyStyle $io;

    public function __construct()
    {
        $version = $this->getVersionFromComposer();

        parent::__construct('Deployer', $version);

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
        if (class_exists(\Composer\InstalledVersions::class)) {
            try {
                $version = \Composer\InstalledVersions::getPrettyVersion('bigpixelrocket/deployer-php');
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
        $tag = @shell_exec('cd '.escapeshellarg($projectRoot).' && git describe --tags --exact-match 2>/dev/null');
        if ($tag) {
            return trim($tag);
        }

        // Get the latest tag + commit info
        $describe = @shell_exec('cd '.escapeshellarg($projectRoot).' && git describe --tags --always 2>/dev/null');
        if ($describe) {
            return trim($describe);
        }

        // Get current branch + short commit hash
        $branch = @shell_exec('cd '.escapeshellarg($projectRoot).' && git rev-parse --abbrev-ref HEAD 2>/dev/null');
        $commit = @shell_exec('cd '.escapeshellarg($projectRoot).' && git rev-parse --short HEAD 2>/dev/null');

        if ($branch && $commit) {
            return trim($branch).'@'.trim($commit);
        }

        return null;
    }
}
