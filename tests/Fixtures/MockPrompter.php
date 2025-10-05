<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

use Bigpixelrocket\DeployerPHP\Services\PrompterService;
use Closure;

/**
 * Mock prompter service for testing.
 *
 * Returns predefined values instead of displaying interactive prompts.
 * Each prompt method pops the next value from its queue.
 */
class MockPrompter extends PrompterService
{
    /**
     * Create a mock prompter with predefined return values.
     *
     * @param array<string> $textQueue
     * @param array<string> $passwordQueue
     * @param array<bool> $confirmQueue
     * @param array<int|string> $selectQueue
     * @param array<array<int|string>> $multiselectQueue
     * @param array<string> $suggestQueue
     * @param array<int|string> $searchQueue
     * @param array<bool> $pauseQueue
     */
    public function __construct(private array $textQueue = [], private array $passwordQueue = [], private array $confirmQueue = [], private array $selectQueue = [], private array $multiselectQueue = [], private array $suggestQueue = [], private array $searchQueue = [], private array $pauseQueue = [])
    {
    }

    //
    // Prompt Methods
    // -------------------------------------------------------------------------------

    /**
     * Return next text value from queue.
     */
    public function text(
        string $label,
        string $placeholder = '',
        string $default = '',
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        if (empty($this->textQueue)) {
            throw new \RuntimeException('MockPrompter: No text values left in queue for prompt: ' . $label);
        }

        return array_shift($this->textQueue);
    }

    /**
     * Return next password value from queue.
     */
    public function password(
        string $label,
        string $placeholder = '',
        bool $required = true,
        mixed $validate = null,
        string $hint = ''
    ): string {
        if (empty($this->passwordQueue)) {
            throw new \RuntimeException('MockPrompter: No password values left in queue for prompt: ' . $label);
        }

        return array_shift($this->passwordQueue);
    }

    /**
     * Return next confirm value from queue.
     */
    public function confirm(
        string $label,
        bool $default = true,
        string $yes = 'Yes',
        string $no = 'No',
        string $hint = ''
    ): bool {
        if (empty($this->confirmQueue)) {
            throw new \RuntimeException('MockPrompter: No confirm values left in queue for prompt: ' . $label);
        }

        return array_shift($this->confirmQueue);
    }

    /**
     * Return next pause value from queue.
     */
    public function pause(string $message = 'Press enter to continue...'): bool
    {
        if (empty($this->pauseQueue)) {
            throw new \RuntimeException('MockPrompter: No pause values left in queue');
        }

        return array_shift($this->pauseQueue);
    }

    /**
     * Return next select value from queue.
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
        if (empty($this->selectQueue)) {
            throw new \RuntimeException('MockPrompter: No select values left in queue for prompt: ' . $label);
        }

        return array_shift($this->selectQueue);
    }

    /**
     * Return next multiselect value from queue.
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
        if (empty($this->multiselectQueue)) {
            throw new \RuntimeException('MockPrompter: No multiselect values left in queue for prompt: ' . $label);
        }

        return array_shift($this->multiselectQueue);
    }

    /**
     * Return next suggest value from queue.
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
        if (empty($this->suggestQueue)) {
            throw new \RuntimeException('MockPrompter: No suggest values left in queue for prompt: ' . $label);
        }

        return array_shift($this->suggestQueue);
    }

    /**
     * Return next search value from queue.
     */
    public function search(
        string $label,
        Closure $options,
        string $placeholder = '',
        int $scroll = 5,
        mixed $validate = null,
        string $hint = ''
    ): int|string {
        if (empty($this->searchQueue)) {
            throw new \RuntimeException('MockPrompter: No search values left in queue for prompt: ' . $label);
        }

        return array_shift($this->searchQueue);
    }

    /**
     * Execute callback and return result (no spinner shown in tests).
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
        // In tests, just execute the callback without the spinner
        return $callback();
    }
}
