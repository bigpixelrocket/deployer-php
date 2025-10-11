<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Process\Process;

/**
 * Service for executing local shell commands with consistent configuration.
 */
final readonly class ProcessService
{
    public function __construct(
        private FilesystemService $fs,
    ) {
    }

    /**
     * Execute a shell command and return the Process instance.
     *
     * @param list<string> $command
     */
    public function run(array $command, string $cwd, float $timeout = 3.0): Process
    {
        if ($command === []) {
            throw new \InvalidArgumentException('Process command cannot be empty');
        }

        if (!$this->fs->isDirectory($cwd)) {
            throw new \InvalidArgumentException("Invalid working directory: {$cwd}");
        }

        $process = new Process($command, $cwd);
        $process->setTimeout($timeout);
        $process->run();

        return $process;
    }
}
