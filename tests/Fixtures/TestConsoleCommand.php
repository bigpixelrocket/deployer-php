<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Repositories\SiteRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\Services\IOService;
use Bigpixelrocket\DeployerPHP\Services\ProcessService;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Bigpixelrocket\DeployerPHP\Traits\ServerHelpersTrait;
use Bigpixelrocket\DeployerPHP\Traits\SiteHelpersTrait;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Input\InputOption;
use Symfony\Component\Console\Output\OutputInterface;

/**
 * Test fixture for BaseCommand testing.
 *
 * Supports testing IOService and ServerHelpersTrait methods.
 */
class TestConsoleCommand extends BaseCommand
{
    use ServerHelpersTrait;
    use SiteHelpersTrait;
    private string $methodToTest = '';

    private array $testArgs = [];

    /**
     * Create a TestConsoleCommand instance with the required service and repository dependencies.
     *
     * @param Container $container Dependency injection container.
     * @param EnvService $env Environment service.
     * @param InventoryService $inventory Inventory management service.
     * @param IOService $io I/O service for console operations.
     * @param ProcessService $proc Process execution service.
     * @param ServerRepository $servers Repository for server records.
     * @param SiteRepository $sites Repository for site records.
     * @param SSHService $ssh SSH service for remote execution.
     */
    public function __construct(
        Container $container,
        EnvService $env,
        InventoryService $inventory,
        IOService $io,
        ProcessService $proc,
        ServerRepository $servers,
        SiteRepository $sites,
        SSHService $ssh,
    ) {
        parent::__construct($container, $env, $inventory, $io, $proc, $servers, $sites, $ssh);
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
                'info' => $this->io->info(...$this->testArgs),
                'error' => $this->io->error(...$this->testArgs),
                'success' => $this->io->success(...$this->testArgs),
                'warning' => $this->io->warning(...$this->testArgs),
                'h1' => $this->io->h1(...$this->testArgs),
                'hr' => $this->io->hr(),
                'writeln' => $this->io->writeln(...$this->testArgs),
                'showCommandHint' => $this->io->showCommandHint(...$this->testArgs),
                'displayServerDeets' => $this->displayServerDeets(...$this->testArgs),
                'displaySiteDeets' => $this->displaySiteDeets(...$this->testArgs),
                'getOptionOrPrompt' => $this->testGetOptionOrPrompt(),
                'getOptionOrPromptEmpty' => $this->testGetOptionOrPromptEmpty(),
                'getOptionOrPromptBoolean' => $this->testGetOptionOrPromptBoolean(),
                'getOptionOrPromptTypes' => $this->testGetOptionOrPromptTypes(),
                'getValidatedOptionOrPromptValid' => $this->testGetValidatedOptionOrPromptValid(),
                'getValidatedOptionOrPromptInvalid' => $this->testGetValidatedOptionOrPromptInvalid(),
                'testPromptSpin' => $this->testPromptSpinWrapper(),
                'promptText' => $this->testPromptTextWrapper(),
                'promptPassword' => $this->testPromptPasswordWrapper(),
                'promptConfirm' => $this->testPromptConfirmWrapper(),
                'promptPause' => $this->testPromptPauseWrapper(),
                'promptSelect' => $this->testPromptSelectWrapper(),
                'promptMultiselect' => $this->testPromptMultiselectWrapper(),
                'promptSuggest' => $this->testPromptSuggestWrapper(),
                'promptSearch' => $this->testPromptSearchWrapper(),
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
        $result = $this->io->getOptionOrPrompt(
            'name',
            fn () => $this->io->promptText(label: 'Name:', required: true)
        );
        $this->io->writeln("Result: {$result}");
    }

    /**
     * Test getOptionOrPrompt with empty string handling.
     */
    private function testGetOptionOrPromptEmpty(): void
    {
        $closureExecuted = false;
        $result = $this->io->getOptionOrPrompt(
            'name',
            function () use (&$closureExecuted) {
                $closureExecuted = true;

                return 'from-closure';
            }
        );

        if ($closureExecuted) {
            $this->io->writeln('Closure executed');
        }
        $this->io->writeln("Result: {$result}");
    }

    /**
     * Test getOptionOrPrompt with boolean flag.
     */
    private function testGetOptionOrPromptBoolean(): void
    {
        $result = $this->io->getOptionOrPrompt(
            'yes',
            fn () => false
        );
        $this->io->writeln('Result: '.($result ? 'true' : 'false'));
    }

    /**
     * Test getOptionOrPrompt with different return types.
     */
    private function testGetOptionOrPromptTypes(): void
    {
        $expected = $this->testArgs[0] ?? 'default';

        $result = $this->io->getOptionOrPrompt(
            'name',
            fn () => $expected
        );

        if (is_bool($result)) {
            $this->io->writeln('Result: '.($result ? 'true' : 'false'));
        } elseif (is_array($result)) {
            $this->io->writeln('Result: '.json_encode($result));
        } else {
            $this->io->writeln("Result: {$result}");
        }
    }

    /**
     * Test getValidatedOptionOrPrompt with valid input.
     */
    private function testGetValidatedOptionOrPromptValid(): void
    {
        $result = $this->io->getValidatedOptionOrPrompt(
            'name',
            fn ($validate) => $this->io->promptText(label: 'Name:', validate: $validate),
            fn ($value) => trim((string) $value) === '' ? 'Cannot be empty' : null
        );

        if ($result === null) {
            $this->io->writeln('Result: null');
        } else {
            $this->io->writeln("Result: {$result}");
        }
    }

    /**
     * Test getValidatedOptionOrPrompt with invalid input.
     */
    private function testGetValidatedOptionOrPromptInvalid(): void
    {
        $result = $this->io->getValidatedOptionOrPrompt(
            'name',
            fn ($validate) => $this->io->promptText(label: 'Name:', validate: $validate),
            fn ($value) => 'Always invalid'
        );

        $this->io->writeln('Result: '.($result ?? 'null'));
    }

    /**
     * Test promptSpin wrapper.
     */
    private function testPromptSpinWrapper(): void
    {
        $result = $this->io->promptSpin(
            fn () => 'success',
            'Testing...'
        );

        $this->io->writeln("Spin result: {$result}");
    }

    /**
     * Test promptText wrapper.
     */
    private function testPromptTextWrapper(): void
    {
        $this->io->promptText('Test:', required: false);
    }

    /**
     * Test promptPassword wrapper.
     */
    private function testPromptPasswordWrapper(): void
    {
        $this->io->promptPassword('Test:', required: false);
    }

    /**
     * Test promptConfirm wrapper.
     */
    private function testPromptConfirmWrapper(): void
    {
        $this->io->promptConfirm('Test:');
    }

    /**
     * Test promptPause wrapper.
     */
    private function testPromptPauseWrapper(): void
    {
        $this->io->promptPause('Test');
    }

    /**
     * Test promptSelect wrapper.
     */
    private function testPromptSelectWrapper(): void
    {
        $this->io->promptSelect('Test:', ['a', 'b'], default: 'a');
    }

    /**
     * Test promptMultiselect wrapper.
     */
    private function testPromptMultiselectWrapper(): void
    {
        $this->io->promptMultiselect('Test:', ['a', 'b']);
    }

    /**
     * Test promptSuggest wrapper.
     */
    private function testPromptSuggestWrapper(): void
    {
        $this->io->promptSuggest('Test:', ['a', 'b'], required: false);
    }

    /**
     * Test promptSearch wrapper.
     */
    private function testPromptSearchWrapper(): void
    {
        $this->io->promptSearch('Test:', fn ($q) => ['a', 'b']);
    }
}
