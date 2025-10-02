<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Traits;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__.'/../../TestHelpers.php';

//
// Test fixtures
// -------------------------------------------------------------------------------

class TestConsoleOutputCommand extends BaseCommand
{
    private string $methodToTest = '';

    private array $testArgs = [];

    public function __construct(
        Container $container,
        EnvService $env,
        InventoryService $inventory,
    ) {
        parent::__construct($container, $env, $inventory);
    }

    public function setTestMethod(string $method, array $args = []): void
    {
        $this->methodToTest = $method;
        $this->testArgs = $args;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setName('test-output')->setDescription('Test console output methods');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Test name option');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Test host option');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->methodToTest !== '') {
            match ($this->methodToTest) {
                'error' => $this->error(...$this->testArgs),
                'success' => $this->success(...$this->testArgs),
                'warning' => $this->warning(...$this->testArgs),
                'h1' => $this->h1(...$this->testArgs),
                'hr' => $this->hr(),
                'text' => $this->text(...$this->testArgs),
                'writeln' => $this->writeln(...$this->testArgs),
                'getOptionOrPrompt' => $this->testGetOptionOrPrompt($input),
                'showCommandHint' => $this->showCommandHint(...$this->testArgs),
                default => null,
            };
        }

        return Command::SUCCESS;
    }

    private function testGetOptionOrPrompt(InputInterface $input): void
    {
        $wasProvided = false;
        $result = $this->getOptionOrPrompt($input, 'name', 'Name:', wasProvided: $wasProvided);
        $this->io->text("Result: {$result}, Provided: ".($wasProvided ? 'true' : 'false'));
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

describe('ConsoleOutputTrait', function () {
    //
    // Status Messages

    it('displays error message with red X symbol', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('error', ['Connection failed']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✗')
            ->and($output)->toContain('Connection failed');
    });

    it('displays error message with optional tip', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('error', ['Connection failed', 'Check your SSH key']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✗')
            ->and($output)->toContain('Connection failed')
            ->and($output)->toContain('Tip:')
            ->and($output)->toContain('Check your SSH key');
    });

    it('displays success message with green checkmark', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('success', ['Server added successfully']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('✓')
            ->and($output)->toContain('Server added successfully');
    });

    it('displays warning message with yellow warning symbol', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('warning', ['Skipping connection check']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('⚠')
            ->and($output)->toContain('Skipping connection check');
    });

    //
    // Output Formatting

    it('displays heading with icon', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('h1', ['Server Configuration']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('▸')
            ->and($output)->toContain('Server Configuration');
    });

    it('displays separator line with box-drawing characters', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('hr');
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('╭───────')
            ->and(strlen($output))->toBeGreaterThan(40);
    });

    it('writes text with proper indentation', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('text', ['Simple text output']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Simple text output');
    });

    it('writes multiple lines of text', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('text', [['Line 1', 'Line 2', 'Line 3']]);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Line 1')
            ->and($output)->toContain('Line 2')
            ->and($output)->toContain('Line 3');
    });

    it('writes single line', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('writeln', ['Output line']);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Output line');
    });

    it('writes multiple lines', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('writeln', [['First line', 'Second line']]);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('First line')
            ->and($output)->toContain('Second line');
    });

    //
    // User Input Helpers

    it('gets option value when provided via CLI', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('getOptionOrPrompt');
        $tester = new CommandTester($command);

        // ACT
        $tester->execute(['--name' => 'production']);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Result: production')
            ->and($output)->toContain('Provided: true');
    });

    //
    // Command Hints

    it('displays command hint for non-interactive execution', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => '192.168.1.100', 'yes' => true],
            ['name' => false, 'host' => false, 'yes' => true],
        ]);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('Next time, run non-interactively:')
            ->and($output)->toContain('server:add')
            ->and($output)->toContain('--name')
            ->and($output)->toContain('--host')
            ->and($output)->toContain('--yes');
    });

    it('highlights prompted options differently in command hint', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => '192.168.1.100'],
            ['name' => true, 'host' => false],
        ]);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('--name')
            ->and($output)->toContain('--host')
            ->and($output)->toContain('prod-server')
            ->and($output)->toContain('192.168.1.100');
    });

    it('skips null and empty values in command hint', function () {
        // ARRANGE
        $container = new Container();
        $command = new TestConsoleOutputCommand($container, mockEnvService(true), mockInventoryService(true));
        $command->setTestMethod('showCommandHint', [
            'server:add',
            ['name' => 'prod-server', 'host' => null, 'port' => ''],
            ['name' => true, 'host' => false, 'port' => false],
        ]);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($output)->toContain('--name')
            ->and($output)->not->toContain('--host')
            ->and($output)->not->toContain('--port');
    });
});
