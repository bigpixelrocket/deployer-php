<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\DTOs\SiteDTO;
use Bigpixelrocket\DeployerPHP\Repositories\SiteRepository;

require_once __DIR__ . '/../../TestHelpers.php';

describe('SiteRepository', function () {
    //
    // State Management
    // -------------------------------------------------------------------------------

    it('requires inventory to be loaded before operations', function () {
        // ARRANGE
        $repository = new SiteRepository();

        // ACT & ASSERT
        expect(fn () => $repository->all())
            ->toThrow(\RuntimeException::class, 'Inventory not set');
    });

    //
    // CRUD Operations
    // -------------------------------------------------------------------------------

    it('handles complete CRUD lifecycle', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => []]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT & ASSERT - Create
        $site1 = new SiteDTO('example.com', 'git@github.com:user/repo.git', 'main', ['web1', 'web2']);
        $site2 = new SiteDTO('test.com', '', '', []);

        $repository->create($site1);
        $repository->create($site2);

        // ASSERT - Find by domain
        $found = $repository->findByDomain('example.com');
        expect($found)->not->toBeNull()
            ->and($found->domain)->toBe('example.com')
            ->and($found->repo)->toBe('git@github.com:user/repo.git')
            ->and($found->branch)->toBe('main')
            ->and($found->servers)->toBe(['web1', 'web2']);

        // ASSERT - Find returns null for missing
        expect($repository->findByDomain('nonexistent.com'))->toBeNull();

        // ASSERT - All returns both sites
        $all = $repository->all();
        expect($all)->toHaveCount(2)
            ->and($all[0]->domain)->toBe('example.com')
            ->and($all[1]->domain)->toBe('test.com');

        // ACT & ASSERT - Delete
        $repository->delete('example.com');
        expect($repository->findByDomain('example.com'))->toBeNull()
            ->and($repository->all())->toHaveCount(1);

        // ASSERT - Delete nonexistent doesn't error
        $repository->delete('never-existed.com');
        expect($repository->all())->toHaveCount(1);
    });

    it('prevents duplicate site creation', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => [
            ['domain' => 'existing.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => []],
        ]]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT & ASSERT
        expect(fn () => $repository->create(new SiteDTO('existing.com', 'git@github.com:other/repo.git', 'develop', [])))
            ->toThrow(\RuntimeException::class, "Site 'existing.com' already exists");
    });

    //
    // Data Hydration Robustness
    // -------------------------------------------------------------------------------

    it('handles malformed inventory data gracefully', function (array $rawData, string $expectedDomain, string $expectedRepo, string $expectedBranch, array $expectedServers) {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => [$rawData]]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT
        $sites = $repository->all();

        // ASSERT
        expect($sites)->toHaveCount(1)
            ->and($sites[0]->domain)->toBe($expectedDomain)
            ->and($sites[0]->repo)->toBe($expectedRepo)
            ->and($sites[0]->branch)->toBe($expectedBranch)
            ->and($sites[0]->servers)->toBe($expectedServers);
    })->with([
        'missing domain' => [
            ['repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => []],
            '', 'git@github.com:user/repo.git', 'main', [],
        ],
        'missing repo' => [
            ['domain' => 'example.com', 'branch' => 'main', 'servers' => []],
            'example.com', '', 'main', [],
        ],
        'missing branch' => [
            ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'servers' => []],
            'example.com', 'git@github.com:user/repo.git', '', [],
        ],
        'wrong domain type' => [
            ['domain' => 12345, 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main'],
            '', 'git@github.com:user/repo.git', 'main', [],
        ],
        'wrong servers type' => [
            ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => 'not-array'],
            'example.com', 'git@github.com:user/repo.git', 'main', [],
        ],
        'mixed servers array' => [
            ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => ['web1', 123, 'web2', null]],
            'example.com', 'git@github.com:user/repo.git', 'main', ['web1', 'web2'],
        ],
    ]);

    //
    // Initialization Edge Cases
    // -------------------------------------------------------------------------------

    it('initializes empty array when sites key missing', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, []);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();

        // ACT
        $repository->loadInventory($inventory);

        // ASSERT
        expect($repository->all())->toBeArray()->toBeEmpty();
    });
});
