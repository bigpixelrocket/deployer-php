<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Site\SiteAddCommand;
use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createSiteAddCommandTester(array $existingServers = [], array $existingSites = []): CommandTester
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
        'sites' => $existingSites,
    ];

    $container = mockCommandContainer(inventoryData: $inventoryData);
    $command = $container->build(SiteAddCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('SiteAddCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('adds git site with all options provided non-interactively', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createSiteAddCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => 'example.com',
            '--type' => 'git',
            '--repo' => 'git@github.com:user/repo.git',
            '--branch' => 'main',
            '--servers' => 'web1',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('Site added successfully')
            ->and($output)->toContain('Run non-interactively:')
            ->and($output)->toContain('site:add')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('git@github.com:user/repo.git');
    });

    it('adds local site with minimal options', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createSiteAddCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => 'local.test',
            '--type' => 'local',
            '--servers' => 'web1',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('Site added successfully')
            ->and($output)->toContain('local.test')
            ->and($output)->toContain('Local');
    });

    it('adds site with multiple servers', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
            new ServerDTO('web2', '192.168.1.2', 22, 'root', null),
        ];
        $tester = createSiteAddCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => 'multi-server.com',
            '--type' => 'git',
            '--repo' => 'git@github.com:user/app.git',
            '--branch' => 'production',
            '--servers' => 'web1,web2',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain('Site added successfully')
            ->and($output)->toContain('multi-server.com')
            ->and($output)->toContain('web1, web2');
    });

    //
    // Error Scenarios
    // -------------------------------------------------------------------------------

    it('fails when no servers are available', function () {
        // ARRANGE
        $tester = createSiteAddCommandTester([]);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('⚠')
            ->and($output)->toContain('No servers available')
            ->and($output)->toContain('server:add');
    });

    it('rejects invalid domain with helpful error message', function (string $invalidDomain) {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createSiteAddCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => $invalidDomain,
            '--type' => 'local',
            '--servers' => 'web1',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('valid');
    })->with([
        'invalid chars' => ['example!@#.com'],
        'spaces' => ['example .com'],
        'empty' => [''],
    ]);

    it('rejects invalid branch with helpful error message', function (string $invalidBranch) {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createSiteAddCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => 'test.com',
            '--type' => 'git',
            '--repo' => 'git@github.com:user/repo.git',
            '--branch' => $invalidBranch,
            '--servers' => 'web1',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('Branch');
    })->with([
        'empty' => [''],
        'whitespace only' => ['   '],
    ]);

    it('prevents duplicate site domains', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $existingSites = [
            [
                'domain' => 'duplicate.com',
                'repo' => null,
                'branch' => null,
                'servers' => ['web1'],
            ],
        ];
        $tester = createSiteAddCommandTester($existingServers, $existingSites);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => 'duplicate.com',
            '--type' => 'local',
            '--servers' => 'web1',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('already exists')
            ->and($output)->toContain('duplicate.com');
    });

    it('rejects non-existent server names', function () {
        // ARRANGE
        $existingServers = [
            new ServerDTO('web1', '192.168.1.1', 22, 'root', null),
        ];
        $tester = createSiteAddCommandTester($existingServers);

        // ACT
        $exitCode = $tester->execute([
            '--domain' => 'test.com',
            '--type' => 'local',
            '--servers' => 'non-existent',
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain('non-existent')
            ->and($output)->toMatch('/not found|does not exist/i');
    });
});
