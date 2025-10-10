<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\DTOs\ServerDTO;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;

require_once __DIR__ . '/../../TestHelpers.php';

describe('ServerRepository', function () {
    //
    // State Management
    // -------------------------------------------------------------------------------

    it('requires inventory to be loaded before operations', function () {
        // ARRANGE
        $repository = new ServerRepository();

        // ACT & ASSERT
        expect(fn () => $repository->all())
            ->toThrow(\RuntimeException::class, 'Inventory not set');
    });

    //
    // CRUD Operations
    // -------------------------------------------------------------------------------

    it('handles complete CRUD lifecycle', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['servers' => []]);
        $inventory->loadInventoryFile();
        $repository = new ServerRepository();
        $repository->loadInventory($inventory);

        // ACT & ASSERT - Create
        $server1 = new ServerDTO('web1', '192.168.1.1', 2222, 'deployer', '~/.ssh/key');
        $server2 = new ServerDTO('web2', '192.168.1.2');

        $repository->create($server1);
        $repository->create($server2);

        // ASSERT - Find by name
        $found = $repository->findByName('web1');
        expect($found)->not->toBeNull()
            ->and($found->name)->toBe('web1')
            ->and($found->host)->toBe('192.168.1.1')
            ->and($found->port)->toBe(2222)
            ->and($found->username)->toBe('deployer')
            ->and($found->privateKeyPath)->toBe('~/.ssh/key');

        // ASSERT - Find returns null for missing
        expect($repository->findByName('nonexistent'))->toBeNull();

        // ASSERT - All returns both servers
        $all = $repository->all();
        expect($all)->toHaveCount(2)
            ->and($all[0]->name)->toBe('web1')
            ->and($all[1]->name)->toBe('web2');

        // ACT & ASSERT - Delete
        $repository->delete('web1');
        expect($repository->findByName('web1'))->toBeNull()
            ->and($repository->all())->toHaveCount(1);

        // ASSERT - Delete nonexistent doesn't error
        $repository->delete('never-existed');
        expect($repository->all())->toHaveCount(1);
    });

    it('prevents duplicate server creation', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, ['servers' => [
            ['name' => 'existing', 'host' => '192.168.1.1', 'port' => 22, 'username' => 'root', 'privateKeyPath' => null],
        ]]);
        $inventory->loadInventoryFile();
        $repository = new ServerRepository();
        $repository->loadInventory($inventory);

        // ACT & ASSERT
        expect(fn () => $repository->create(new ServerDTO('existing', '192.168.1.2')))
            ->toThrow(\RuntimeException::class, "Server 'existing' already exists");
    });

    //
    // Data Hydration Robustness
    // -------------------------------------------------------------------------------

    it('handles malformed inventory data gracefully', function (array $rawData, string $expectedName, string $expectedHost, int $expectedPort) {
        // ARRANGE
        $inventory = mockInventoryService(true, ['servers' => [$rawData]]);
        $inventory->loadInventoryFile();
        $repository = new ServerRepository();
        $repository->loadInventory($inventory);

        // ACT
        $servers = $repository->all();

        // ASSERT
        expect($servers)->toHaveCount(1)
            ->and($servers[0]->name)->toBe($expectedName)
            ->and($servers[0]->host)->toBe($expectedHost)
            ->and($servers[0]->port)->toBe($expectedPort);
    })->with([
        'missing name' => [
            ['host' => '192.168.1.1', 'port' => 22, 'username' => 'root'],
            '', '192.168.1.1', 22,
        ],
        'missing host' => [
            ['name' => 'web1', 'port' => 22, 'username' => 'root'],
            'web1', '', 22,
        ],
        'invalid port type' => [
            ['name' => 'web1', 'host' => '192.168.1.1', 'port' => 'not-a-number'],
            'web1', '192.168.1.1', 22,
        ],
        'wrong name type' => [
            ['name' => 12345, 'host' => '192.168.1.1'],
            '', '192.168.1.1', 22,
        ],
    ]);

    //
    // Initialization Edge Cases
    // -------------------------------------------------------------------------------

    it('initializes empty array when servers key missing', function () {
        // ARRANGE
        $inventory = mockInventoryService(true, []);
        $inventory->loadInventoryFile();
        $repository = new ServerRepository();

        // ACT
        $repository->loadInventory($inventory);

        // ASSERT
        expect($repository->all())->toBeArray()->toBeEmpty();
    });
});
