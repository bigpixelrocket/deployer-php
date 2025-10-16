<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

/**
 * Git operations service.
 *
 * Provides utilities for detecting git repository information.
 */
final readonly class GitService
{
    public function __construct(private ProcessService $proc)
    {
    }

    //
    // Git Detection
    // -------------------------------------------------------------------------------

    /**
     * Detect git remote origin URL from a working directory.
     *
     * @param string|null $workingDir Working directory to run git command in (defaults to current)
     * @return string|null The remote URL, or null if not in a git repo or command fails
     */
    public function detectRemoteUrl(?string $workingDir = null): ?string
    {
        return $this->runGitCommand(
            ['git', 'config', '--get', 'remote.origin.url'],
            $workingDir
        );
    }

    /**
     * Detect current git branch name from a working directory.
     *
     * @param string|null $workingDir Working directory to run git command in (defaults to current)
     * @return string|null The branch name, or null if not in a git repo or command fails
     */
    public function detectCurrentBranch(?string $workingDir = null): ?string
    {
        return $this->runGitCommand(
            ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
            $workingDir
        );
    }

    //
    // Helpers
    // -------------------------------------------------------------------------------

    /**
     * Run a git command and return trimmed output or null on failure.
     *
     * @param list<string> $cmd Git command and arguments
     * @param string|null $workingDir Working directory (defaults to current)
     * @return string|null Command output or null on failure
     */
    private function runGitCommand(array $cmd, ?string $workingDir): ?string
    {
        try {
            $cwd = $workingDir ?? getcwd();
            if ($cwd === false) {
                return null;
            }

            $process = $this->proc->run($cmd, $cwd, 2.0);

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
