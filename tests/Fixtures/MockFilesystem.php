<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Mock filesystem for testing with error simulation and in-memory storage.
 *
 * Simulates filesystem operations without touching disk, supporting:
 * - File existence checks with path matching
 * - File read/write operations
 * - Directory creation
 * - Configurable error scenarios (permission denied, write failures, etc.)
 *
 * @example
 *   $fs = new MockFilesystem(
 *       initialExists: true,
 *       initialContent: 'test data',
 *       initialPath: 'config.yml'
 *   );
 *   $fs->exists('config.yml'); // true
 *   $fs->readFile('config.yml'); // 'test data'
 */
class MockFilesystem extends Filesystem
{
    /** @var array<string, string> */
    private array $files = [];

    /** @var array<int, string> */
    private array $directories = [];

    public function __construct(
        private readonly bool $initialExists,
        private readonly string $initialContent,
        private readonly bool $throwOnRead,
        private readonly bool $throwOnMkdir,
        private readonly bool $throwOnDump,
        private readonly string $initialPath
    ) {
        if ($this->initialExists) {
            $this->files[$this->initialPath] = $this->initialContent;
        }
        $this->directories = $this->throwOnMkdir ? [] : ['.deployer'];
    }

    /**
     * @param iterable<string>|string $files
     */
    public function exists(string|iterable $files): bool
    {
        if (is_iterable($files)) {
            foreach ($files as $file) {
                if (!$this->exists($file)) {
                    return false;
                }
            }
            return true;
        }

        // Check files (direct match or basename match)
        if (isset($this->files[$files])) {
            return true;
        }

        foreach (array_keys($this->files) as $storedPath) {
            // Match if the basename matches or if it's a path component match
            if (basename($files) === $storedPath || str_ends_with($files, '/'.$storedPath)) {
                return true;
            }
        }

        // Check directories (match exact path only)
        foreach ($this->directories as $dir) {
            if (rtrim($files, '/\\') === rtrim($dir, '/\\')) {
                return true;
            }
        }

        return false;
    }

    public function readFile(string $filename): string
    {
        if ($this->throwOnRead) {
            throw new IOException('Permission denied', 0, null, $filename);
        }

        // Try direct match first
        if (array_key_exists($filename, $this->files)) {
            return $this->files[$filename];
        }

        // Try basename or path component match
        foreach ($this->files as $storedPath => $content) {
            // Match if the basename matches or if it's a path component match
            if (basename($filename) === $storedPath || str_ends_with($filename, '/'.$storedPath)) {
                return $content;
            }
        }

        throw new IOException("File does not exist: {$filename}", 0, null, $filename);
    }

    /**
     * @param iterable<string>|string $dirs
     */
    public function mkdir(string|iterable $dirs, int $mode = 0777): void
    {
        if (is_string($dirs)) {
            if ($this->throwOnMkdir) {
                throw new IOException('Permission denied', 0, null, $dirs);
            }
            $this->directories[] = $dirs;
        } else {
            // Handle iterable of directories
            foreach ($dirs as $dir) {
                $this->mkdir($dir, $mode);
            }
        }
    }

    public function dumpFile(string $filename, mixed $content): void
    {
        if ($this->throwOnDump) {
            throw new IOException('Write failed', 0, null, $filename);
        }

        $this->files[$filename] = (string) $content;
    }
}
