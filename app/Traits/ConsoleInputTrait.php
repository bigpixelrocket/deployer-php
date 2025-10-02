<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Traits;

use function Laravel\Prompts\text;

/**
 * Console input gathering helpers.
 *
 * Requires the using class to have a `protected InputInterface $input` property.
 */
trait ConsoleInputTrait
{
    /**
     * Get option value or prompt user interactively.
     *
     * Checks if an option was provided via CLI. If yes, returns it.
     * If not, prompts the user interactively using Laravel Prompts.
     *
     * @param string $optionName The option name to check
     * @param string $label The prompt label for interactive input
     * @param string $default Default value for the prompt
     * @param bool $required Whether the input is required
     * @param string $placeholder Placeholder text for the prompt
     * @param-out bool $wasProvided Set to true if option was provided, false if prompted
     *
     * @return string The option value or prompted input
     */
    protected function getOptionOrPrompt(
        string $optionName,
        string $label,
        string $default = '',
        bool $required = true,
        string $placeholder = '',
        ?bool &$wasProvided = null
    ): string {
        $value = $this->input->getOption($optionName);

        if (is_string($value) && $value !== '') {
            $wasProvided = true;

            return $value;
        }

        $wasProvided = false;

        return text(
            label: $label,
            placeholder: $placeholder,
            default: $default,
            required: $required
        );
    }
}
