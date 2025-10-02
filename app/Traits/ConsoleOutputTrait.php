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
     * Display a plain text message.
     */
    protected function text(string $message): void
    {
        $this->writeln($message);
    }

    /**
     * Display an info message with cyan info symbol.
     */
    protected function info(string $message): void
    {
        $this->writeln("<fg=cyan>ℹ</> {$message}");
    }

    /**
     * Display a note message with cyan info symbol (alias for info).
     */
    protected function note(string $message): void
    {
        $this->info($message);
    }

    /**
     * Display a success message with green checkmark.
     */
    protected function success(string $message): void
    {
        $this->writeln("<fg=green>✓</> {$message}");
    }

    /**
     * Display a warning message with yellow warning symbol.
     */
    protected function warning(string $message): void
    {
        $this->writeln("<fg=yellow>⚠</> {$message}");
    }

    /**
     * Display an error message with red X and optional tip.
     */
    protected function error(string $message, ?string $tip = null): void
    {
        $output = ["<fg=red>✗</> {$message}"];

        if ($tip !== null) {
            $output[] = "<fg=gray>Tip:</> {$tip}";
        }

        $this->writeln($output);
    }

    //
    // Heading and separator
    // -------------------------------------------------------------------------------

    /**
     * Write-out a heading.
     */
    protected function h1(string $text): void
    {
        $this->writeln('<fg=bright-blue>▸ </><fg=cyan>'.$text.'</>');
    }

    /**
     * Write-out a separator line.
     */
    protected function hr(): void
    {
        $this->writeln([
            '<fg=cyan>╭───────</><fg=blue>─────────</><fg=bright-blue>─────────</><fg=magenta>─────────</><fg=gray>────────</>',
            '',
        ]);
    }

    //
    // Command Hints
    // -------------------------------------------------------------------------------

    /**
     * Display a command replay hint showing how to run non-interactively.
     *
     * @param array<string, mixed> $options Array of option name => value pairs
     * @param array<string, bool> $provided Array of option name => was provided (true) or prompted (false)
     */
    protected function showCommandHint(string $commandName, array $options, array $provided): void
    {
        $this->writeln('<fg=cyan>💡 Next time, run non-interactively:</>');
        $this->writeln('');

        // Build command parts
        $parts = [$commandName];

        foreach ($options as $optionName => $value) {
            $wasProvided = $provided[$optionName] ?? false;
            $color = $wasProvided ? 'gray' : 'bright-yellow';

            if ($value === null || $value === '') {
                continue;
            }

            // Format the option
            $optionFlag = '--'.$optionName;
            if (is_bool($value)) {
                if ($value) {
                    $parts[] = "<fg={$color}>{$optionFlag}</>";
                }
            } else {
                $stringValue = is_scalar($value) ? (string) $value : '';
                $escapedValue = escapeshellarg($stringValue);
                $parts[] = "<fg={$color}>{$optionFlag}={$escapedValue}</>";
            }
        }

        $command = implode(' ', $parts);
        $this->writeln("  {$command}");
        $this->writeln('');
    }
}
