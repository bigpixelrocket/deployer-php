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

function createServerDeleteCommandTester(array $existingServers = []): CommandTester
{
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

    it('displays server information before deletion', function (ServerDTO $server, array $expectedOutput) {
        // ARRANGE
        $tester = createServerDeleteCommandTester([$server]);

        // ACT
        $tester->execute([
            '--name' => $server->name,
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        foreach ($expectedOutput as $expected) {
            expect($output)->toContain($expected);
        }
    })->with([
        'custom key path' => [
            new ServerDTO('custom-key', 'example.com', 2222, 'deployer', '~/.ssh/key'),
            ['Name:', 'custom-key', 'Host:', 'example.com', 'Port:', '2222', 'User:', 'deployer', 'Key:', '~/.ssh/key'],
        ],
        'default key path' => [
            new ServerDTO('default-key', '192.168.1.1', 22, 'root', null),
            ['Name:', 'default-key', 'Host:', '192.168.1.1', 'Port:', '22', 'User:', 'root', 'Key:', 'default', '~/.ssh/id_ed25519', '~/.ssh/id_rsa'],
        ],
    ]);

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
