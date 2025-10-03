<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('ConsoleOutputTrait', function () {
    beforeEach(function () {
        $container = new Container();
        $this->command = new TestConsoleCommand($container, mockEnvService(true), mockInventoryService(true), mockServerRepository());
        $this->tester = new CommandTester($this->command);
    });

    //
    // Basic Output

    it('displays plain text message', function () {
        // ARRANGE
        $this->command->setTestMethod('text', ['Plain text message']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Plain text message');
    });

    //
    // Status Messages

    it('displays info message with cyan info symbol', function () {
        // ARRANGE
        $this->command->setTestMethod('info', ['Information message']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('ℹ')
            ->and($output)->toContain('Information message');
    });

    it('displays note message with cyan info symbol', function () {
        // ARRANGE
        $this->command->setTestMethod('note', ['Note message']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('ℹ')
            ->and($output)->toContain('Note message');
    });

    it('displays error message with red X symbol', function () {
        // ARRANGE
        $this->command->setTestMethod('error', ['Connection failed']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✗')
            ->and($output)->toContain('Connection failed');
    });

    it('displays error message with optional tip', function () {
        // ARRANGE
        $this->command->setTestMethod('error', ['Connection failed', 'Check your SSH key']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✗')
            ->and($output)->toContain('Connection failed')
            ->and($output)->toContain('Tip:')
            ->and($output)->toContain('Check your SSH key');
    });

    it('displays success message with green checkmark', function () {
        // ARRANGE
        $this->command->setTestMethod('success', ['Server added successfully']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✓')
            ->and($output)->toContain('Server added successfully');
    });

    it('displays warning message with yellow warning symbol', function () {
        // ARRANGE
        $this->command->setTestMethod('warning', ['Skipping connection check']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('⚠')
            ->and($output)->toContain('Skipping connection check');
    });

    //
    // Output Formatting

    it('displays heading with icon', function () {
        // ARRANGE
        $this->command->setTestMethod('h1', ['Server Configuration']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('▸')
            ->and($output)->toContain('Server Configuration');
    });

    it('displays separator line with box-drawing characters', function () {
        // ARRANGE
        $this->command->setTestMethod('hr');

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('╭───────')
            ->and(strlen($output))->toBeGreaterThan(40);
    });

    it('writes single line', function () {
        // ARRANGE
        $this->command->setTestMethod('writeln', ['Output line']);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Output line');
    });

    it('writes multiple lines', function () {
        // ARRANGE
        $this->command->setTestMethod('writeln', [['First line', 'Second line']]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('First line')
            ->and($output)->toContain('Second line');
    });

    //
    // Command Hints

    it('displays command hint for non-interactive execution', function () {
        // ARRANGE
        $this->command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => '192.168.1.100', 'yes' => true],
            ['name' => false, 'host' => false, 'yes' => true],
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Next time, run non-interactively:')
            ->and($output)->toContain('server:add')
            ->and($output)->toContain('--name')
            ->and($output)->toContain('--host')
            ->and($output)->toContain('--yes');
    });

    it('highlights prompted options differently in command hint', function () {
        // ARRANGE
        $this->command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => '192.168.1.100'],
            ['name' => true, 'host' => false],
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('--name')
            ->and($output)->toContain('--host')
            ->and($output)->toContain('prod-server')
            ->and($output)->toContain('192.168.1.100');
    });

    it('skips null and empty values in command hint', function () {
        // ARRANGE
        $this->command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => null, 'port' => ''],
            ['name' => true, 'host' => false, 'port' => false],
        ]);

        // ACT
        $this->tester->execute([]);
        $output = $this->tester->getDisplay();

        // ASSERT
        expect($output)->toContain('--name')
            ->and($output)->not->toContain('--host')
            ->and($output)->not->toContain('--port');
    });
});
