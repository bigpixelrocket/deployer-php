<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

describe('ConsoleOutputTrait', function () {
    beforeEach(function () {
        $container = mockCommandContainer();
        $this->command = $container->build(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
        $this->tester = new CommandTester($this->command);
    });

    //
    // Raw output
    // -------------------------------------------------------------------------------

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
    // Message helpers
    // -------------------------------------------------------------------------------

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

    //
    // Heading and separator
    // -------------------------------------------------------------------------------

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
        expect($output)->toContain('╭───────');
    });

    //
    // Command hint
    // -------------------------------------------------------------------------------

    it('displays command hint for non-interactive execution', function () {
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

    it('formats command options correctly in hint', function () {
        // ARRANGE
        $this->command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => '192.168.1.100'],
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
