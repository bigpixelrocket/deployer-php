<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('ConsoleInputTrait', function () {
    beforeEach(function () {
        $container = mockCommandContainer();
        $this->command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $this->tester = new CommandTester($this->command);
    });

    //
    // getOptionOrPrompt
    // -------------------------------------------------------------------------------

    //
    // String Options

    it('returns option value when string option provided', function () {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPrompt');

        // ACT
        $this->tester->execute(['--name' => 'production']);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Result: production');
    });

    it('returns empty string when option is explicitly set to empty', function () {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPromptEmpty');

        // ACT
        $this->tester->execute(['--name' => '']);
        $output = $this->tester->getDisplay();

        // ASSERT - Empty string is a valid value, so closure should NOT execute
        expect($output)->not->toContain('Closure executed')
            ->and($output)->toContain('Result:');
    });

    it('executes closure when string option not provided', function () {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPromptEmpty');

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Closure executed')
            ->and($output)->toContain('Result: from-closure');
    });

    //
    // Boolean Flags

    it('returns true when boolean flag is provided', function () {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPromptBoolean');

        // ACT
        $this->tester->execute(['--yes' => true]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Result: true');
    });

    it('executes closure when boolean flag not provided', function () {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPromptBoolean');

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Result: false');
    });

    //
    // Return Type Flexibility

    it('supports different return types from closure', function (mixed $expected, string $description) {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPromptTypes', [$expected]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        if (is_bool($expected)) {
            expect($output)->toContain('Result: ' . ($expected ? 'true' : 'false'));
        } elseif (is_array($expected)) {
            expect($output)->toContain('Result: ' . json_encode($expected));
        } else {
            expect($output)->toContain("Result: {$expected}");
        }
    })->with([
        'string return' => ['text-value', 'string'],
        'boolean true' => [true, 'boolean'],
        'boolean false' => [false, 'boolean'],
        'integer return' => [42, 'integer'],
        'array return' => [['option1', 'option2'], 'array'],
    ]);

    //
    // Prompt Wrappers
    // -------------------------------------------------------------------------------

    // Note: Spacing suppression is now handled by PrompterService internally.
    // When using MockPrompter in tests, no ANSI sequences are output (as expected).
    // The real PrompterService handles spacing suppression for actual prompts.

    it('promptSpin executes callback and returns result', function () {
        // ARRANGE
        $this->command->setTestMethod('testPromptSpin');

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Spin result: success');
    });
});
