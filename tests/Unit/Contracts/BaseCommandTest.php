<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Contracts;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Tester\CommandTester;

require_once __DIR__ . '/../../TestHelpers.php';

//
// Test fixtures
// -------------------------------------------------------------------------------

class TestableBaseCommand extends BaseCommand
{
    public function __construct(
        Container $container,
        EnvService $env,
        InventoryService $inventory,
        ServerRepository $servers,
        private readonly string $testName = 'test-command',
    ) {
        parent::__construct($container, $env, $inventory, $servers);
    }

    protected function configure(): void
    {
        parent::configure();
        $this->setName($this->testName)->setDescription('Test command for BaseCommand testing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = parent::execute($input, $output);
        $this->text('Test command executed successfully');
        return $result;
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

describe('BaseCommand', function () {
    it('constructs with dependencies and registers custom options', function () {
        // ARRANGE
        $container = new Container();
        $env = mockEnvService(true);
        $inventory = mockInventoryService(true);
        $servers = mockServerRepository();

        // ACT
        $command = new TestableBaseCommand($container, $env, $inventory, $servers, 'test');

        // ASSERT
        expect($command->getName())->toBe('test')
            ->and($command->getDefinition()->hasOption('env'))->toBeTrue()
            ->and($command->getDefinition()->hasOption('inventory'))->toBeTrue()
            ->and($command->getDefinition()->getOption('env')->getDescription())
                ->toContain('Custom path to .env file')
            ->and($command->getDefinition()->getOption('inventory')->getDescription())
                ->toContain('Custom path to inventory.yml file');
    });

    it('executes with proper env and inventory status output', function (bool $hasEnvFile, string $expectedEnvMessage) {
        // ARRANGE
        $container = new Container();
        $env = mockEnvService($hasEnvFile);
        $inventory = mockInventoryService(true);
        $servers = mockServerRepository();
        $command = new TestableBaseCommand($container, $env, $inventory, $servers);
        $tester = new CommandTester($command);

        // ACT
        $exitCode = $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Environment:')
            ->and($output)->toContain('Inventory:')
            ->and($output)->toContain($expectedEnvMessage)
            ->and($output)->toContain('Reading inventory from')
            ->and($output)->toContain('Test command executed successfully');
    })->with([
        'env file exists' => [true, 'Reading variables from'],
        'no env file' => [false, 'No .env file found'],
    ]);
});
