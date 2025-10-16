<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Site\SiteListCommand;
use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

/**
 * @param array<int, SiteDTO> $existingSites
 */
function createSiteListCommandTester(array $existingSites = []): CommandTester
{
    // Build inventory data with sites
    $inventoryData = [
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
    $command = $container->build(SiteListCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('SiteListCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('lists multiple sites with full details', function () {
        // ARRANGE
        $existingSites = [
            new SiteDTO('example.com', 'git@github.com:user/repo.git', 'main', ['web1']),
            new SiteDTO('app.example.com', 'git@github.com:user/app.git', 'develop', ['web2']),
            new SiteDTO('local.test', null, null, ['web1']),
        ];
        $tester = createSiteListCommandTester($existingSites);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('▸')
            ->and($output)->toContain('All Sites')
            ->and($output)->toContain('example.com')
            ->and($output)->toContain('git@github.com:user/repo.git')
            ->and($output)->toContain('main')
            ->and($output)->toContain('app.example.com')
            ->and($output)->toContain('develop')
            ->and($output)->toContain('local.test')
            ->and($output)->toContain('Local');
    });

    it('lists single site with complete details', function () {
        // ARRANGE
        $existingSites = [
            new SiteDTO('production.com', 'git@github.com:company/prod.git', 'production', ['web1', 'web2']),
        ];
        $tester = createSiteListCommandTester($existingSites);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('All Sites')
            ->and($output)->toContain('production.com')
            ->and($output)->toContain('git@github.com:company/prod.git')
            ->and($output)->toContain('production')
            ->and($output)->toContain('web1, web2');
    });

    //
    // Edge Cases
    // -------------------------------------------------------------------------------

    it('handles empty inventory gracefully', function () {
        // ARRANGE
        $tester = createSiteListCommandTester([]);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('⚠')
            ->and($output)->toContain('No sites found in inventory')
            ->and($output)->toContain('site:add')
            ->and($output)->not->toContain('All Sites');
    });

    it('displays server count correctly', function (array $servers, string $expectedOutput) {
        // ARRANGE
        $existingSites = [
            new SiteDTO('test.com', null, null, $servers),
        ];
        $tester = createSiteListCommandTester($existingSites);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('Servers:')
            ->and($output)->toContain($expectedOutput);
    })->with([
        'single server' => [['web1'], 'web1'],
        'multiple servers' => [['web1', 'web2', 'web3'], 'web1, web2, web3'],
    ]);
});
