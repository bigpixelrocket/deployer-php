<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Symfony\Component\Process\Process;

/**
 * Factory for creating Process instances with consistent timeout configuration.
 */
final class ProcessFactory
{
    /**
     * Create a new Process with timeout.
     *
     * @param array<string> $command
     */
    public function create(array $command, string $cwd, ?float $timeout = 3.0): Process
    {
        $process = new Process($command, $cwd);
        $process->setTimeout($timeout);

        return $process;
    }
}
