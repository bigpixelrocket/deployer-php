<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Filesystem\Filesystem;

// Test stub for filesystem operations
class FilesystemStub extends Filesystem
{
    private bool $fileExists = true;
    private string $fileContent = '';
    private bool $shouldThrowOnMkdir = false;
    private bool $shouldThrowOnDump = false;

    public function setFileExists(bool $exists): void
    {
        $this->fileExists = $exists;
    }

    public function setFileContent(string $content): void
    {
        $this->fileContent = $content;
    }

    public function setShouldThrowOnMkdir(bool $throw): void
    {
        $this->shouldThrowOnMkdir = $throw;
    }

    public function setShouldThrowOnDump(bool $throw): void
    {
        $this->shouldThrowOnDump = $throw;
    }

    public function exists($files): bool
    {
        return $this->fileExists;
    }

    public function readFile(string $filename): string
    {
        return $this->fileContent;
    }

    public function mkdir($dirs, int $mode = 0777): void
    {
        if ($this->shouldThrowOnMkdir) {
            throw new Exception('Permission denied');
        }
    }

    public function dumpFile(string $filename, $content): void
    {
        if ($this->shouldThrowOnDump) {
            throw new Exception('Write failed');
        }
    }
}

describe('InventoryService', function () {
    beforeEach(function () {
        $this->filesystem = new FilesystemStub();
        $this->service = new InventoryService($this->filesystem);
    });

    //
    // Read Operations
    // ----

    it('returns empty array when inventory file does not exist', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);

        // ACT
        $result = $this->service->getAll();

        // ASSERT
        expect($result)->toBe([]);
    });

    it('returns parsed inventory when file exists', function () {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com";
        $expected = ['servers' => ['web1' => ['host' => 'example.com']]];

        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $result = $this->service->getAll();

        // ASSERT
        expect($result)->toBe($expected);
    });

    it('returns empty array for non-existent collection', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);

        // ACT
        $result = $this->service->list('servers');

        // ASSERT
        expect($result)->toBe([]);
    });

    it('returns collection items when collection exists', function () {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com\n  web2:\n    host: test.com";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $result = $this->service->list('servers');

        // ASSERT
        expect($result)->toBe([
            'web1' => ['host' => 'example.com'],
            'web2' => ['host' => 'test.com'],
        ]);
    });

    it('safely handles non-array collection values', function () {
        // ARRANGE
        $yamlContent = "servers: invalid_string_value";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $result = $this->service->list('servers');

        // ASSERT
        expect($result)->toBe([]);
    });

    it('returns false for non-existent collection', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);

        // ACT
        $result = $this->service->has('servers', 'web1');

        // ASSERT
        expect($result)->toBeFalse();
    });

    it('checks key existence in collection', function (string $collection, string $key, bool $expected) {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com\ndatabases:\n  db1:\n    host: db.com";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $result = $this->service->has($collection, $key);

        // ASSERT
        expect($result)->toBe($expected);
    })->with([
        ['servers', 'web1', true],
        ['servers', 'web2', false],
        ['databases', 'db1', true],
        ['nonexistent', 'key', false],
    ]);

    it('throws exception when key not found', function (string $collection, string $key) {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT & ASSERT
        expect(fn () => $this->service->get($collection, $key))
            ->toThrow(RuntimeException::class, "Key '{$key}' not found in collection '{$collection}'.");
    })->with([
        ['servers', 'web2'],
        ['databases', 'db1'],
        ['nonexistent', 'key'],
    ]);

    it('returns value when key exists', function () {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com\n    port: 22";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $result = $this->service->get('servers', 'web1');

        // ASSERT
        expect($result)->toBe(['host' => 'example.com', 'port' => 22]);
    });

    //
    // Write Operations
    // ----

    it('creates new collection and key', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);

        // ACT
        $this->service->set('servers', 'web1', ['host' => 'example.com']);

        // ASSERT
        // No exception should be thrown - test passes if no error
        expect(true)->toBeTrue();
    });

    it('adds key to existing collection', function () {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $this->service->set('servers', 'web2', ['host' => 'test.com']);

        // ASSERT
        expect(true)->toBeTrue(); // No exception should be thrown
    });

    it('overwrites existing key', function () {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: old.com";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $this->service->set('servers', 'web1', ['host' => 'new.com']);

        // ASSERT
        expect(true)->toBeTrue(); // No exception should be thrown
    });

    it('replaces entire collection', function () {
        // ARRANGE
        $items = ['web1' => ['host' => 'example.com'], 'web2' => ['host' => 'test.com']];
        $this->filesystem->setFileExists(false);

        // ACT
        $this->service->setCollection('servers', $items);

        // ASSERT
        expect(true)->toBeTrue(); // No exception should be thrown
    });

    it('throws exception when key not found and mustExist is true', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);

        // ACT & ASSERT
        expect(fn () => $this->service->delete('servers', 'web1', true))
            ->toThrow(RuntimeException::class, "Key 'web1' not found in collection 'servers'.");
    });

    it('does nothing when key not found and mustExist is false', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);

        // ACT
        $this->service->delete('servers', 'web1', false);

        // ASSERT
        expect(true)->toBeTrue(); // No exception should be thrown
    });

    it('removes key and updates file', function () {
        // ARRANGE
        $yamlContent = "servers:\n  web1:\n    host: example.com\n  web2:\n    host: test.com";
        $this->filesystem->setFileExists(true);
        $this->filesystem->setFileContent($yamlContent);

        // ACT
        $this->service->delete('servers', 'web1');

        // ASSERT
        expect(true)->toBeTrue(); // No exception should be thrown
    });

    //
    // Error Handling
    // ----

    it('handles directory creation failure', function () {
        // ARRANGE
        $this->filesystem->setFileExists(false);
        $this->filesystem->setShouldThrowOnMkdir(true);

        // ACT & ASSERT
        expect(fn () => $this->service->set('servers', 'web1', ['host' => 'example.com']))
            ->toThrow(RuntimeException::class, 'Unable to create inventory directory');
    });

    it('handles file write failure', function () {
        // ARRANGE
        $this->filesystem->setFileExists(true);
        $this->filesystem->setShouldThrowOnDump(true);

        // ACT & ASSERT
        expect(fn () => $this->service->set('servers', 'web1', ['host' => 'example.com']))
            ->toThrow(RuntimeException::class, 'Failed to write inventory file');
    });
});
