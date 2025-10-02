<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use Symfony\Component\Filesystem\Exception\IOException;

describe('mockFilesystem', function () {
    //
    // File Existence Checks
    // -------------------------------------------------------------------------------

    it('correctly distinguishes between files and directories', function () {
        // ARRANGE - Mock with a file in a directory
        $mockFs = mockFilesystem(exists: true, content: 'content', initialPath: '.deployer/inventory.yml');

        // ACT & ASSERT - Directory exists but file in subdirectory doesn't
        expect($mockFs->exists('.deployer'))->toBeTrue('directory should exist');
        expect($mockFs->exists('.deployer/'))->toBeTrue('directory with trailing slash should exist');
        expect($mockFs->exists('.deployer/inventory.yml'))->toBeTrue('existing file should exist');
        expect($mockFs->exists('.deployer/missing.yml'))->toBeFalse('non-existent file should not exist');
    });

    it('does not report files as existing due to directory substring match', function () {
        // ARRANGE - File doesn't exist, only directory
        $mockFs = mockFilesystem(exists: false, content: '', initialPath: '.deployer/inventory.yml');

        // ACT & ASSERT - Directory exists but file doesn't (regression test for substring bug)
        expect($mockFs->exists('.deployer'))->toBeTrue('directory should exist');
        expect($mockFs->exists('.deployer/inventory.yml'))->toBeFalse('non-existent file should not exist even if directory matches');
        expect($mockFs->exists('/path/to/.deployer/config.yml'))->toBeFalse('non-existent file with directory substring should not exist');
    });

    it('matches files by direct path or path ending', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: 'test', initialPath: 'inventory.yml');

        // ACT & ASSERT
        expect($mockFs->exists('inventory.yml'))->toBeTrue('direct match should work');
        expect($mockFs->exists('/path/to/inventory.yml'))->toBeTrue('path ending match should work');
        expect($mockFs->exists('/different/inventory.yml'))->toBeTrue('different path ending should work');
    });

    it('handles iterable file checks correctly', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: 'test', initialPath: '.env');
        $mockFs->dumpFile('.env.example', 'example');

        // ACT & ASSERT
        expect($mockFs->exists(['.env', '.env.example']))->toBeTrue('all files exist');
        expect($mockFs->exists(['.env', '.missing']))->toBeFalse('one file missing');
    });

    //
    // File Read Operations
    // -------------------------------------------------------------------------------

    it('reads file content correctly', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: 'test content', initialPath: 'test.txt');

        // ACT
        $result = $mockFs->readFile('test.txt');

        // ASSERT
        expect($result)->toBe('test content');
    });

    it('throws IOException when reading non-existent file', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: false, content: '', initialPath: 'missing.txt');

        // ACT & ASSERT
        expect(fn () => $mockFs->readFile('missing.txt'))
            ->toThrow(IOException::class, 'File does not exist: missing.txt');
    });

    it('throws IOException when configured to throw on read', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: 'test', throwOnRead: true, initialPath: 'test.txt');

        // ACT & ASSERT
        expect(fn () => $mockFs->readFile('test.txt'))
            ->toThrow(IOException::class, 'Permission denied');
    });

    //
    // Directory Operations
    // -------------------------------------------------------------------------------

    it('creates directories successfully', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: '', initialPath: 'test.txt');

        // ACT
        $mockFs->mkdir('/new/directory');

        // ASSERT
        expect($mockFs->exists('/new/directory'))->toBeTrue();
    });

    it('throws IOException when configured to throw on mkdir', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: '', throwOnMkdir: true, initialPath: 'test.txt');

        // ACT & ASSERT
        expect(fn () => $mockFs->mkdir('/new/directory'))
            ->toThrow(IOException::class, 'Permission denied');
    });

    //
    // File Write Operations
    // -------------------------------------------------------------------------------

    it('writes file content successfully', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: false, content: '', initialPath: 'test.txt');

        // ACT
        $mockFs->dumpFile('new.txt', 'new content');

        // ASSERT
        expect($mockFs->exists('new.txt'))->toBeTrue();
        expect($mockFs->readFile('new.txt'))->toBe('new content');
    });

    it('throws IOException when configured to throw on dump', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: '', throwOnDump: true, initialPath: 'test.txt');

        // ACT & ASSERT
        expect(fn () => $mockFs->dumpFile('test.txt', 'content'))
            ->toThrow(IOException::class, 'Write failed');
    });
});

describe('mockEnvService', function () {
    it('creates EnvService with mock filesystem', function () {
        // ARRANGE & ACT
        $service = mockEnvService(fileExists: true, fileContent: 'TEST_KEY=value');

        // ASSERT
        expect($service)->toBeInstanceOf(\Bigpixelrocket\DeployerPHP\Services\EnvService::class);
    });
});

describe('mockInventoryService', function () {
    it('creates InventoryService with array data', function () {
        // ARRANGE
        $data = ['servers' => ['web1' => ['host' => 'example.com']]];

        // ACT
        $service = mockInventoryService(fileExists: true, data: $data);

        // ASSERT
        expect($service)->toBeInstanceOf(\Bigpixelrocket\DeployerPHP\Services\InventoryService::class);
    });

    it('creates InventoryService with string data', function () {
        // ARRANGE
        $yamlContent = 'servers:' . PHP_EOL . '  web1:' . PHP_EOL . '    host: example.com';

        // ACT
        $service = mockInventoryService(fileExists: true, data: $yamlContent);

        // ASSERT
        expect($service)->toBeInstanceOf(\Bigpixelrocket\DeployerPHP\Services\InventoryService::class);
    });
});

describe('mockFilesystemService', function () {
    it('creates FilesystemService with mock filesystem', function () {
        // ARRANGE & ACT
        $service = mockFilesystemService(fileExists: true, fileContent: 'test content');

        // ASSERT
        expect($service)->toBeInstanceOf(\Bigpixelrocket\DeployerPHP\Services\FilesystemService::class);
    });
});

describe('setEnv', function () {
    it('sets environment variable', function () {
        // ACT
        setEnv('TEST_VAR', 'test_value');

        // ASSERT
        expect($_ENV['TEST_VAR'])->toBe('test_value');
        expect($_SERVER['TEST_VAR'])->toBe('test_value');
        expect(getenv('TEST_VAR'))->toBe('test_value');

        // CLEANUP
        setEnv('TEST_VAR', null);
    });

    it('unsets environment variable when value is null', function () {
        // ARRANGE
        setEnv('TEST_VAR', 'initial_value');

        // ACT
        setEnv('TEST_VAR', null);

        // ASSERT
        expect(isset($_ENV['TEST_VAR']))->toBeFalse();
        expect(isset($_SERVER['TEST_VAR']))->toBeFalse();
        expect(getenv('TEST_VAR'))->toBeFalse();
    });
});
