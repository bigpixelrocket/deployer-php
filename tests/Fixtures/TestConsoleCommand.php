<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test fixture for BaseCommand trait testing.
 *
 * Supports testing both ConsoleInputTrait and ConsoleOutputTrait methods.
 */
class TestConsoleCommand extends BaseCommand
{
    private string $methodToTest = '';

    private array $testArgs = [];

    public function __construct(
        Container $container,
        EnvService $env,
        InventoryService $inventory,
        ServerRepository $servers,
    ) {
        parent::__construct($container, $env, $inventory, $servers);
    }

    /**
     * Configure the test method to execute.
     */
    public function setTestMethod(string $method, array $args = []): void
    {
        $this->methodToTest = $method;
        $this->testArgs = $args;
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setName('test-console')->setDescription('Test console trait methods');
        $this->addOption('name', null, InputOption::VALUE_REQUIRED, 'Test name option');
        $this->addOption('host', null, InputOption::VALUE_REQUIRED, 'Test host option');
        $this->addOption('yes', 'y', InputOption::VALUE_NONE, 'Test yes flag');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->methodToTest !== '') {
            match ($this->methodToTest) {
                'info' => $this->info(...$this->testArgs),
                'error' => $this->error(...$this->testArgs),
                'success' => $this->success(...$this->testArgs),
                'warning' => $this->warning(...$this->testArgs),
                'h1' => $this->h1(...$this->testArgs),
                'hr' => $this->hr(),
                'writeln' => $this->writeln(...$this->testArgs),
                'showCommandHint' => $this->showCommandHint(...$this->testArgs),
                'getOptionOrPrompt' => $this->testGetOptionOrPrompt(),
                'getOptionOrPromptEmpty' => $this->testGetOptionOrPromptEmpty(),
                'getOptionOrPromptBoolean' => $this->testGetOptionOrPromptBoolean(),
                'getOptionOrPromptTypes' => $this->testGetOptionOrPromptTypes(),
                'testPromptSpin' => $this->testPromptSpinWrapper(),
                default => null,
            };
        }

        return Command::SUCCESS;
    }

    /**
     * Test helper for getOptionOrPrompt method.
     */
    private function testGetOptionOrPrompt(): void
    {
        $result = $this->getOptionOrPrompt(
            'name',
            fn () => $this->promptText(label: 'Name:', required: true)
        );
        $this->io->text("Result: {$result}");
    }

    /**
     * Test getOptionOrPrompt with empty string handling.
     */
    private function testGetOptionOrPromptEmpty(): void
    {
        $closureExecuted = false;
        $result = $this->getOptionOrPrompt(
            'name',
            function () use (&$closureExecuted) {
                $closureExecuted = true;

                return 'from-closure';
            }
        );

        if ($closureExecuted) {
            $this->io->text('Closure executed');
        }
        $this->io->text("Result: {$result}");
    }

    /**
     * Test getOptionOrPrompt with boolean flag.
     */
    private function testGetOptionOrPromptBoolean(): void
    {
        $result = $this->getOptionOrPrompt(
            'yes',
            fn () => false
        );
        $this->io->text('Result: '.($result ? 'true' : 'false'));
    }

    /**
     * Test getOptionOrPrompt with different return types.
     */
    private function testGetOptionOrPromptTypes(): void
    {
        $expected = $this->testArgs[0] ?? 'default';

        $result = $this->getOptionOrPrompt(
            'name',
            fn () => $expected
        );

        if (is_bool($result)) {
            $this->io->text('Result: '.($result ? 'true' : 'false'));
        } elseif (is_array($result)) {
            $this->io->text('Result: '.json_encode($result));
        } else {
            $this->io->text("Result: {$result}");
        }
    }

    /**
     * Test promptSpin wrapper.
     */
    private function testPromptSpinWrapper(): void
    {
        $result = $this->promptSpin(
            fn () => 'success',
            'Testing...'
        );

        $this->io->text("Spin result: {$result}");
    }
}
