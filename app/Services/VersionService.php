<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Composer\InstalledVersions;
use Symfony\Component\Process\Process;

/**
 * Handles version detection from multiple sources with fallback chain.
 *
 * Priority Order:
 * 1. Composer InstalledVersions API
 * 2. Git tag detection
 * 3. Fallback to 'dev-main'
 */
class VersionService
{
    /**
     * Create a VersionService configured with process and filesystem services and package/fallback version.
     *
     * @param string $packageName The Composer package name to query for version information (default 'bigpixelrocket/deployer-php').
     * @param string $fallbackVersion The version to return when no other source provides one (default 'dev-main').
     */
    public function __construct(
        private readonly ProcessService $proc,
        private readonly FilesystemService $fs,
        private readonly string $packageName = 'bigpixelrocket/deployer-php',
        private readonly string $fallbackVersion = 'dev-main'
    ) {
    }

    /**
     * Get version from all available sources with fallback chain.
     */
    public function getVersion(): string
    {
        // Try Composer's InstalledVersions API first
        $composerVersion = $this->getVersionFromComposer();
        if ($composerVersion !== null) {
            return $composerVersion;
        }

        // Try to get version from git next
        $gitVersion = $this->getVersionFromGit();
        if ($gitVersion !== null) {
            return $gitVersion;
        }

        // Default fallback
        return $this->fallbackVersion;
    }

    //
    // Version Detection Methods
    // -------------------------------------------------------------------------------

    /**
     * Get version from Composer's runtime API.
     */
    public function getVersionFromComposer(): ?string
    {
        if (!class_exists(InstalledVersions::class)) {
            return null;
        }

        try {
            $version = InstalledVersions::getPrettyVersion($this->packageName);
            return $version;
        } catch (\OutOfBoundsException) {
            // Package not found in installed.json, continue to fallbacks
            return null;
        }
    }

    /**
     * Get version from git repository using multiple strategies.
     */
    public function getVersionFromGit(?string $projectRoot = null): ?string
    {
        $projectRoot ??= $this->fs->getParentDirectory(__DIR__, 2);

        // Check if we're in a git repository
        if (!$this->isGitRepository($projectRoot)) {
            return null;
        }

        // Try exact tag match first
        $exactTag = $this->getExactGitTag($projectRoot);
        if ($exactTag !== null) {
            return $exactTag;
        }

        // Try git describe with tags
        $describeVersion = $this->getGitDescribeVersion($projectRoot);
        if ($describeVersion !== null) {
            return $describeVersion;
        }

        // Fallback to branch + commit
        return $this->getBranchWithCommit($projectRoot);
    }

    //
    // Git Detection Helpers
    // -------------------------------------------------------------------------------

    /**
     * Check if directory is a git repository.
     */
    public function isGitRepository(string $projectRoot): bool
    {
        return $this->fs->isDirectory($projectRoot . '/.git');
    }

    /**
     * Retrieve the exact Git tag name that points to HEAD, if present.
     *
     * @param string $projectRoot Path to the Git repository root.
     * @return string|null The exact tag name that points to HEAD, or `null` if HEAD is not tagged or an error occurs.
     */
    public function getExactGitTag(string $projectRoot): ?string
    {
        try {
            $process = $this->proc->run(['git', 'describe', '--tags', '--exact-match'], $projectRoot);

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
        } catch (\Exception) {
            // Directory doesn't exist or other process error
        }

        return null;
    }

    /**
     * Determine a human-readable Git reference for the repository at the given path.
     *
     * Attempts to run `git describe --tags --always` and returns the trimmed output on success.
     *
     * @param string $projectRoot Path to the repository root where the Git command will run.
     * @return string|null The described reference (tag, tag+commit, or short commit) if available, `null` otherwise.
     */
    public function getGitDescribeVersion(string $projectRoot): ?string
    {
        try {
            $process = $this->proc->run(['git', 'describe', '--tags', '--always'], $projectRoot);

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }
        } catch (\Exception) {
            // Directory doesn't exist or other process error
        }

        return null;
    }

    /**
     * Produce the current Git branch combined with the short commit hash.
     *
     * Returns a string in the format "branch@commit" where `branch` is the current branch name
     * and `commit` is the short commit hash. Returns `null` if the repository information cannot
     * be determined or an error occurs.
     *
     * @param string $projectRoot Path to the repository root.
     * @return string|null The branch and short commit separated by '@', or `null` if unavailable.
     */
    public function getBranchWithCommit(string $projectRoot): ?string
    {
        try {
            $branchProcess = $this->proc->run(['git', 'rev-parse', '--abbrev-ref', 'HEAD'], $projectRoot);
            $commitProcess = $this->proc->run(['git', 'rev-parse', '--short', 'HEAD'], $projectRoot);

            if ($branchProcess->isSuccessful() && $commitProcess->isSuccessful()) {
                $branch = trim($branchProcess->getOutput());
                $commit = trim($commitProcess->getOutput());
                return $branch . '@' . $commit;
            }
        } catch (\Exception) {
            // Directory doesn't exist or other process error
        }

        return null;
    }
}