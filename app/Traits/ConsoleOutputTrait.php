<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Style\SymfonyStyle;

use function Laravel\Prompts\text;

/**
 * Console output formatting methods for beautiful TUI.
 *
 * Provides consistent styling, status messages, and command hints.
 *
 * Requires the using class to have:
 * - protected SymfonyStyle $io
 */
trait ConsoleOutputTrait
{
    //
    // Status Messages
    // -------------------------------------------------------------------------------

    /**
     * Display an error message with red X and optional tip.
     */
    protected function error(string $message, ?string $tip = null): void
    {
        $output = [
            "<fg=red>✗</> {$message}",
            '',
        ];

        if ($tip !== null) {
            $output[] = "<fg=gray>Tip:</> {$tip}";
            $output[] = '';
        }

        $this->writeln($output);
    }

    /**
     * Display a success message with green checkmark.
     */
    protected function success(string $message): void
    {
        $this->writeln([
            "<fg=green>✓</> {$message}",
            '',
        ]);
    }

    /**
     * Display a warning message with yellow warning symbol.
     */
    protected function warning(string $message): void
    {
        $this->writeln([
            "<fg=yellow>⚠</> {$message}",
            '',
        ]);
    }

    //
    // Output Formatting
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

    /**
     * Write-out styled text lines.
     *
     * @param array<int, string> $lines
     */
    protected function text(string|array $lines): void
    {
        $writeLines = is_array($lines) ? $lines : [$lines];
        foreach ($writeLines as $line) {
            $this->io->text(' '.$line);
        }
    }

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
    // User Input Helpers
    // -------------------------------------------------------------------------------

    /**
     * Get option value or prompt user interactively.
     *
     * Checks if an option was provided via CLI. If yes, returns the value and sets
     * wasProvided to true. If not, prompts user interactively and sets wasProvided to false.
     *
     * @param bool $wasProvided Set to true if option was provided, false if prompted
     * @return string The option value (from CLI or prompt)
     */
    protected function getOptionOrPrompt(
        InputInterface $input,
        string $optionName,
        string $label,
        string $placeholder = '',
        bool $required = true,
        ?string $default = null,
        bool &$wasProvided = false
    ): string {
        /** @var ?string $value */
        $value = $input->getOption($optionName);

        if ($value !== null && $value !== '') {
            $wasProvided = true;

            return $value;
        }

        $wasProvided = false;

        return text(
            label: $label,
            placeholder: $placeholder,
            default: $default ?? '',
            required: $required
        );
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
