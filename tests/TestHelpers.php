<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Filesystem\Exception\IOException;

if (!function_exists('setEnv')) {
    /**
     * Set or unset environment variables for testing.
     */
    function setEnv(string $key, ?string $value): void
    {
        if ($value === null) {
            unset($_ENV[$key], $_SERVER[$key]);
            putenv("{$key}");
        } else {
            $_ENV[$key] = $value;
            $_SERVER[$key] = $value;
            putenv("{$key}={$value}");
        }
    }
}

if (!function_exists('mockFilesystem')) {
    /**
     * Create a mock filesystem for testing with comprehensive error simulation and in-memory storage.
     */
    function mockFilesystem(
        bool $exists = true,
        string $content = '',
        bool $throwOnRead = false,
        bool $throwOnMkdir = false,
        bool $throwOnDump = false,
        string $initialPath = '.deployer/inventory.yml'
    ): Filesystem {
        return new class ($exists, $content, $throwOnRead, $throwOnMkdir, $throwOnDump, $initialPath) extends Filesystem {
            private array $fileSystem = [];
            private bool $dirExists = true;

            public function __construct(
                private readonly bool $initialExists,
                private readonly string $initialContent,
                private readonly bool $throwOnRead,
                private readonly bool $throwOnMkdir,
                private readonly bool $throwOnDump,
                private readonly string $initialPath
            ) {
                if ($this->initialExists) {
                    $this->fileSystem[$this->initialPath] = $this->initialContent;
                }
                $this->dirExists = !$this->throwOnMkdir;
            }

            private function normalizePath(string $path): string
            {
                return str_replace('\\', '/', $path);
            }

            private function getTargetKey(string $path): string
            {
                $normalized = $this->normalizePath($path);
                if ($normalized === $this->initialPath || str_ends_with($normalized, '/' . $this->initialPath)) {
                    return $this->initialPath;
                }
                return $normalized;
            }

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

                // Handle directory checks
                $normalized = $this->normalizePath($files);
                if (str_ends_with($normalized, '.deployer')) {
                    return $this->dirExists;
                }

                $targetKey = $this->getTargetKey($files);
                return isset($this->fileSystem[$targetKey]);
            }

            public function readFile(string $filename): string
            {
                if ($this->throwOnRead) {
                    throw new IOException('Permission denied', 0, null, $filename);
                }

                $targetKey = $this->getTargetKey($filename);
                if (!isset($this->fileSystem[$targetKey])) {
                    throw new IOException("File does not exist: {$filename}", 0, null, $filename);
                }

                return $this->fileSystem[$targetKey];
            }

            public function mkdir($dirs, int $mode = 0777): void
            {
                if ($this->throwOnMkdir) {
                    throw new IOException('Permission denied', 0, null, (string) $dirs);
                }
                unset($dirs, $mode);

                $this->dirExists = true;
            }

            public function dumpFile(string $filename, $content): void
            {
                if ($this->throwOnDump) {
                    throw new IOException('Write failed', 0, null, $filename);
                }
                $targetKey = $this->getTargetKey($filename);
                $this->fileSystem[$targetKey] = $content;
            }
        };
    }
}

if (!function_exists('mockEnvService')) {
    /**
     * Create a mock EnvService for testing.
     */
    function mockEnvService(bool $hasFile = true): EnvService
    {
        $content = $hasFile ? 'API_KEY=test_value' : '';
        return new EnvService(mockFilesystem($hasFile, $content, false, false, false, '.env'), new Dotenv());
    }
}

if (!function_exists('mockInventoryService')) {
    /**
     * Create a mock InventoryService for testing.
     */
    function mockInventoryService(bool $hasFile = true): InventoryService
    {
        $content = $hasFile ? 'servers:' . PHP_EOL . '  web1:' . PHP_EOL . '    host: example.com' : '';
        return new InventoryService(mockFilesystem($hasFile, $content, false, false, false, '.deployer/inventory.yml'));
    }
}
