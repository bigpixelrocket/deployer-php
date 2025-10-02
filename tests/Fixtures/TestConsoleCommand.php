<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
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
    ) {
        parent::__construct($container, $env, $inventory);
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
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        if ($this->methodToTest !== '') {
            match ($this->methodToTest) {
                'text' => $this->text(...$this->testArgs),
                'info' => $this->info(...$this->testArgs),
                'note' => $this->note(...$this->testArgs),
                'error' => $this->error(...$this->testArgs),
                'success' => $this->success(...$this->testArgs),
                'warning' => $this->warning(...$this->testArgs),
                'h1' => $this->h1(...$this->testArgs),
                'hr' => $this->hr(),
                'writeln' => $this->writeln(...$this->testArgs),
                'showCommandHint' => $this->showCommandHint(...$this->testArgs),
                'getOptionOrPrompt' => $this->testGetOptionOrPrompt(),
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
        $wasProvided = false;
        $result = $this->getOptionOrPrompt('name', 'Name:', wasProvided: $wasProvided);
        $this->io->text("Result: {$result}, Provided: ".($wasProvided ? 'true' : 'false'));
    }
}
