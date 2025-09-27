<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Yaml\Yaml;

require_once __DIR__ . '/../TestHelpers.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('InventoryService', function () {
    beforeEach(function () {
        $this->filesystem = mockFilesystem();
        $this->service = new InventoryService($this->filesystem);
    });

    //
    // Set operations
    // -------------------------------------------------------------------------------

    it('handles all set operation scenarios', function (string $path, mixed $value, ?array $existingData, bool $fileExists) {
        // ARRANGE
        if ($fileExists && $existingData) {
            $yamlContent = Yaml::dump($existingData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            $this->filesystem = mockFilesystem(true, $yamlContent, false);
        } else {
            $this->filesystem = mockFilesystem(false);
        }
        $this->service = new InventoryService($this->filesystem);

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
            $this->filesystem = mockFilesystem(true, $yamlContent, false);
        } else {
            $this->filesystem = mockFilesystem(false);
        }
        $this->service = new InventoryService($this->filesystem);

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
    // Get all operations
    // -------------------------------------------------------------------------------

    it('handles getAll scenarios', function (array $expected, bool $fileExists) {
        // ARRANGE
        if ($fileExists) {
            $yamlContent = Yaml::dump($expected, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            $this->filesystem = mockFilesystem(true, $yamlContent, false);
        } else {
            $this->filesystem = mockFilesystem(false);
        }
        $this->service = new InventoryService($this->filesystem);

        // ACT
        $result = $this->service->getAll();

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        'returns entire structure' => [
            ['servers' => ['web1' => ['host' => 'example.com']], 'databases' => ['db1' => ['host' => 'db.com']]],
            true
        ],
        'returns empty array when file missing' => [
            [],
            false
        ],
    ]);

    //
    // Has operations
    // -------------------------------------------------------------------------------

    it('correctly identifies existing paths', function (string $path, bool $expected, bool $fileExists) {
        // ARRANGE
        if ($fileExists) {
            $inventoryData = [
                'servers' => [
                    'web1' => ['host' => 'example.com', 'port' => 22],
                    'web2' => ['host' => 'test.com'],
                ],
                'databases' => ['db1' => ['host' => 'db.com']],
            ];
            $yamlContent = Yaml::dump($inventoryData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
            $this->filesystem = mockFilesystem(true, $yamlContent, false);
        } else {
            $this->filesystem = mockFilesystem(false);
        }
        $this->service = new InventoryService($this->filesystem);

        // ACT
        $result = $this->service->has($path);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        // Existing paths
        ['servers', true, true],
        ['servers.web1', true, true],
        ['servers.web1.host', true, true],
        ['servers.web1.port', true, true],
        ['databases.db1.host', true, true],

        // Non-existent paths
        ['servers.web3', false, true],
        ['servers.web1.user', false, true],
        ['missing', false, true],
        ['databases.db2', false, true],
        ['servers.web1.host.subdomain', false, true],

        // File doesn't exist
        ['servers.web1', false, false],
    ]);

    //
    // Delete operations
    // -------------------------------------------------------------------------------

    it('handles delete operations', function (string $path, array $inventoryData, string $scenario) {
        // ARRANGE
        $yamlContent = Yaml::dump($inventoryData, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        $this->filesystem = mockFilesystem(true, $yamlContent, false);
        $this->service = new InventoryService($this->filesystem);

        // ACT
        $this->service->delete($path);

        // ASSERT - Verify data was actually removed
        expect($this->service->has($path))->toBeFalse();

        // Also verify other data remains intact (for precision testing)
        if ($path === 'servers.web1.port') {
            expect($this->service->get('servers.web1.host'))->toBe('example.com');
        } elseif ($path === 'servers.web1') {
            expect($this->service->has('servers.web2'))->toBeTrue();
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
    // Edge cases
    // -------------------------------------------------------------------------------

    it('handles invalid YAML parsing gracefully', function () {
        // ARRANGE
        $this->filesystem = mockFilesystem(true, '', false); // Empty content returns null when parsed
        $this->service = new InventoryService($this->filesystem);

        // ACT
        $result = $this->service->get('servers');

        // ASSERT
        expect($result)->toBeNull();
    });

    //
    // Error handling
    // -------------------------------------------------------------------------------

    it('throws RuntimeException when directory creation fails', function () {
        // ARRANGE
        $filesystem = mockFilesystem(false, '', false, true, false);
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->set('servers.web1', 'value'))
            ->toThrow(RuntimeException::class, 'Unable to create inventory directory');
    });

    it('throws RuntimeException when file write fails', function () {
        // ARRANGE
        $filesystem = mockFilesystem(true, Yaml::dump([], 2, 4), false, false, true);
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->set('servers.web1', 'value'))
            ->toThrow(RuntimeException::class, 'Failed to write inventory file');
    });

    it('throws ParseException when YAML is malformed', function () {
        // ARRANGE
        $filesystem = mockFilesystem(true, "invalid: [\n  - broken", false); // Malformed YAML
        $service = new InventoryService($filesystem);

        // ACT & ASSERT
        expect(fn () => $service->get('servers'))
            ->toThrow(\Symfony\Component\Yaml\Exception\ParseException::class);
    });

    //
    // Inventory file status
    // -------------------------------------------------------------------------------

    it('reports correct inventory file status for different scenarios', function (bool $fileExists, string $fileContent, bool $fileError, bool $dirCreateError, bool $fileWriteError, string $expectedStatusPattern) {
        // ARRANGE
        $filesystem = mockFilesystem($fileExists, $fileContent, $fileError, $dirCreateError, $fileWriteError);
        $service = new InventoryService($filesystem);

        // ACT
        $status = $service->getInventoryFileStatus();

        // ASSERT
        expect($status)->toMatch($expectedStatusPattern);
    })->with([
        // File doesn't exist - should create empty file
        [false, '', false, false, false, '/^Creating inventory file at .+\.yml$/'],

        // File exists with content
        [true, Yaml::dump(['servers' => ['web1' => ['host' => 'example.com', 'port' => 22]]], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists with single item
        [true, Yaml::dump(['single_key' => 'value'], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists but is empty
        [true, Yaml::dump([], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists but has read error
        [true, Yaml::dump(['key' => 'value'], 2, 4), true, false, false, '/^Error reading inventory file from .+\.yml: .+$/'],

        // File doesn't exist and directory creation fails
        [false, '', false, true, false, '/^Error creating inventory file at .+\.yml: .+$/'],

        // File doesn't exist and file write fails
        [false, '', false, false, true, '/^Error creating inventory file at .+\.yml: .+$/'],

        // File exists with complex structure
        [true, Yaml::dump(['environments' => ['prod' => ['db' => ['host' => 'prod-db']]]], 2, 4), false, false, false, '/^Reading inventory from .+\.yml$/'],
    ]);
});
