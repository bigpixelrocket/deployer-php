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
        $gitSite = new SiteDTO('example.com', 'git@github.com:user/repo.git', 'main', ['web1', 'web2']);
        $localSite = new SiteDTO('local.dev', null, null, ['dev1']);

        $repository->create($gitSite);
        $repository->create($localSite);

        // ASSERT - Find git site by domain
        $found = $repository->findByDomain('example.com');
        expect($found)->not->toBeNull()
            ->and($found->domain)->toBe('example.com')
            ->and($found->repo)->toBe('git@github.com:user/repo.git')
            ->and($found->branch)->toBe('main')
            ->and($found->servers)->toBe(['web1', 'web2'])
            ->and($found->isLocal())->toBeFalse();

        // ASSERT - Find local site by domain
        $foundLocal = $repository->findByDomain('local.dev');
        expect($foundLocal)->not->toBeNull()
            ->and($foundLocal->domain)->toBe('local.dev')
            ->and($foundLocal->repo)->toBeNull()
            ->and($foundLocal->branch)->toBeNull()
            ->and($foundLocal->servers)->toBe(['dev1'])
            ->and($foundLocal->isLocal())->toBeTrue();

        // ASSERT - Find returns null for missing
        expect($repository->findByDomain('nonexistent.com'))->toBeNull();

        // ASSERT - All returns both sites
        $all = $repository->all();
        expect($all)->toHaveCount(2)
            ->and($all[0]->domain)->toBe('example.com')
            ->and($all[1]->domain)->toBe('local.dev');

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
    // Server Filtering
    // -------------------------------------------------------------------------------

    it('finds sites by server name', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => [
            ['domain' => 'app1.com', 'repo' => 'git@github.com:user/app1.git', 'branch' => 'main', 'servers' => ['web1']],
            ['domain' => 'app2.com', 'repo' => 'git@github.com:user/app2.git', 'branch' => 'main', 'servers' => ['web2']],
            ['domain' => 'shared.com', 'repo' => 'git@github.com:user/shared.git', 'branch' => 'dev', 'servers' => ['web1', 'web2']],
            ['domain' => 'local.dev', 'servers' => ['web1']],
        ]]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT
        $web1Sites = $repository->findByServer('web1');
        $web2Sites = $repository->findByServer('web2');

        // ASSERT
        expect($web1Sites)->toHaveCount(3)
            ->and($web1Sites[0]->domain)->toBe('app1.com')
            ->and($web1Sites[1]->domain)->toBe('shared.com')
            ->and($web1Sites[2]->domain)->toBe('local.dev')
            ->and($web2Sites)->toHaveCount(2)
            ->and($web2Sites[0]->domain)->toBe('app2.com')
            ->and($web2Sites[1]->domain)->toBe('shared.com');
    });

    it('returns empty array when server has no sites', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => [
            ['domain' => 'app1.com', 'repo' => 'git@github.com:user/app1.git', 'branch' => 'main', 'servers' => ['web1']],
        ]]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT
        $result = $repository->findByServer('web2');

        // ASSERT
        expect($result)->toBeArray()->toBeEmpty();
    });

    it('returns empty array when filtering with nonexistent server', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => [
            ['domain' => 'app1.com', 'repo' => 'git@github.com:user/app1.git', 'branch' => 'main', 'servers' => ['web1']],
        ]]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT
        $result = $repository->findByServer('nonexistent');

        // ASSERT
        expect($result)->toBeArray()->toBeEmpty();
    });

    //
    // Data Hydration Robustness
    // -------------------------------------------------------------------------------

    it('handles malformed inventory data gracefully', function (array $rawData, string $expectedDomain, ?string $expectedRepo, ?string $expectedBranch, array $expectedServers) {
        // ARRANGE
        $inventory = mockInventoryService(true, ['sites' => [$rawData]]);
        $inventory->loadInventoryFile();
        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        // ACT
        $sites = $repository->all();

        // ASSERT
        expect($sites)->toHaveCount(1)
            ->and($sites[0]->domain)->toBe($expectedDomain);

        if ($expectedRepo === null) {
            expect($sites[0]->repo)->toBeNull();
        } else {
            expect($sites[0]->repo)->toBe($expectedRepo);
        }

        if ($expectedBranch === null) {
            expect($sites[0]->branch)->toBeNull();
        } else {
            expect($sites[0]->branch)->toBe($expectedBranch);
        }

        expect($sites[0]->servers)->toBe($expectedServers);
    })->with([
        'missing domain' => [
            ['repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => []],
            '', 'git@github.com:user/repo.git', 'main', [],
        ],
        'missing repo (local site)' => [
            ['domain' => 'example.com', 'branch' => 'main', 'servers' => []],
            'example.com', null, 'main', [],
        ],
        'missing branch (local site)' => [
            ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'servers' => []],
            'example.com', 'git@github.com:user/repo.git', null, [],
        ],
        'missing both repo and branch (local site)' => [
            ['domain' => 'local.dev', 'servers' => ['dev1']],
            'local.dev', null, null, ['dev1'],
        ],
        'wrong domain type' => [
            ['domain' => 12345, 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main'],
            '', 'git@github.com:user/repo.git', 'main', [],
        ],
        'wrong repo type' => [
            ['domain' => 'example.com', 'repo' => 12345, 'branch' => 'main', 'servers' => []],
            'example.com', null, 'main', [],
        ],
        'wrong branch type' => [
            ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 12345, 'servers' => []],
            'example.com', 'git@github.com:user/repo.git', null, [],
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
