<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Console\Site\SiteDeleteCommand;
use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Tester\CommandTester;

//
// Test helpers
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../../../TestHelpers.php';

function createSiteDeleteCommandTester(array $existingSites = []): CommandTester
{
    // Pre-populate repository with test sites
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
    $command = $container->build(SiteDeleteCommand::class);
    return new CommandTester($command);
}

//
// Integration tests
// -------------------------------------------------------------------------------

describe('SiteDeleteCommand', function () {
    //
    // Success Scenarios
    // -------------------------------------------------------------------------------

    it('deletes site with site option non-interactively', function () {
        // ARRANGE
        $existingSites = [
            new SiteDTO('example.com', 'git@github.com:user/repo.git', 'main', ['web1']),
            new SiteDTO('app.example.com', null, null, ['web2']),
        ];
        $tester = createSiteDeleteCommandTester($existingSites);

        // ACT
        $exitCode = $tester->execute([
            '--site' => 'example.com',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('✓')
            ->and($output)->toContain("Site 'example.com' deleted successfully")
            ->and($output)->toContain('Run non-interactively:')
            ->and($output)->toContain('site:delete');
    });

    //
    // Error Scenarios
    // -------------------------------------------------------------------------------

    it('fails when deleting non-existent site', function () {
        // ARRANGE
        $existingSites = [
            new SiteDTO('existing.com', null, null, ['web1']),
        ];
        $tester = createSiteDeleteCommandTester($existingSites);

        // ACT
        $exitCode = $tester->execute([
            '--site' => 'non-existent.com',
            '--yes' => true,
        ]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::FAILURE)
            ->and($output)->toContain('✗')
            ->and($output)->toContain("Site 'non-existent.com' not found");
    });

    it('handles empty inventory gracefully', function () {
        // ARRANGE
        $tester = createSiteDeleteCommandTester([]);

        // ACT
        $exitCode = $tester->execute([]);

        // ASSERT
        $output = $tester->getDisplay();
        expect($exitCode)->toBe(Command::SUCCESS)
            ->and($output)->toContain('⚠')
            ->and($output)->toContain('No sites found in inventory')
            ->and($output)->toContain('site:add');
    });
});
