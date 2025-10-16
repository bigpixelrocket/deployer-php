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

    it('lists sites with full details', function (array $sites, array $expectedOutputs) {
        // ARRANGE
        $existingSites = array_map(
            fn (array $data) => new SiteDTO($data['domain'], $data['repo'] ?? null, $data['branch'] ?? null, $data['servers']),
            $sites
        );
        $tester = createSiteListCommandTester($existingSites);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('All Sites');

        foreach ($expectedOutputs as $expected) {
            expect($output)->toContain($expected);
        }
    })->with([
        'multiple sites' => [
            [
                ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => ['web1']],
                ['domain' => 'app.example.com', 'repo' => 'git@github.com:user/app.git', 'branch' => 'develop', 'servers' => ['web2']],
                ['domain' => 'local.test', 'servers' => ['web1']],
            ],
            ['example.com', 'git@github.com:user/repo.git', 'main', 'app.example.com', 'develop', 'local.test', 'Local'],
        ],
        'single site' => [
            [
                ['domain' => 'production.com', 'repo' => 'git@github.com:company/prod.git', 'branch' => 'production', 'servers' => ['web1', 'web2']],
            ],
            ['production.com', 'git@github.com:company/prod.git', 'production', 'web1, web2'],
        ],
    ]);

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
