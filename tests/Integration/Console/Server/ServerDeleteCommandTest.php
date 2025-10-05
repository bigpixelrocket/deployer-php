<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Server\ServerDeleteCommand;
use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createServerDeleteCommandTester(array $existingServers = []): CommandTester
{
    $container = new Container();
    $env = mockEnvService(true);

    // Pre-populate repository with test servers
    $inventoryData = empty($existingServers) ? ['servers' => []] : ['servers' => array_map(
        fn (ServerDTO $server) => [
            'name' => $server->name,
            'host' => $server->host,
            'port' => $server->port,
            'username' => $server->username,
            'privateKeyPath' => $server->privateKeyPath,
        ],
        $existingServers
    )];

    $inventory = mockInventoryService(true, $inventoryData);
    $inventory->loadInventoryFile();

    $repository = new ServerRepository();
    $repository->loadInventory($inventory);

    $ssh = mockSSHService();
    $prompter = mockPrompter();

    $command = new ServerDeleteCommand($container, $env, $inventory, $repository, $ssh, $prompter);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('ServerDeleteCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('deletes server with name option non-interactively', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
            new ServerDTO('web2', '192.168.1.2', 22, 'root', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--name' => 'web1',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain("Server 'web1' deleted successfully")
            ->and($output)->toContain('Run non-interactively:')
            ->and($output)->toContain('server:delete');
    });

    it('deletes server with confirmation', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('production', '10.0.0.1', 22, 'deployer', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--name' => 'production',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('deleted successfully');
    });

    it('deletes server when confirmation is provided', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('delete-me', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--name' => 'delete-me',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('deleted successfully');
    });

    //
    // Error Scenarios
    // -------------------------------------------------------------------------------

    it('fails when deleting non-existent server', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('existing', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--name' => 'non-existent',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain("Server 'non-existent' not found");
    });

    it('handles empty inventory gracefully', function () {
        // ARRANGE
        $tester = createServerDeleteCommandTester([]);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('⚠')
            ->and($output)->toContain('No servers found in inventory')
            ->and($output)->toContain('server:add');
    });

    //
    // Display Verification
    // -------------------------------------------------------------------------------

    it('displays server info before confirmation', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('display-test', 'example.com', 2222, 'deployer', '~/.ssh/key'),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $tester->execute([
            '--name' => 'display-test',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($output)->toContain('Name:')
            ->and($output)->toContain('display-test')
            ->and($output)->toContain('Host:')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('Port:')
            ->and($output)->toContain('2222')
            ->and($output)->toContain('User:')
            ->and($output)->toContain('deployer')
            ->and($output)->toContain('Key:')
            ->and($output)->toContain('~/.ssh/key');
    });

    it('displays default SSH key message when path is null', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('default-key-server', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $tester->execute([
            '--name' => 'default-key-server',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($output)->toContain('Key:')
            ->and($output)->toContain('default')
            ->and($output)->toContain('~/.ssh/id_ed25519')
            ->and($output)->toContain('~/.ssh/id_rsa');
    });

    it('shows command hint with correct parameters', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('hint-test', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $tester->execute([
            '--name' => 'hint-test',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($output)->toContain('Run non-interactively:')
            ->and($output)->toContain('server:delete')
            ->and($output)->toContain('--name')
            ->and($output)->toContain('hint-test')
            ->and($output)->toContain('--yes');
    });
});
