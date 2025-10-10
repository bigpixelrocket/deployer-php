<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

/**
 * Console output formatting methods.
 *
 * Requires the using class to have a `protected SymfonyStyle $io` property.
 */
trait ConsoleOutputTrait
{
    //
    // Raw output
    // -------------------------------------------------------------------------------

    /**
     * Write-out multiple lines.
     *
     * @param array<int, string> $lines
     */
    protected function writeln(string|array $lines): void
    {
        $writeLines = is_array($lines) ? $lines : [$lines];
        foreach ($writeLines as $line) {
            $this->io->writeln(' '.$line);
        }
    }

    //
    // Message helpers
    // -------------------------------------------------------------------------------

    /**
     * Display an info message with cyan info symbol.
     */
    protected function info(string $message): void
    {
        $this->writeln("<fg=cyan>ℹ {$message}</>");
    }

    /**
     * Display a success message with green checkmark.
     */
    protected function success(string $message): void
    {
        $this->writeln("<fg=green>✓ {$message}</>");
    }

    /**
     * Display a warning message with yellow warning symbol.
     */
    protected function warning(string $message): void
    {
        $this->writeln("<fg=yellow>⚠ {$message}</>");
    }

    /**
     * Display an error message with red X.
     */
    protected function error(string $message): void
    {
        $this->writeln("<fg=red>✗ {$message}</>");
    }

    //
    // Heading and separator
    // -------------------------------------------------------------------------------

    /**
     * Write-out a heading.
     */
    protected function h1(string $text): void
    {
        $this->writeln([
            '<fg=bright-blue>▸ </><fg=cyan;options=bold>'.$text.'</>',
            '',
        ]);
    }

    /**
     * Write-out a separator line.
     */
    protected function hr(): void
    {
        $this->writeln([
            '<fg=cyan;options=bold>╭────────</><fg=blue;options=bold>──────────</><fg=bright-blue;options=bold>──────────</><fg=magenta;options=bold>──────────</><fg=gray;options=bold>─────────</>',
            '',
        ]);
    }

    //
    // Command hint
    // -------------------------------------------------------------------------------

    /**
     * Display a command replay hint showing how to run non-interactively.
     *
     * @param array<string, mixed> $options Array of option name => value pairs
     */
    protected function showCommandHint(string $commandName, array $options): void
    {
        $this->writeln('<fg=cyan>◆ Run non-interactively:</>');
        $this->writeln('');

        //
        // Build command options

        $parts = [];
        foreach ($options as $optionName => $value) {
            if ($value === null || $value === '') {
                continue;
            }

            // Format the option
            $optionFlag = '--'.$optionName;
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = $optionFlag;
                }
            } else {
                $stringValue = is_scalar($value) ? (string) $value : '';
                $escapedValue = escapeshellarg($stringValue);
                $parts[] = "{$optionFlag}={$escapedValue}";
            }
        }

        //
        // Display command hint

        $this->writeln("  <fg=gray>vendor/bin/deployer {$commandName} \\ </>");

        foreach ($parts as $index => $part) {
            $last = $index === count($parts) - 1;
            $this->writeln("  <fg=gray>  {$part}</>".($last ? '' : '<fg=gray> \\ </>'));
        }
    }
}
