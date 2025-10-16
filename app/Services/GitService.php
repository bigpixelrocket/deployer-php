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
        try {
            $cwd = $workingDir ?? getcwd();
            if ($cwd === false) {
                return null;
            }

            $process = $this->proc->run(
                ['git', 'config', '--get', 'remote.origin.url'],
                $cwd,
                2.0
            );

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }

    /**
     * Detect current git branch name from a working directory.
     *
     * @param string|null $workingDir Working directory to run git command in (defaults to current)
     * @return string|null The branch name, or null if not in a git repo or command fails
     */
    public function detectCurrentBranch(?string $workingDir = null): ?string
    {
        try {
            $cwd = $workingDir ?? getcwd();
            if ($cwd === false) {
                return null;
            }

            $process = $this->proc->run(
                ['git', 'rev-parse', '--abbrev-ref', 'HEAD'],
                $cwd,
                2.0
            );

            if ($process->isSuccessful()) {
                return trim($process->getOutput());
            }

            return null;
        } catch (\Exception) {
            return null;
        }
    }
}
