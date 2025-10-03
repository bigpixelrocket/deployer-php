<?php

declare(strict_types=1);

require_once __DIR__ . '/../../TestHelpers.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('InventoryService', function () {
    beforeEach(function () {
        $this->service = mockInventoryService(true, '');
    });

    //
    // Set operations
    // -------------------------------------------------------------------------------

    it('handles all set operation scenarios', function (string $path, mixed $value, ?array $existingData, bool $fileExists) {
        // ARRANGE
        $this->service = mockInventoryService($fileExists, $existingData ?? []);
        $this->service->loadInventoryFile();

        // ACT
        $this->service->set($path, $value);

        // ASSERT - Verify data was actually stored correctly
        $result = $this->service->get($path);
        expect($result)->toBe($value);
    })->with([
        // New file scenarios
        'simple nested path' => ['widgets.alpha', 'value', null, false],
        'deep nested path' => ['toys.robot.color', 'blue', null, false],
        'array value' => ['widgets.alpha', ['color' => 'red', 'size' => 10], null, false],
        'complex nested structure' => ['categories.shapes.widgets.circle.radius', ['value' => '5'], null, false],
        'single segment path' => ['widgets', ['alpha' => ['color' => 'red']], null, false],

        // Existing file scenarios
        'overwrite existing value' => ['widgets.alpha.color', 'blue', ['widgets' => ['alpha' => ['color' => 'red']]], true],
        'create intermediate paths' => ['widgets.beta.nested.color', 'green', ['widgets' => ['alpha' => ['color' => 'red']]], true],
        'type conflict resolution' => ['widgets.alpha', 'new-string-value', ['widgets' => ['alpha' => ['color' => 'red']]], true],
    ]);

    //
    // Get operations
    // -------------------------------------------------------------------------------

    it('handles all get operation scenarios', function (string $path, mixed $expected, ?array $inventoryData, bool $fileExists) {
        // ARRANGE
        $this->service = mockInventoryService($fileExists, $inventoryData ?? []);
        $this->service->loadInventoryFile();

        // ACT
        $result = $this->service->get($path);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        // File exists - positive cases
        'deep nested value' => [
            'widgets.alpha.color',
            'red',
            ['widgets' => ['alpha' => ['color' => 'red', 'size' => 'large'], 'beta' => ['color' => 'blue', 'size' => 'small']], 'animals' => ['cat' => ['sound' => 'meow', 'legs' => 4]]],
            true
        ],
        'nested object' => [
            'widgets.alpha',
            ['color' => 'red', 'size' => 'large'],
            ['widgets' => ['alpha' => ['color' => 'red', 'size' => 'large'], 'beta' => ['color' => 'blue', 'size' => 'small']], 'animals' => ['cat' => ['sound' => 'meow', 'legs' => 4]]],
            true
        ],
        'top level collection' => [
            'widgets',
            ['alpha' => ['color' => 'red', 'size' => 'large'], 'beta' => ['color' => 'blue', 'size' => 'small']],
            ['widgets' => ['alpha' => ['color' => 'red', 'size' => 'large'], 'beta' => ['color' => 'blue', 'size' => 'small']], 'animals' => ['cat' => ['sound' => 'meow', 'legs' => 4]]],
            true
        ],
        'different collection value' => [
            'animals.cat.legs',
            4,
            ['widgets' => ['alpha' => ['color' => 'red', 'size' => 'large'], 'beta' => ['color' => 'blue', 'size' => 'small']], 'animals' => ['cat' => ['sound' => 'meow', 'legs' => 4]]],
            true
        ],
        'single segment' => [
            'animals',
            ['cat' => ['sound' => 'meow', 'legs' => 4]],
            ['widgets' => ['alpha' => ['color' => 'red', 'size' => 'large'], 'beta' => ['color' => 'blue', 'size' => 'small']], 'animals' => ['cat' => ['sound' => 'meow', 'legs' => 4]]],
            true
        ],

        // File exists - negative cases (non-existent paths)
        'non-existent deep path' => ['widgets.gamma.color', null, ['widgets' => ['alpha' => ['color' => 'red']]], true],
        'non-existent collection' => ['animals.dog', null, ['widgets' => ['alpha' => ['color' => 'red']]], true],
        'non-existent root' => ['missing', null, ['widgets' => ['alpha' => ['color' => 'red']]], true],
        'partial path match' => ['widgets.alpha.weight', null, ['widgets' => ['alpha' => ['color' => 'red']]], true],

        // File doesn't exist
        'file not found' => ['widgets', null, null, false],
    ]);

    //
    // Get with default value
    // -------------------------------------------------------------------------------

    it('returns default value when path does not exist', function (string $path, mixed $default, mixed $expected) {
        // ARRANGE
        $inventoryData = ['widgets' => ['alpha' => ['color' => 'red']]];
        $this->service = mockInventoryService(true, $inventoryData);
        $this->service->loadInventoryFile();

        // ACT
        $result = $this->service->get($path, $default);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        'non-existent path with default' => ['widgets.beta', 'default-value', 'default-value'],
        'non-existent path with array default' => ['widgets.beta', ['color' => 'blue'], ['color' => 'blue']],
        'non-existent path with null default' => ['widgets.beta', null, null],
        'non-existent path with numeric default' => ['widgets.alpha.size', 10, 10],
        'non-existent path with boolean default' => ['widgets.alpha.visible', true, true],
        'existing path ignores default' => ['widgets.alpha.color', 'ignored', 'red'],
    ]);

    //
    // Delete operations
    // -------------------------------------------------------------------------------

    it('handles delete operations', function (string $path, array $inventoryData, string $scenario) {
        // ARRANGE
        $this->service = mockInventoryService(true, $inventoryData);
        $this->service->loadInventoryFile();

        // ACT
        $this->service->delete($path);

        // ASSERT - Verify data was actually removed
        expect($this->service->get($path))->toBeNull();

        // Also verify other data remains intact (for precision testing)
        if ($path === 'widgets.alpha.size') {
            expect($this->service->get('widgets.alpha.color'))->toBe('red');
        } elseif ($path === 'widgets.alpha') {
            expect($this->service->get('widgets.beta'))->not->toBeNull();
        }
    })->with([
        'removes specific property' => [
            'widgets.alpha.size',
            ['widgets' => ['alpha' => ['color' => 'red', 'size' => 10], 'beta' => ['color' => 'blue']]],
            'property removal'
        ],
        'removes entire nested structure' => [
            'widgets.alpha',
            ['widgets' => ['alpha' => ['color' => 'red'], 'beta' => ['color' => 'blue']]],
            'structure removal'
        ],
        'handles non-existent path gracefully' => [
            'widgets.gamma',
            ['widgets' => ['alpha' => ['color' => 'red']]],
            'graceful handling'
        ],
    ]);

    //
    // Error handling
    // -------------------------------------------------------------------------------

    it('throws RuntimeException when file write fails during initialization', function () {
        // ARRANGE
        $service = mockInventoryService(false, '', false, true);

        // ACT & ASSERT
        expect(fn () => $service->loadInventoryFile())
            ->toThrow(RuntimeException::class, 'Error writing inventory file');
    });

    it('throws RuntimeException when file write fails during set operation', function () {
        // ARRANGE
        $service = mockInventoryService(true, ['existing' => 'data'], false, true);
        $service->loadInventoryFile();

        // ACT & ASSERT
        expect(fn () => $service->set('widgets.alpha', 'value'))
            ->toThrow(RuntimeException::class, 'Error writing inventory file');
    });

    it('throws RuntimeException when file read fails', function () {
        // ARRANGE
        $service = mockInventoryService(true, 'content', true);

        // ACT & ASSERT
        expect(fn () => $service->loadInventoryFile())
            ->toThrow(RuntimeException::class, 'Error reading inventory file');
    });

    it('throws RuntimeException when attempting write before initialization', function () {
        // ARRANGE
        $service = mockInventoryService(false, '');

        // ACT & ASSERT
        expect(fn () => $service->set('widgets.alpha', 'value'))
            ->toThrow(RuntimeException::class, 'Inventory not loaded. Call loadInventoryFile() first.');
    });

    //
    // Inventory file status
    // -------------------------------------------------------------------------------

    it('reports correct inventory file status for different scenarios', function (bool $fileExists, array|string $data, bool $fileError, bool $fileWriteError, bool $expectsException, ?string $expectedStatusPattern) {
        // ARRANGE
        $service = mockInventoryService($fileExists, $data, $fileError, $fileWriteError);

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
        [true, ['widgets' => ['alpha' => ['color' => 'red', 'size' => 10]]], false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists with single item
        [true, ['single_key' => 'value'], false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists but is empty
        [true, [], false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists with complex structure
        [true, ['categories' => ['shapes' => ['circle' => ['radius' => 5]]]], false, false, false, '/^Reading inventory from .+\.yml$/'],

        // File exists but has read error (throws exception)
        [true, ['key' => 'value'], true, false, true, null],

        // File doesn't exist and file write fails (throws exception)
        [false, '', false, true, true, null],
    ]);
});
