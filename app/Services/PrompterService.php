<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Services;

use Closure;

use function Laravel\Prompts\confirm;
use function Laravel\Prompts\multiselect;
use function Laravel\Prompts\password;
use function Laravel\Prompts\pause;
use function Laravel\Prompts\search;
use function Laravel\Prompts\select;
use function Laravel\Prompts\spin;
use function Laravel\Prompts\suggest;
use function Laravel\Prompts\text;

/**
 * User input prompting service.
 *
 * Wraps Laravel Prompts functions for dependency injection and testability.
 */
class PrompterService
{
    //
    // Prompt Methods
    // -------------------------------------------------------------------------------

    /**
     * Prompt for text input.
     */
    public function text(
        string $label,
        string $placeholder = '',
        string $default = '',
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        $this->suppressPromptSpacing();

        return text(
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
     */
    public function password(
        string $label,
        string $placeholder = '',
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        $this->suppressPromptSpacing();

        return password(
            label: $label,
            placeholder: $placeholder,
            required: $required,
            validate: $validate,
            hint: $hint
        );
    }

    /**
     * Prompt for yes/no confirmation.
     */
    public function confirm(
        string $label,
        bool $default = true,
        string $yes = 'Yes',
        string $no = 'No',
        string $hint = ''
    ): bool {
        $this->suppressPromptSpacing();

        return confirm(
            label: $label,
            default: $default,
            yes: $yes,
            no: $no,
            hint: $hint
        );
    }

    /**
     * Display a message and wait for user to press Enter.
     */
    public function pause(string $message = 'Press enter to continue...'): bool
    {
        $this->suppressPromptSpacing();

        return pause($message);
    }

    /**
     * Prompt for single selection from options.
     *
     * @param array<int|string, string> $options
     */
    public function select(
        string $label,
        array $options,
        int|string|null $default = null,
        int $scroll = 5,
        mixed $validate = null,
        string $hint = ''
    ): int|string {
        $this->suppressPromptSpacing();

        return select(
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
     * @param array<int|string, string> $options
     * @param array<int|string> $default
     *
     * @return array<int|string>
     */
    public function multiselect(
        string $label,
        array $options,
        array $default = [],
        int $scroll = 5,
        bool $required = false,
        mixed $validate = null,
        string $hint = ''
    ): array {
        $this->suppressPromptSpacing();

        return multiselect(
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
     * @param array<string>|Closure $options
     */
    public function suggest(
        string $label,
        array|Closure $options,
        string $placeholder = '',
        string $default = '',
        int $scroll = 5,
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        $this->suppressPromptSpacing();

        return suggest(
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
     */
    public function search(
        string $label,
        Closure $options,
        string $placeholder = '',
        int $scroll = 5,
        mixed $validate = null,
        string $hint = ''
    ): int|string {
        $this->suppressPromptSpacing();

        return search(
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
     * @param Closure(): T $callback
     *
     * @return T
     */
    public function spin(
        Closure $callback,
        string $message = 'Loading...'
    ): mixed {
        return spin(
            callback: $callback,
            message: $message
        );
    }

    //
    // Private Helpers
    // -------------------------------------------------------------------------------

    /**
     * Remove the annoying newline that Laravel Prompts adds before each prompt.
     *
     * Uses ANSI escape sequence to move cursor up one line and clear it.
     */
    private function suppressPromptSpacing(): void
    {
        // Move cursor up one line and clear it
        // This compensates for the newline Laravel Prompts adds
        echo "\033[1A\033[2K";
    }
}
