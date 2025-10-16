<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Server\ServerDeleteCommand;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createServerDeleteCommandTester(array $existingServers = [], array $existingSites = []): CommandTester
{
    // Pre-populate repository with test servers and sites
    $inventoryData = [
        'servers' => array_map(
            fn (ServerDTO $server) => [
                'name' => $server->name,
                'host' => $server->host,
                'port' => $server->port,
                'username' => $server->username,
                'privateKeyPath' => $server->privateKeyPath,
            ],
            $existingServers
        ),
        'sites' => $existingSites,
    ];

    $container = mockCommandContainer(inventoryData: $inventoryData);
    $command = $container->build(ServerDeleteCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('ServerDeleteCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('deletes server with server option non-interactively', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
            new ServerDTO('web2', '192.168.1.2', 22, 'root', null),
        ];
        $tester = createServerDeleteCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--server' => 'web1',
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
            '--server' => 'non-existent',
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

    it('prevents deletion when server has sites', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $existingSites = [
            ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => ['web1']],
            ['domain' => 'app.example.com', 'servers' => ['web1']],
        ];
        $tester = createServerDeleteCommandTester($existingServers, $existingSites);

        // ACT
        $exitCode = $tester->execute([
            '--server' => 'web1',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain("Cannot delete server 'web1' because it has one or more sites")
            ->and($output)->toContain('Sites:')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('app.example.com');
    });
});
