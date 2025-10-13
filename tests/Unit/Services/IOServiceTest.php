<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Services;

use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('IOService', function () {
    beforeEach(function () {
        $container = mockCommandContainer();
        $this->command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $this->tester = new CommandTester($this->command);
    });

    //
    // Input Gathering - getOptionOrPrompt
    // -------------------------------------------------------------------------------

    it('handles option vs prompt scenarios correctly', function (array $options, string $method, mixed $expected) {
        // ARRANGE
        $this->command->setTestMethod($method);

        // ACT
        $this->tester->execute($options);
        $output = $this->tester->getDisplay();

        // ASSERT
        if (is_bool($expected)) {
            expect($output)->toContain('Result: '.($expected ? 'true' : 'false'));
        } else {
            expect($output)->toContain("Result: {$expected}");
        }
    })->with([
        'string option provided' => [['--name' => 'production'], 'getOptionOrPrompt', 'production'],
        'empty string is valid value' => [['--name' => ''], 'getOptionOrPromptEmpty', ''],
        'no option executes closure' => [[], 'getOptionOrPromptEmpty', 'from-closure'],
        'boolean flag provided' => [['--yes' => true], 'getOptionOrPromptBoolean', true],
        'boolean flag not provided' => [[], 'getOptionOrPromptBoolean', false],
    ]);

    it('supports different return types from prompt closure', function (mixed $value, string $display) {
        // ARRANGE
        $this->command->setTestMethod('getOptionOrPromptTypes', [$value]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain("Result: {$display}");
    })->with([
        'string' => ['text-value', 'text-value'],
        'integer' => [42, '42'],
        'boolean true' => [true, 'true'],
        'boolean false' => [false, 'false'],
        'array' => [['a', 'b'], '["a","b"]'],
    ]);

    //
    // Input Gathering - getValidatedOptionOrPrompt
    // -------------------------------------------------------------------------------

    it('validates CLI options and returns appropriate result', function (array $options, ?string $expected, bool $hasError) {
        // ARRANGE
        $this->command->setTestMethod('getValidatedOptionOrPromptValid');

        // ACT
        $this->tester->execute($options);
        $output = $this->tester->getDisplay();

        // ASSERT
        if ($expected === null) {
            expect($output)->toContain('Result: null');
        } else {
            expect($output)->toContain("Result: {$expected}");
        }

        if ($hasError) {
            expect($output)->toContain('✗');
        }
    })->with([
        'valid CLI option' => [['--name' => 'valid-name'], 'valid-name', false],
        'invalid CLI option (empty)' => [['--name' => ''], null, true],
    ]);

    it('returns null when validator always fails', function () {
        // ARRANGE
        $this->command->setTestMethod('getValidatedOptionOrPromptInvalid');

        // ACT
        $this->tester->execute(['--name' => 'anything']);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✗')
            ->and($output)->toContain('Always invalid')
            ->and($output)->toContain('Result: null');
    });

    //
    // Output Methods - Status Messages
    // -------------------------------------------------------------------------------

    it('displays status messages with correct symbols and colors', function (string $method, string $message, string $symbol) {
        // ARRANGE
        $this->command->setTestMethod($method, [$message]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain($symbol)
            ->and($output)->toContain($message);
    })->with([
        'success' => ['success', 'Server added successfully', '✓'],
        'error' => ['error', 'Connection failed', '✗'],
        'warning' => ['warning', 'Skipping connection check', '⚠'],
        'info' => ['info', 'Configuration loaded', 'ℹ'],
    ]);

    //
    // Output Methods - Formatting
    // -------------------------------------------------------------------------------

    it('writes single and multiple lines correctly', function (string|array $lines, array $expectedContains) {
        // ARRANGE
        $this->command->setTestMethod('writeln', [$lines]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        foreach ($expectedContains as $text) {
            expect($output)->toContain($text);
        }
    })->with([
        'single line' => ['Output line', ['Output line']],
        'multiple lines' => [['First line', 'Second line'], ['First line', 'Second line']],
    ]);

    it('displays visual separators correctly', function (string $method, string $expectedContent) {
        // ARRANGE
        $this->command->setTestMethod($method, $method === 'h1' ? ['Server Configuration'] : []);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain($expectedContent);
    })->with([
        'h1 heading' => ['h1', '▸'],
        'h1 text' => ['h1', 'Server Configuration'],
        'hr separator' => ['hr', '╭───────'],
    ]);

    //
    // Output Methods - Command Hints
    // -------------------------------------------------------------------------------

    it('displays command hint with formatted options', function () {
        // ARRANGE
        $this->command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => '192.168.1.100', 'yes' => true],
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Run non-interactively:')
            ->and($output)->toContain('server:add')
            ->and($output)->toContain('--name')
            ->and($output)->toContain('--host')
            ->and($output)->toContain('--yes');
    });

    it('formats command options correctly and skips null/empty values', function (array $options, array $shouldContain, array $shouldNotContain) {
        // ARRANGE
        $this->command->setTestMethod('showCommandHint', ['server:add', $options]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        foreach ($shouldContain as $text) {
            expect($output)->toContain($text);
        }

        foreach ($shouldNotContain as $text) {
            expect($output)->not->toContain($text);
        }
    })->with([
        'string options' => [
            ['name' => 'server1', 'host' => '192.168.1.1'],
            ['--name', '--host', 'server1', '192.168.1.1'],
            [],
        ],
        'skip null and empty' => [
            ['name' => 'server1', 'host' => null, 'port' => ''],
            ['--name'],
            ['--host', '--port'],
        ],
        'boolean true shown' => [
            ['yes' => true],
            ['--yes'],
            [],
        ],
        'boolean false skipped' => [
            ['yes' => false],
            [],
            ['--yes'],
        ],
    ]);

    //
    // Prompt Methods - Spin
    // -------------------------------------------------------------------------------

    it('executes callback and returns result from promptSpin', function () {
        // ARRANGE
        $this->command->setTestMethod('testPromptSpin');

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Spin result: success');
    });
});
