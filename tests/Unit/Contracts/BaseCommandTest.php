<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Contracts;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Container;
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
        private readonly string $testName = 'test-command',
    ) {
        parent::__construct($container, $env, $inventory);
    }

    protected function configure(): void
    {
        $this->setName($this->testName)->setDescription('Test command for BaseCommand testing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = parent::execute($input, $output);
        $this->io->text('Test command executed successfully');
        return $result;
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

describe('BaseCommand', function () {
    it('constructs with dependencies and executes with proper output', function (bool $hasEnvFile, bool $hasInventoryFile, string $commandName, string $envPattern, string $inventoryPattern) {
        // ARRANGE
        $container = new Container();
        $env = mockEnvService($hasEnvFile);
        $inventory = mockInventoryService($hasInventoryFile);

        // ACT
        $command = new TestableBaseCommand($container, $env, $inventory, $commandName);
        $tester = new CommandTester($command);
        $exitCode = $tester->execute([]);
        $output = $tester->getDisplay();

        // ASSERT
        expect($command->getName())->toBe($commandName)
            ->and($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toMatch($envPattern)
            ->and($output)->toMatch($inventoryPattern)
            ->and($output)->toContain('Environment:')
            ->and($output)->toContain('Inventory:')
            ->and($output)->toContain('╭───────')
            ->and($output)->toContain('Test command executed successfully');
    })->with([
        'both files, simple name' => [true, true, 'test', '/Environment:[\s\S]*variable[\s\S]*from/', '/Inventory:[\s\S]*Reading inventory from/'],
        'no files, kebab case' => [false, false, 'deploy-server', '/Environment:[\s\S]*No \\.env file found/', '/Inventory:[\s\S]*Creating inventory file/'],
        'env only, colon separated' => [true, false, 'server:deploy', '/Environment:[\s\S]*variable[\s\S]*from/', '/Inventory:[\s\S]*Creating inventory file/'],
        'inventory only, default' => [false, true, 'test-command', '/Environment:[\s\S]*No \\.env file found/', '/Inventory:[\s\S]*Reading inventory from/'],
    ]);
});
