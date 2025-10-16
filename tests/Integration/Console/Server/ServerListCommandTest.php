<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Server\ServerListCommand;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

/**
 * @param array<int, ServerDTO> $existingServers
 * @param array<int, SiteDTO> $existingSites
 */
function createServerListCommandTester(array $existingServers = [], array $existingSites = []): CommandTester
{
    // Build inventory data with servers and sites
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
        'sites' => array_map(
            fn (SiteDTO $site) => [
                'domain' => $site->domain,
                'repo' => $site->repo,
                'branch' => $site->branch,
                'servers' => $site->servers,
            ],
            $existingSites
        ),
    ];

    $container = mockCommandContainer(inventoryData: $inventoryData);
    $command = $container->build(ServerListCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('ServerListCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('lists servers with full details', function () {
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

    it('displays SSH key path correctly', function (?string $keyPath, array $expectedOutput) {
        // ARRANGE
        $existingServers = [
            new ServerDTO('test-server', '192.168.1.1', 22, 'root', $keyPath),
        ];
        $tester = createServerListCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Key:');

        foreach ($expectedOutput as $expected) {
            expect($output)->toContain($expected);
        }
    })->with([
        'default key' => [null, ['default', '~/.ssh/id_ed25519', '~/.ssh/id_rsa']],
        'custom key' => ['~/.ssh/special_key', ['~/.ssh/special_key']],
    ]);

    //
    // Site Display
    // -------------------------------------------------------------------------------

    it('displays sites under their respective servers', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
            new ServerDTO('web2', '192.168.1.2', 22, 'root', null),
        ];
        $existingSites = [
            new SiteDTO('example.com', 'https://github.com/user/repo.git', 'main', ['web1']),
            new SiteDTO('test.com', null, null, ['web2']),
            new SiteDTO('shared.com', 'https://github.com/user/shared.git', 'dev', ['web1', 'web2']),
        ];
        $tester = createServerListCommandTester($existingServers, $existingSites);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Sites:')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('test.com')
            ->and($output)->toContain('shared.com');

        // Verify sites appear after their servers
        $web1Pos = strpos($output, 'web1');
        $examplePos = strpos($output, 'example.com');
        $sharedPos = strpos($output, 'shared.com');
        $web2Pos = strpos($output, 'web2');
        $testPos = strpos($output, 'test.com');

        expect($web1Pos)->toBeLessThan($examplePos)
            ->and($web1Pos)->toBeLessThan($sharedPos)
            ->and($web2Pos)->toBeLessThan($testPos);
    });

    it('displays no sites section when server has no sites', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createServerListCommandTester($existingServers, []);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('web1')
            ->and($output)->not->toContain('Sites:');
    });
});
