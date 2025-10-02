<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Process\Process;

/**
 * Factory for creating Process instances with consistent timeout configuration.
 */
final readonly class ProcessFactory
{
    public function __construct(
        private FilesystemService $fs,
    ) {
    }

    /**
     * Create a new Process with timeout.
     *
     * @param list<string> $command
     */
    public function create(array $command, string $cwd, ?float $timeout = 3.0): Process
    {
        if ($command === []) {
            throw new \InvalidArgumentException('Process command cannot be empty');
        }

        if (!$this->fs->isDirectory($cwd)) {
            throw new \InvalidArgumentException("Invalid working directory: {$cwd}");
        }

        $process = new Process($command, $cwd);
        $process->setTimeout($timeout ?? 3.0);

        return $process;
    }
}
