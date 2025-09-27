<?php

declare(strict_types=1);

use Symfony\Component\Filesystem\Filesystem;

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
        bool $throwOnDump = false
    ): Filesystem {
        return new class ($exists, $content, $throwOnRead, $throwOnMkdir, $throwOnDump) extends Filesystem {
            private array $fileSystem = [];
            private bool $dirExists = true;

            public function __construct(
                private readonly bool $initialExists,
                private readonly string $initialContent,
                private readonly bool $throwOnRead,
                private readonly bool $throwOnMkdir,
                private readonly bool $throwOnDump
            ) {
                if ($this->initialExists) {
                    $this->fileSystem['.deployer/inventory.yml'] = $this->initialContent;
                }
                $this->dirExists = !$this->throwOnMkdir;
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
                if (str_ends_with($files, '.deployer')) {
                    return $this->dirExists;
                }

                return isset($this->fileSystem[$files]) || isset($this->fileSystem['.deployer/inventory.yml']);
            }

            public function readFile(string $filename): string
            {
                if ($this->throwOnRead) {
                    throw new \RuntimeException('Permission denied');
                }

                return $this->fileSystem['.deployer/inventory.yml'] ?? $this->initialContent;
            }

            public function mkdir($dirs, int $mode = 0777): void
            {
                if ($this->throwOnMkdir) {
                    throw new \Exception('Permission denied');
                }
                $this->dirExists = true;
            }

            public function dumpFile(string $filename, $content): void
            {
                if ($this->throwOnDump) {
                    throw new \Exception('Write failed');
                }
                $this->fileSystem['.deployer/inventory.yml'] = $content;
            }
        };
    }
}

if (!function_exists('mockEnvService')) {
    /**
     * Create a mock EnvService for testing.
     */
    function mockEnvService(bool $hasFile = true): \Bigpixelrocket\DeployerPHP\Services\EnvService
    {
        $content = $hasFile ? 'API_KEY=test_value' : '';
        return new \Bigpixelrocket\DeployerPHP\Services\EnvService(mockFilesystem($hasFile, $content), new \Symfony\Component\Dotenv\Dotenv());
    }
}

if (!function_exists('mockInventoryService')) {
    /**
     * Create a mock InventoryService for testing.
     */
    function mockInventoryService(bool $hasFile = true): \Bigpixelrocket\DeployerPHP\Services\InventoryService
    {
        $content = $hasFile ? 'servers:' . PHP_EOL . '  web1:' . PHP_EOL . '    host: example.com' : '';
        return new \Bigpixelrocket\DeployerPHP\Services\InventoryService(mockFilesystem($hasFile, $content));
    }
}
