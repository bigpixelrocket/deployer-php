<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\PrompterService;

/**
 * Tests for PrompterService spacing suppression.
 *
 * Most prompt functionality is tested via integration tests that use MockPrompter.
 * This test verifies the real PrompterService properly suppresses Laravel Prompts spacing.
 */
describe('PrompterService', function () {
    it('suppresses spacing with ANSI escape sequences before prompts', function () {
        // ARRANGE
        $expectedAnsi = "\033[1A\033[2K"; // Move up + clear line
        $service = new PrompterService();

        // ACT
        // Capture raw output including ANSI sequences using output buffering
        ob_start();

        try {
            // Call a prompt method which will output ANSI then fail (non-interactive mode)
            // We're only testing that ANSI is output, not the actual prompt
            $service->text('Test prompt', required: true);
        } catch (Throwable) {
            // Expected to fail in non-interactive mode, but ANSI was already output
        }

        $output = ob_get_clean();

        // ASSERT
        // Verify the ANSI escape sequence was output for spacing suppression
        expect($output)->toContain($expectedAnsi);
    });

    it('suppresses spacing for all prompt types', function (string $method) {
        // ARRANGE
        $expectedAnsi = "\033[1A\033[2K";
        $service = new PrompterService();

        // ACT
        ob_start();

        try {
            // Call each prompt method to verify ANSI output
            match ($method) {
                'text' => $service->text('Label'),
                'password' => $service->password('Label'),
                'confirm' => $service->confirm('Label'),
                'pause' => $service->pause(),
                'select' => $service->select('Label', ['a' => 'Option A']),
                'multiselect' => $service->multiselect('Label', ['a' => 'Option A']),
                'suggest' => $service->suggest('Label', ['option']),
                'search' => $service->search('Label', fn () => ['a' => 'Option A']),
                default => throw new \InvalidArgumentException("Unknown method: {$method}")
            };
        } catch (Throwable) {
            // Expected to fail in non-interactive mode
        }

        $output = ob_get_clean();

        // ASSERT
        expect($output)->toContain($expectedAnsi);
    })->with([
        'text',
        'password',
        'confirm',
        'pause',
        'select',
        'multiselect',
        'suggest',
        'search',
    ]);

    it('does not suppress spacing for spin method', function () {
        // ARRANGE
        $expectedAnsi = "\033[1A\033[2K";
        $service = new PrompterService();
        $callbackExecuted = false;

        // ACT
        ob_start();

        $result = $service->spin(
            callback: function () use (&$callbackExecuted) {
                $callbackExecuted = true;
                return 'result';
            },
            message: 'Loading...'
        );

        $output = ob_get_clean();

        // ASSERT
        // spin() doesn't call suppressPromptSpacing() - verify no ANSI output
        expect($output)->not->toContain($expectedAnsi)
            ->and($result)->toBe('result')
            ->and($callbackExecuted)->toBeTrue();
    });
});
