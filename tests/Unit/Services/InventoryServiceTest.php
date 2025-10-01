<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../../TestHelpers.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('InventoryService', function () {
    beforeEach(function () {
        $this->filesystem = mockFilesystem(true, '', false, false, false, 'inventory.yml');
        $this->service = new InventoryService($this->filesystem);
    });

    //
    // Set operations
    // -------------------------------------------------------------------------------

    it('handles all set operation scenarios', function (string $path, mixed $value, ?array $existingData, bool $fileExists) {
        // ARRANGE
        if ($fileExists && $existingData) {
            $yamlContent = Yaml::dump($existingData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            $this->filesystem = mockFilesystem(true, $yamlContent, false, false, false, 'inventory.yml');
        } else {
            $this->filesystem = mockFilesystem(false, '', false, false, false, 'inventory.yml');
        }

        $this->service = new InventoryService($this->filesystem);
        $this->service->loadInventoryFile();

        // ACT
        $this->service->set($path, $value);

        // ASSERT - Verify data was actually stored correctly
        $result = $this->service->get($path);
        expect($result)->toBe($value);
    })->with([
        // New file scenarios
        'simple nested path' => ['servers.web1', 'value', null, false],
        'deep nested path' => ['app.db.host', 'localhost', null, false],
        'array value' => ['servers.web1', ['host' => 'example.com', 'port' => 22], null, false],
        'complex nested structure' => ['deployments.prod.servers.web.config', ['cpu' => '2'], null, false],
        'single segment path' => ['servers', ['web1' => ['host' => 'example.com']], null, false],

        // Existing file scenarios
        'overwrite existing value' => ['servers.web1.host', 'new.com', ['servers' => ['web1' => ['host' => 'old.com']]], true],
        'create intermediate paths' => ['servers.web2.database.host', 'db.example.com', ['servers' => ['web1' => ['host' => 'example.com']]], true],
        'type conflict resolution' => ['servers.web1', 'new-string-value', ['servers' => ['web1' => ['host' => 'example.com']]], true],
    ]);

    //
    // Get operations
    // -------------------------------------------------------------------------------

    it('handles all get operation scenarios', function (string $path, mixed $expected, ?array $inventoryData, bool $fileExists) {
        // ARRANGE
        if ($fileExists && $inventoryData) {
            $yamlContent = Yaml::dump($inventoryData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            $this->filesystem = mockFilesystem(true, $yamlContent, false, false, false, 'inventory.yml');
        } else {
            $this->filesystem = mockFilesystem(false, '', false, false, false, 'inventory.yml');
        }

        $this->service = new InventoryService($this->filesystem);
        $this->service->loadInventoryFile();

        // ACT
        $result = $this->service->get($path);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        // File exists - positive cases
        'deep nested value' => [
            'servers.production.host',
            'prod.example.com',
            ['servers' => ['production' => ['host' => 'prod.example.com', 'user' => 'deploy'], 'staging' => ['host' => 'staging.example.com']], 'databases' => ['primary' => ['host' => 'db1.example.com', 'port' => 5432]]],
            true
        ],
        'nested object' => [
            'servers.production',
            ['host' => 'prod.example.com', 'user' => 'deploy'],
            ['servers' => ['production' => ['host' => 'prod.example.com', 'user' => 'deploy'], 'staging' => ['host' => 'staging.example.com']], 'databases' => ['primary' => ['host' => 'db1.example.com', 'port' => 5432]]],
            true
        ],
        'top level collection' => [
            'servers',
            ['production' => ['host' => 'prod.example.com', 'user' => 'deploy'], 'staging' => ['host' => 'staging.example.com']],
            ['servers' => ['production' => ['host' => 'prod.example.com', 'user' => 'deploy'], 'staging' => ['host' => 'staging.example.com']], 'databases' => ['primary' => ['host' => 'db1.example.com', 'port' => 5432]]],
            true
        ],
        'different collection port' => [
            'databases.primary.port',
            5432,
            ['servers' => ['production' => ['host' => 'prod.example.com', 'user' => 'deploy'], 'staging' => ['host' => 'staging.example.com']], 'databases' => ['primary' => ['host' => 'db1.example.com', 'port' => 5432]]],
            true
        ],
        'single segment' => [
            'databases',
            ['primary' => ['host' => 'db1.example.com', 'port' => 5432]],
            ['servers' => ['production' => ['host' => 'prod.example.com', 'user' => 'deploy'], 'staging' => ['host' => 'staging.example.com']], 'databases' => ['primary' => ['host' => 'db1.example.com', 'port' => 5432]]],
            true
        ],

        // File exists - negative cases (non-existent paths)
        'non-existent deep path' => ['servers.web2.host', null, ['servers' => ['web1' => ['host' => 'example.com']]], true],
        'non-existent collection' => ['databases.primary', null, ['servers' => ['web1' => ['host' => 'example.com']]], true],
        'non-existent root' => ['missing', null, ['servers' => ['web1' => ['host' => 'example.com']]], true],
        'partial path match' => ['servers.web1.port', null, ['servers' => ['web1' => ['host' => 'example.com']]], true],

        // File doesn't exist
        'file not found' => ['servers', null, null, false],
    ]);

    //
    // Get with default value
    // -------------------------------------------------------------------------------

    it('returns default value when path does not exist', function (string $path, mixed $default, mixed $expected) {
        // ARRANGE
        $inventoryData = ['servers' => ['web1' => ['host' => 'example.com']]];
        $yamlContent = Yaml::dump($inventoryData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $this->filesystem = mockFilesystem(true, $yamlContent, false, false, false, 'inventory.yml');
        $this->service = new InventoryService($this->filesystem);
        $this->service->loadInventoryFile();

        // ACT
        $result = $this->service->get($path, $default);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        'non-existent path with default' => ['servers.web2', 'default-server', 'default-server'],
        'non-existent path with array default' => ['servers.web2', ['host' => 'default.com'], ['host' => 'default.com']],
        'non-existent path with null default' => ['servers.web2', null, null],
        'non-existent path with numeric default' => ['servers.web1.port', 22, 22],
        'non-existent path with boolean default' => ['servers.web1.enabled', true, true],
        'existing path ignores default' => ['servers.web1.host', 'ignored', 'example.com'],
    ]);

    //
    // Delete operations
    // -------------------------------------------------------------------------------

    it('handles delete operations', function (string $path, array $inventoryData, string $scenario) {
        // ARRANGE
        $yamlContent = Yaml::dump($inventoryData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $this->filesystem = mockFilesystem(true, $yamlContent, false, false, false, 'inventory.yml');
        $this->service = new InventoryService($this->filesystem);
        $this->service->loadInventoryFile();

        // ACT
        $this->service->delete($path);

        // ASSERT - Verify data was actually removed
        expect($this->service->get($path))->toBeNull();

        // Also verify other data remains intact (for precision testing)
        if ($path === 'servers.web1.port') {
            expect($this->service->get('servers.web1.host'))->toBe('example.com');
        } elseif ($path === 'servers.web1') {
            expect($this->service->get('servers.web2'))->not->toBeNull();
        }
    })->with([
        'removes specific property' => [
            'servers.web1.port',
            ['servers' => ['web1' => ['host' => 'example.com', 'port' => 22], 'web2' => ['host' => 'test.com']]],
            'property removal'
        ],
        'removes entire nested structure' => [
            'servers.web1',
            ['servers' => ['web1' => ['host' => 'example.com'], 'web2' => ['host' => 'test.com']]],
            'structure removal'
        ],
        'handles non-existent path gracefully' => [
            'servers.web2',
            ['servers' => ['web1' => ['host' => 'example.com']]],
            'graceful handling'
        ],
    ]);

    //
    // Error handling
    // -------------------------------------------------------------------------------

    it('throws RuntimeException when file write fails during initialization', function () {
        // ARRANGE
        $filesystem = mockFilesystem(false, '', false, false, true, 'inventory.yml');
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->loadInventoryFile())
            ->toThrow(RuntimeException::class, 'Error writing inventory file');
    });

    it('throws RuntimeException when file write fails during set operation', function () {
        // ARRANGE
        $filesystem = mockFilesystem(true, Yaml::dump(['existing' => 'data'], 2, 4), false, false, true, 'inventory.yml');
        $service = new InventoryService($filesystem);
        $service->loadInventoryFile();

        // ACT & ASSERT
        expect(fn () => $service->set('servers.web1', 'value'))
            ->toThrow(RuntimeException::class, 'Error writing inventory file');
    });

    it('throws RuntimeException when file read fails', function () {
        // ARRANGE
        $filesystem = mockFilesystem(true, 'content', true, false, false, 'inventory.yml');
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->loadInventoryFile())
            ->toThrow(RuntimeException::class, 'Error reading inventory file');
    });

    it('throws RuntimeException when attempting write before initialization', function () {
        // ARRANGE
        $filesystem = mockFilesystem(false, '', false, false, false, 'inventory.yml');
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->set('servers.web1', 'value'))
            ->toThrow(RuntimeException::class, 'Inventory not loaded. Call loadInventoryFile() first.');
    });

    //
    // Inventory file status
    // -------------------------------------------------------------------------------

    it('reports correct inventory file status for different scenarios', function (bool $fileExists, string $fileContent, bool $fileError, bool $fileWriteError, bool $expectsException, ?string $expectedStatusPattern) {
        // ARRANGE
        $filesystem = mockFilesystem($fileExists, $fileContent, $fileError, false, $fileWriteError, 'inventory.yml');
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        if ($expectsException) {
            expect(fn () => $service->loadInventoryFile())
                ->toThrow(RuntimeException::class);
        } else {
            $service->loadInventoryFile();
            $status = $service->getInventoryFileStatus();
            expect($status)->toMatch($expectedStatusPattern);
        }
    })->with([
        // File exists with content
        [true, Yaml::dump(['servers' => ['web1' => ['host' => 'example.com', 'port' => 22]]], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists with single item
        [true, Yaml::dump(['single_key' => 'value'], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists but is empty
        [true, Yaml::dump([], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists with complex structure
        [true, Yaml::dump(['environments' => ['prod' => ['db' => ['host' => 'prod-db']]]], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists but has read error (throws exception)
        [true, Yaml::dump(['key' => 'value'], 2, 4), true, false, true, null],

        // File doesn't exist and file write fails (throws exception)
        [false, '', false, true, true, null],
    ]);
});
