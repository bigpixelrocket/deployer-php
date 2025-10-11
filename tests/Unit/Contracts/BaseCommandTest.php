<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit\Contracts;

use Bigpixelrocket\DeployerPHP\Contracts\BaseCommand;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Repositories\SiteRepository;
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
    protected function configure(): void
    {
        parent::configure();
        $this->setName('test-command')->setDescription('Test command for BaseCommand testing');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $result = parent::execute($input, $output);
        $this->writeln('Test command executed successfully');
        return $result;
    }
}

//
// Unit tests
// -------------------------------------------------------------------------------

describe('BaseCommand', function () {
    it('constructs with dependencies and registers custom options', function () {
        // ARRANGE
        $container = mockCommandContainer();
        $command = $container->build(TestableBaseCommand::class);

        // ASSERT
        expect($command->getName())->toBe('test-command')
            ->and($command->getDefinition()->hasOption('env'))->toBeTrue()
            ->and($command->getDefinition()->hasOption('inventory'))->toBeTrue()
            ->and($command->getDefinition()->getOption('env')->getDescription())
                ->toContain('Custom path to .env file')
            ->and($command->getDefinition()->getOption('inventory')->getDescription())
                ->toContain('Custom path to inventory.yml file');
    });

    it('executes with proper env and inventory status output', function (bool $hasEnvFile, string $expectedEnvMessage) {
        // ARRANGE
        $container = mockCommandContainer(envFileExists: $hasEnvFile);
        $command = $container->build(TestableBaseCommand::class);
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

    it('initializes repositories with inventory during initialization', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, [
            'servers' => [['name' => 'web1', 'host' => '192.168.1.1', 'port' => 22, 'user' => 'deploy']],
            'sites' => [['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => ['web1']]],
        ]);

        // Create uninitialized repositories (not using helper to avoid auto-loading)
        $servers = new ServerRepository();
        $sites = new SiteRepository();

        $container = mockCommandContainer(
            inventory: $inventory,
            servers: $servers,
            sites: $sites
        );

        $command = $container->build(TestableBaseCommand::class);
        $tester = new CommandTester($command);

        // ACT
        $tester->execute([]);

        // ASSERT - Verify repositories were loaded with inventory data
        expect($servers->findByName('web1'))->not->toBeNull()
            ->and($servers->findByName('web1')->host)->toBe('192.168.1.1')
            ->and($sites->findByDomain('example.com'))->not->toBeNull()
            ->and($sites->findByDomain('example.com')->domain)->toBe('example.com');
    });
});
