<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createServerListCommandTester(array $existingServers = []): CommandTester
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

    $repository = new \Bigpixelrocket\DeployerPHP\Repositories\ServerRepository();
    $repository->loadInventory($inventory);

    $ssh = mockSSHService();

    $command = new \Bigpixelrocket\DeployerPHP\Console\Server\ServerListCommand($container, $env, $inventory, $repository, $ssh);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('ServerListCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('lists multiple servers with full details', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
            new ServerDTO('web2', '192.168.1.2', 2222, 'deployer', '~/.ssh/custom'),
            new ServerDTO('database', '10.0.0.5', 22, 'admin', null),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('▸')
            ->and($output)->toContain('All Servers')
            ->and($output)->toContain('web1')
            ->and($output)->toContain('192.168.1.1')
            ->and($output)->toContain('web2')
            ->and($output)->toContain('192.168.1.2')
            ->and($output)->toContain('2222')
            ->and($output)->toContain('deployer')
            ->and($output)->toContain('database')
            ->and($output)->toContain('10.0.0.5');
    });

    it('lists single server with complete details', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('production', 'prod.example.com', 8022, 'deploy', '~/.ssh/prod_key'),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('All Servers')
            ->and($output)->toContain('production')
            ->and($output)->toContain('prod.example.com')
            ->and($output)->toContain('8022')
            ->and($output)->toContain('deploy')
            ->and($output)->toContain('~/.ssh/prod_key');
    });

    //
    // Edge Cases
    // -------------------------------------------------------------------------------

    it('handles empty inventory gracefully', function () {
        // ARRANGE
        $tester = createServerListCommandTester([]);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('⚠')
            ->and($output)->toContain('No servers found in inventory')
            ->and($output)->toContain('server:add')
            ->and($output)->not->toContain('All Servers');
    });

    it('displays default SSH key message for servers without custom keys', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('default-key', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Key:')
            ->and($output)->toContain('default')
            ->and($output)->toContain('~/.ssh/id_ed25519')
            ->and($output)->toContain('~/.ssh/id_rsa');
    });

    it('displays custom SSH key paths correctly', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('custom-key', '192.168.1.1', 22, 'root', '~/.ssh/special_key'),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Key:')
            ->and($output)->toContain('~/.ssh/special_key')
            ->and($output)->not->toContain('default');
    });

    it('displays all server fields correctly', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('full-details', 'server.example.com', 9022, 'sysadmin', '/home/user/.ssh/key'),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($output)->toContain('Name:')
            ->and($output)->toContain('full-details')
            ->and($output)->toContain('Host:')
            ->and($output)->toContain('server.example.com')
            ->and($output)->toContain('Port:')
            ->and($output)->toContain('9022')
            ->and($output)->toContain('User:')
            ->and($output)->toContain('sysadmin')
            ->and($output)->toContain('Key:')
            ->and($output)->toContain('/home/user/.ssh/key');
    });

    it('lists servers in order they appear in inventory', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('alpha', '192.168.1.1', 22, 'root', null),
            new ServerDTO('beta', '192.168.1.2', 22, 'root', null),
            new ServerDTO('gamma', '192.168.1.3', 22, 'root', null),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        $alphaPos = strpos($output, 'alpha');
        $betaPos = strpos($output, 'beta');
        $gammaPos = strpos($output, 'gamma');

        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($alphaPos)->toBeLessThan($betaPos)
            ->and($betaPos)->toBeLessThan($gammaPos);
    });
});
