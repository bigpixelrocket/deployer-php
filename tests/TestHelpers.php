<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\FilesystemService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\Services\ProcessFactory;
use Bigpixelrocket\DeployerPHP\Services\VersionService;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Exception\IOException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

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
     * Create a mock filesystem for testing with error simulation and in-memory storage.
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
            private array $files = [];
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

                // Check files (direct match or path ends with stored key)
                if (isset($this->files[$files])) {
                    return true;
                }

                foreach (array_keys($this->files) as $storedPath) {
                    if (str_ends_with($files, (string) $storedPath)) {
                        return true;
                    }
                }

                // Check directories (match exact path only)
                foreach ($this->directories as $dir) {
                    if (rtrim($files, '/\\') === rtrim((string) $dir, '/\\')) {
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
                if (isset($this->files[$filename])) {
                    return $this->files[$filename];
                }

                // Try path ending match
                foreach ($this->files as $storedPath => $content) {
                    if (str_ends_with($filename, (string) $storedPath)) {
                        return $content;
                    }
                }

                throw new IOException("File does not exist: {$filename}", 0, null, $filename);
            }

            public function mkdir(string|iterable $dirs, int $mode = 0777): void
            {
                if ($this->throwOnMkdir) {
                    throw new IOException('Permission denied', 0, null, (string) $dirs);
                }

                $this->directories[] = (string) $dirs;
            }

            public function dumpFile(string $filename, $content): void
            {
                if ($this->throwOnDump) {
                    throw new IOException('Write failed', 0, null, $filename);
                }

                $this->files[$filename] = $content;
            }
        };
    }
}

if (!function_exists('mockEnvService')) {
    /**
     * Create a mock EnvService for testing with configurable filesystem behavior.
     */
    function mockEnvService(
        bool $fileExists = true,
        string $fileContent = 'API_KEY=test_value',
        bool $throwOnRead = false
    ): EnvService {
        $mockFs = mockFilesystem($fileExists, $fileContent, $throwOnRead, false, false, '.env');
        $filesystemService = new FilesystemService($mockFs);
        return new EnvService($filesystemService, new Dotenv());
    }
}

if (!function_exists('mockInventoryService')) {
    /**
     * Create a mock InventoryService for testing with configurable filesystem behavior.
     * Accepts either array data (auto-converts to YAML) or raw string content.
     */
    function mockInventoryService(
        bool $fileExists = true,
        array|string $data = '',
        bool $throwOnRead = false,
        bool $throwOnWrite = false
    ): InventoryService {
        // Convert array data to YAML
        if (is_array($data)) {
            $fileContent = empty($data) ? '' : Yaml::dump($data, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE);
        } else {
            $defaultContent = 'servers:' . PHP_EOL . '  web1:' . PHP_EOL . '    host: example.com';
            $fileContent = $data ?: ($fileExists ? $defaultContent : '');
        }

        $mockFs = mockFilesystem($fileExists, $fileContent, $throwOnRead, false, $throwOnWrite, 'inventory.yml');
        $filesystemService = new FilesystemService($mockFs);
        return new InventoryService($filesystemService);
    }
}

if (!function_exists('mockFilesystemService')) {
    /**
     * Create a FilesystemService with a mock Filesystem for testing.
     */
    function mockFilesystemService(
        bool $fileExists = true,
        string $fileContent = '',
        bool $throwOnRead = false,
        bool $throwOnMkdir = false,
        bool $throwOnWrite = false,
        string $filePath = 'test.txt'
    ): FilesystemService {
        $mockFs = mockFilesystem($fileExists, $fileContent, $throwOnRead, $throwOnMkdir, $throwOnWrite, $filePath);
        return new FilesystemService($mockFs);
    }
}

if (!function_exists('mockProcessFactory')) {
    /**
     * Create a ProcessFactory for testing.
     *
     * Uses real Filesystem since directory validation requires is_dir() checks.
     * Tests should use real directories (e.g., __DIR__, sys_get_temp_dir()).
     */
    function mockProcessFactory(): ProcessFactory
    {
        $filesystemService = new FilesystemService(new Filesystem());
        return new ProcessFactory($filesystemService);
    }
}

if (!function_exists('mockVersionService')) {
    /**
     * Create a VersionService for testing with configurable package name and fallback.
     *
     * Uses real Filesystem and ProcessFactory since git operations require real directory checks.
     */
    function mockVersionService(
        ?string $packageName = null,
        ?string $fallback = null
    ): VersionService {
        $filesystemService = new FilesystemService(new Filesystem());
        $processFactory = new ProcessFactory($filesystemService);

        // Conditionally pass parameters to use VersionService defaults
        if ($packageName !== null && $fallback !== null) {
            return new VersionService($processFactory, $filesystemService, $packageName, $fallback);
        }

        if ($packageName !== null) {
            return new VersionService($processFactory, $filesystemService, $packageName);
        }

        return new VersionService($processFactory, $filesystemService);
    }
}

if (!function_exists('mockServerRepository')) {
    /**
     * Create a ServerRepository for testing with a loaded inventory service.
     */
    function mockServerRepository(
        bool $fileExists = true,
        array|string $data = '',
        bool $throwOnRead = false,
        bool $throwOnWrite = false
    ): ServerRepository {
        $inventory = mockInventoryService($fileExists, $data, $throwOnRead, $throwOnWrite);
        $inventory->loadInventoryFile();

        $repository = new ServerRepository();
        $repository->loadInventory($inventory);

        return $repository;
    }
}
