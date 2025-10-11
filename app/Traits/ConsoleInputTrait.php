<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

use Closure;

/**
 * Console input gathering helpers.
 *
 * Requires the using class to have:
 * - `protected InputInterface $input` property
 * - `protected PrompterService $prompter` property
 * - `getDefinition()` method (typically from extending Command)
 */
trait ConsoleInputTrait
{
    /**
     * Get option value or prompt user interactively.
     *
     * Checks if an option was provided via CLI. If yes, returns it.
     * If not, prompts the user interactively using a custom closure.
     *
     * @template T
     *
     * @param string $optionName The option name to check
     * @param Closure(): T $promptCallback Closure that performs the actual prompting (e.g., text(), select(), confirm())
     *
     * @return string|bool|T The option value or prompted input
     *
     * @example
     * // Text input
     * $name = $this->getOptionOrPrompt(
     *     'name',
     *     fn() => text('Server name:', placeholder: 'web1')
     * );
     *
     * // Boolean flag (VALUE_NONE option)
     * $skip = $this->getOptionOrPrompt(
     *     'skip',
     *     fn() => confirm('Skip verification?', default: false)
     * );
     *
     * // Select input
     * $env = $this->getOptionOrPrompt(
     *     'environment',
     *     fn() => select('Environment:', ['dev', 'staging', 'prod'])
     * );
     */
    protected function getOptionOrPrompt(
        string $optionName,
        Closure $promptCallback
    ): mixed {
        $value = $this->input->getOption($optionName);

        // For boolean flags (VALUE_NONE options), check if actually provided
        if (is_bool($value)) {
            // Build list of option flags to check
            $optionFlags = ['--' . $optionName];

            // Try to find short flag from option definition
            try {
                $inputDef = $this->getDefinition();
                if ($inputDef->hasOption($optionName)) {
                    $option = $inputDef->getOption($optionName);
                    if ($option->getShortcut() !== null) {
                        $optionFlags[] = '-' . $option->getShortcut();
                    }
                }
            } catch (\Throwable) {
                // Ignore errors getting shortcut
            }

            // Check if flag was actually provided (works for both CLI and tests with ArrayInput)
            $wasProvided = $this->input->hasParameterOption($optionFlags, true);

            if ($wasProvided) {
                // Flag was provided - return its value (true for CLI flags, could be false in tests)
                return $value;
            }

            // Flag was not provided - prompt in interactive mode, return false otherwise
            if ($this->input->isInteractive()) {
                return $promptCallback();
            }

            return false;
        }

        // Handle string options (including empty strings)
        // null means option was not provided, empty string means it was provided but empty
        if ($value !== null) {
            return $value;
        }

        // Prompt user interactively
        return $promptCallback();
    }

    //
    // Laravel Prompts Wrappers
    // -------------------------------------------------------------------------------

    /**
     * Prompt for text input.
     *
     * @param string $label The question to display
     * @param string $placeholder Optional placeholder text
     * @param string $default Optional default value
     * @param bool $required Whether input is required
     * @param mixed $validate Optional validation callback
     * @param string $hint Optional hint text
     *
     * @return string The user's input
     */
    protected function promptText(
        string $label,
        string $placeholder = '',
        string $default = '',
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        return $this->prompter->text(
            label: $label,
            placeholder: $placeholder,
            default: $default,
            required: $required,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Prompt for password input.
     *
     * @param string $label The question to display
     * @param string $placeholder Optional placeholder text
     * @param bool $required Whether input is required
     * @param mixed $validate Optional validation callback
     * @param string $hint Optional hint text
     *
     * @return string The user's password input
     */
    protected function promptPassword(
        string $label,
        string $placeholder = '',
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        return $this->prompter->password(
            label: $label,
            placeholder: $placeholder,
            required: $required,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Prompt for yes/no confirmation.
     *
     * @param string $label The question to display
     * @param bool $default Default value (true = yes, false = no)
     * @param string $yes Text for "yes" option
     * @param string $no Text for "no" option
     * @param string $hint Optional hint text
     *
     * @return bool True if confirmed, false otherwise
     */
    protected function promptConfirm(
        string $label,
        bool $default = true,
        string $yes = 'Yes',
        string $no = 'No',
        string $hint = ''
    ): bool {
        return $this->prompter->confirm(
            label: $label,
            default: $default,
            yes: $yes,
            no: $no,
            hint: $hint
        );
    }

    /**
     * Display a message and wait for user to press Enter.
     *
     * @param string $message The message to display
     *
     * @return bool Always returns a boolean
     */
    protected function promptPause(string $message = 'Press enter to continue...'): bool
    {
        return $this->prompter->pause($message);
    }

    /**
     * Prompt for single selection from options.
     *
     * @param string $label The question to display
     * @param array<int|string, string> $options Available options
     * @param int|string|null $default Default option key
     * @param int $scroll Number of visible options
     * @param mixed $validate Optional validation callback
     * @param string $hint Optional hint text
     *
     * @return int|string The selected option key
     */
    protected function promptSelect(
        string $label,
        array $options,
        int|string|null $default = null,
        int $scroll = 5,
        mixed $validate = null,
        string $hint = ''
    ): int|string {
        return $this->prompter->select(
            label: $label,
            options: $options,
            default: $default,
            scroll: $scroll,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Prompt for multiple selections from options.
     *
     * @param string $label The question to display
     * @param array<int|string, string> $options Available options
     * @param array<int|string> $default Default selected option keys
     * @param int $scroll Number of visible options
     * @param bool $required Whether at least one selection is required
     * @param mixed $validate Optional validation callback
     * @param string $hint Optional hint text
     *
     * @return array<int|string> The selected option keys
     */
    protected function promptMultiselect(
        string $label,
        array $options,
        array $default = [],
        int $scroll = 5,
        bool $required = false,
        mixed $validate = null,
        string $hint = ''
    ): array {
        return $this->prompter->multiselect(
            label: $label,
            options: $options,
            default: $default,
            scroll: $scroll,
            required: $required,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Prompt with autocomplete suggestions.
     *
     * @param string $label The question to display
     * @param array<string>|Closure $options Available suggestions (array or closure)
     * @param string $placeholder Optional placeholder text
     * @param string $default Optional default value
     * @param int $scroll Number of visible suggestions
     * @param bool $required Whether input is required
     * @param mixed $validate Optional validation callback
     * @param string $hint Optional hint text
     *
     * @return string The user's input
     */
    protected function promptSuggest(
        string $label,
        array|Closure $options,
        string $placeholder = '',
        string $default = '',
        int $scroll = 5,
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        return $this->prompter->suggest(
            label: $label,
            options: $options,
            placeholder: $placeholder,
            default: $default,
            scroll: $scroll,
            required: $required,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Prompt with searchable options.
     *
     * @param string $label The question to display
     * @param Closure $options Closure that accepts search string and returns filtered options
     * @param string $placeholder Optional placeholder text
     * @param int $scroll Number of visible options
     * @param mixed $validate Optional validation callback
     * @param string $hint Optional hint text
     *
     * @return int|string The selected option key
     */
    protected function promptSearch(
        string $label,
        Closure $options,
        string $placeholder = '',
        int $scroll = 5,
        mixed $validate = null,
        string $hint = ''
    ): int|string {
        return $this->prompter->search(
            label: $label,
            options: $options,
            placeholder: $placeholder,
            scroll: $scroll,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Display a loading spinner during long operations.
     *
     * @template T
     *
     * @param Closure(): T $callback Operation to perform
     * @param string $message Message to display
     *
     * @return T Result from the callback
     */
    protected function promptSpin(
        Closure $callback,
        string $message = 'Loading...'
    ): mixed {
        return $this->prompter->spin(
            callback: $callback,
            message: $message
        );
    }
}
