<?php

declare(strict_types=1);

require_once __DIR__ . '/../TestHelpers.php';

use Symfony\Component\Filesystem\Exception\IOException;

describe('mockFilesystem', function () {
    //
    // File Existence Checks
    // -------------------------------------------------------------------------------

    it('validates file and directory existence logic', function ($fileExists, $content, $initialPath, $checkPath, $expected, $description) {
        // ARRANGE
        $mockFs = mockFilesystem(exists: $fileExists, content: $content, initialPath: $initialPath);

        // ACT & ASSERT
        expect($mockFs->exists($checkPath))->toBe($expected, $description);
    })->with([
        'directory exists' => [true, 'content', '.deployer/inventory.yml', '.deployer', true, 'directory should exist'],
        'directory with trailing slash' => [true, 'content', '.deployer/inventory.yml', '.deployer/', true, 'directory with trailing slash should exist'],
        'existing file' => [true, 'content', '.deployer/inventory.yml', '.deployer/inventory.yml', true, 'existing file should exist'],
        'non-existent file in existing dir' => [true, 'content', '.deployer/inventory.yml', '.deployer/missing.yml', false, 'non-existent file should not exist'],
        'directory exists when file does not' => [false, '', '.deployer/inventory.yml', '.deployer', true, 'directory should exist'],
        'non-existent file (no substring match)' => [false, '', '.deployer/inventory.yml', '.deployer/inventory.yml', false, 'non-existent file should not exist even if directory matches'],
        'different path no substring match' => [false, '', '.deployer/inventory.yml', '/path/to/.deployer/config.yml', false, 'non-existent file with directory substring should not exist'],
        'direct path match' => [true, 'test', 'inventory.yml', 'inventory.yml', true, 'direct match should work'],
        'path ending match' => [true, 'test', 'inventory.yml', '/path/to/inventory.yml', true, 'path ending match should work'],
        'different path ending' => [true, 'test', 'inventory.yml', '/different/inventory.yml', true, 'different path ending should work'],
    ]);

    it('handles iterable file checks correctly', function () {
        // ARRANGE
        $mockFs = mockFilesystem(exists: true, content: 'test', initialPath: '.env');
        $mockFs->dumpFile('.env.example', 'example');

        // ACT & ASSERT
        expect($mockFs->exists(['.env', '.env.example']))->toBeTrue('all files exist')
            ->and($mockFs->exists(['.env', '.missing']))->toBeFalse('one file missing');
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
        expect($mockFs->exists('new.txt'))->toBeTrue()
            ->and($mockFs->readFile('new.txt'))->toBe('new content');
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
        // ARRANGE
        $service = mockEnvService(fileExists: true, fileContent: 'TEST_KEY=test_value');
        $service->loadEnvFile();

        // ACT
        $result = $service->get('TEST_KEY');

        // ASSERT
        expect($result)->toBe('test_value');
    });
});

describe('mockInventoryService', function () {
    it('creates InventoryService with various data formats', function ($data) {
        // ARRANGE
        $service = mockInventoryService(fileExists: true, data: $data);
        $service->loadInventoryFile();

        // ACT
        $result = $service->get('widgets.alpha.color');

        // ASSERT
        expect($result)->toBe('red');
    })->with([
        'array data' => [['widgets' => ['alpha' => ['color' => 'red']]]],
        'string data' => ['widgets:' . PHP_EOL . '  alpha:' . PHP_EOL . '    color: red'],
    ]);
});

describe('mockFilesystemService', function () {
    it('creates FilesystemService with mock filesystem', function () {
        // ARRANGE
        $service = mockFilesystemService(fileExists: true, fileContent: 'test content', filePath: 'test.txt');

        // ACT
        $exists = $service->exists('test.txt');
        $content = $service->readFile('test.txt');

        // ASSERT
        expect($exists)->toBeTrue()
            ->and($content)->toBe('test content');
    });
});

describe('mockTestConsoleCommand', function () {
    it('creates TestConsoleCommand with mocked dependencies', function () {
        // ACT
        $command = mockTestConsoleCommand();

        // ASSERT
        expect($command)->toBeInstanceOf(\Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand::class);
    });
});

describe('setEnv', function () {
    it('manages environment variables', function ($initialValue, $newValue, $expectSet) {
        // ARRANGE
        if ($initialValue !== null) {
            setEnv('TEST_VAR', $initialValue);
        }

        // ACT
        setEnv('TEST_VAR', $newValue);

        // ASSERT
        if ($expectSet) {
            expect($_ENV['TEST_VAR'])->toBe($newValue)
                ->and($_SERVER['TEST_VAR'])->toBe($newValue)
                ->and(getenv('TEST_VAR'))->toBe($newValue);
        } else {
            expect(isset($_ENV['TEST_VAR']))->toBeFalse()
                ->and(isset($_SERVER['TEST_VAR']))->toBeFalse()
                ->and(getenv('TEST_VAR'))->toBeFalse();
        }

        // CLEANUP
        setEnv('TEST_VAR', null);
    })->with([
        'sets variable' => [null, 'test_value', true],
        'unsets when null' => ['initial_value', null, false],
    ]);
});
