<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Repositories\SiteRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\FilesystemService;
use Bigpixelrocket\DeployerPHP\Services\GitService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\Services\IOService;
use Bigpixelrocket\DeployerPHP\Services\ProcessService;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Bigpixelrocket\DeployerPHP\Services\VersionService;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockFilesystem;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockSSHService;
use Symfony\Component\Dotenv\Dotenv;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Yaml\Yaml;

//
// Environment & Configuration Utilities
// -------------------------------------------------------------------------------

if (!function_exists('setEnv')) {
    /**
     * Set or unset environment variables for testing.
     *
     * @example
     *   setEnv('API_KEY', 'secret');    // Sets environment variable
     *   setEnv('API_KEY', null);         // Unsets environment variable
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

//
// Core Infrastructure Mocks
// -------------------------------------------------------------------------------

if (!function_exists('mockFilesystem')) {
    /**
     * Create a mock filesystem for testing with error simulation and in-memory storage.
     *
     * @example
     *   // Basic usage with file content
     *   $fs = mockFilesystem(exists: true, content: 'data', initialPath: 'config.yml');
     *
     * @example
     *   // Simulate permission errors
     *   $fs = mockFilesystem(exists: true, throwOnRead: true);
     *   $fs->readFile('file'); // Throws IOException
     */
    function mockFilesystem(
        bool $exists = true,
        string $content = '',
        bool $throwOnRead = false,
        bool $throwOnMkdir = false,
        bool $throwOnDump = false,
        string $initialPath = 'inventory.yml'
    ): Filesystem {
        return new MockFilesystem($exists, $content, $throwOnRead, $throwOnMkdir, $throwOnDump, $initialPath);
    }
}

if (!function_exists('mockFilesystemService')) {
    /**
     * Create a FilesystemService with a mock Filesystem for testing.
     *
     * @example
     *   $service = mockFilesystemService(
     *       fileExists: true,
     *       fileContent: 'content',
     *       filePath: 'data.txt'
     *   );
     *   $service->readFile('data.txt'); // 'content'
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

//
// Service Layer Mocks
// -------------------------------------------------------------------------------

if (!function_exists('mockEnvService')) {
    /**
     * Create a mock EnvService for testing with configurable filesystem behavior.
     *
     * @example
     *   // Service with valid .env file
     *   $service = mockEnvService(fileExists: true, fileContent: 'API_KEY=secret');
     *   $service->loadEnvFile();
     *   $service->get('API_KEY'); // 'secret'
     *
     * @example
     *   // Test missing .env file handling
     *   $service = mockEnvService(fileExists: false);
     *   $service->loadEnvFile(); // Handles gracefully
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
     * Create a mock InventoryService for tests with a configurable in-memory inventory file.
     *
     * If $data is an array, it is dumped to YAML and used as the inventory file content; if it is a string, it is used verbatim. The filesystem mock can be configured to simulate missing files or read/write errors.
     *
     * @param bool $fileExists Whether the inventory file should appear to exist.
     * @param array|string $data Array to be converted to YAML or raw YAML string to use as file content.
     * @param bool $throwOnRead If true, the mocked filesystem will throw on read operations.
     * @param bool $throwOnWrite If true, the mocked filesystem will throw on write/dump operations.
     * @return InventoryService An InventoryService backed by a mocked FilesystemService.
     */
    function mockInventoryService(
        bool $fileExists = true,
        array|string $data = [],
        bool $throwOnRead = false,
        bool $throwOnWrite = false
    ): InventoryService {
        // Convert array data to YAML
        $fileContent = match (true) {
            is_array($data) && !empty($data) => Yaml::dump($data, 2, 4, Yaml::DUMP_EMPTY_ARRAY_AS_SEQUENCE),
            is_array($data) => '',
            default => $data,
        };

        $mockFs = mockFilesystem($fileExists, $fileContent, $throwOnRead, false, $throwOnWrite, 'inventory.yml');
        $filesystemService = new FilesystemService($mockFs);
        return new InventoryService($filesystemService);
    }
}

if (!function_exists('mockProcessService')) {
    /**
     * Creates a ProcessService configured for tests.
     *
     * Uses a real FilesystemService (Symfony Filesystem) so directory validation relies on is_dir(); tests should provide real directories (e.g., __DIR__, sys_get_temp_dir()).
     *
     * @return ProcessService A ProcessService backed by a FilesystemService using a real Filesystem.
     */
    function mockProcessService(): ProcessService
    {
        $filesystemService = new FilesystemService(new Filesystem());
        return new ProcessService($filesystemService);
    }
}

if (!function_exists('mockSSHService')) {
    /**
     * Create an SSHService for testing with mocked dependencies.
     *
     * Returns a real SSHService instance with mocked filesystem and env dependencies.
     * Use mockSSHServiceWithBehavior() for simulating connection success/failure.
     *
     * @example
     *   $ssh = mockSSHService();
     *   // Use for testing code that depends on SSHService without network calls
     */
    function mockSSHService(): SSHService
    {
        $envService = mockEnvService(fileExists: true);
        $filesystemService = new FilesystemService(new Filesystem());

        return new SSHService($envService, $filesystemService);
    }
}

if (!function_exists('mockSSHServiceWithBehavior')) {
    /**
     * Create a mock SSHService that simulates connection success/failure.
     *
     * @param bool $canConnect Whether SSH connection should succeed (true) or fail (false)
     *
     * @example
     *   // Simulate successful connection
     *   $ssh = mockSSHServiceWithBehavior(canConnect: true);
     *   $ssh->assertCanConnect('host', 22, 'user'); // No exception
     *
     * @example
     *   // Simulate connection failure
     *   $ssh = mockSSHServiceWithBehavior(canConnect: false);
     *   $ssh->assertCanConnect('host', 22, 'user'); // Throws RuntimeException
     */
    function mockSSHServiceWithBehavior(bool $canConnect = true): SSHService
    {
        return new MockSSHService($canConnect);
    }
}

if (!function_exists('mockIOService')) {
    /**
     * Create an IOService for testing.
     *
     * Returns a plain IOService instance. Must call initialize() before using I/O methods.
     * Prompts will run in non-interactive mode during tests.
     *
     * @example
     *   $io = mockIOService();
     *   $io->initialize($command, $input, $output);
     */
    function mockIOService(): IOService
    {
        return new IOService();
    }
}

if (!function_exists('mockVersionService')) {
    /**
     * Create a VersionService configured for tests with an optional package name and fallback version.
     *
     * Uses a real Filesystem and ProcessService because version resolution may perform git/directory checks.
     *
     * @param string|null $packageName Optional package name to use (e.g., "vendor/package"). If omitted the service uses its default discovery.
     * @param string|null $fallback Optional fallback version string used when the package/version cannot be determined.
     * @return VersionService The configured VersionService instance.
     */
    function mockVersionService(
        ?string $packageName = null,
        ?string $fallback = null
    ): VersionService {
        $filesystemService = new FilesystemService(new Filesystem());
        $proc = new ProcessService($filesystemService);

        return match (true) {
            $packageName !== null && $fallback !== null => new VersionService($proc, $filesystemService, $packageName, $fallback),
            $packageName !== null => new VersionService($proc, $filesystemService, $packageName),
            default => new VersionService($proc, $filesystemService),
        };
    }
}

if (!function_exists('mockGitService')) {
    /**
     * Create a GitService for testing with mocked ProcessService.
     *
     * Returns a GitService instance with a real ProcessService for testing git command execution.
     *
     * @example
     *   $git = mockGitService();
     *   // Use in tests that need git functionality
     */
    function mockGitService(): GitService
    {
        $proc = mockProcessService();
        return new GitService($proc);
    }
}

//
// Repository Layer Mocks
// -------------------------------------------------------------------------------

if (!function_exists('mockServerRepository')) {
    /**
     * Create a ServerRepository preloaded with inventory data for use in tests.
     *
     * The returned repository has its inventory loaded from a mocked InventoryService and is ready for immediate use.
     *
     * @param bool $fileExists Whether the mocked inventory file should exist.
     * @param array|string $data Inventory content to load; an array will be converted to YAML, a string will be used as raw file content.
     * @param bool $throwOnRead If true, the mocked filesystem will throw on read operations to simulate read errors.
     * @param bool $throwOnWrite If true, the mocked filesystem will throw on write/dump operations to simulate write errors.
     * @return ServerRepository A ServerRepository instance with inventory loaded from the mocked service.
     */
    function mockServerRepository(
        bool $fileExists = true,
        array|string $data = [],
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

if (!function_exists('mockSiteRepository')) {
    /**
     * Creates a SiteRepository for testing with its inventory loaded from a mocked InventoryService.
     *
     * @param bool $fileExists Whether the underlying inventory file should appear to exist.
     * @param array|string $data Inventory contents as an array (converted to YAML) or raw YAML string.
     * @param bool $throwOnRead If true, the mocked inventory service will throw on read operations.
     * @param bool $throwOnWrite If true, the mocked inventory service will throw on write operations.
     * @return SiteRepository A repository instance with inventory loaded and ready for use.
     */
    function mockSiteRepository(
        bool $fileExists = true,
        array|string $data = [],
        bool $throwOnRead = false,
        bool $throwOnWrite = false
    ): SiteRepository {
        $inventory = mockInventoryService($fileExists, $data, $throwOnRead, $throwOnWrite);
        $inventory->loadInventoryFile();

        $repository = new SiteRepository();
        $repository->loadInventory($inventory);

        return $repository;
    }
}

//
// Command & Integration Test Mocks
// -------------------------------------------------------------------------------

if (!function_exists('mockCommandContainer')) {
    /**
     * Create a Container with mocked dependencies for command testing.
     *
     * Returns a Container with sensible mock defaults for all BaseCommand dependencies.
     * Override specific services by passing them as arguments.
     *
     * @example
     *   // Build command with default mocks
     *   $container = mockCommandContainer();
     *   $command = $container->build(ServerListCommand::class);
     *
     * @example
     *   // Override SSH service for connection testing
     *   $ssh = mockSSHServiceWithBehavior(canConnect: false);
     *   $container = mockCommandContainer(ssh: $ssh);
     *   $command = $container->build(ServerAddCommand::class);
     *
     * @example
     *   // Override inventory data for pre-populated servers
     *   $container = mockCommandContainer(
     *       inventoryData: ['servers' => ['web1' => ['host' => '192.168.1.1']]]
     *   );
     *   $command = $container->build(ServerListCommand::class);
     */
    function mockCommandContainer(
        // Base services (alphabetical order)
        ?EnvService $env = null,
        ?GitService $git = null,
        ?InventoryService $inventory = null,
        ?IOService $io = null,
        ?ProcessService $proc = null,

        // Servers & sites
        ?ServerRepository $servers = null,
        ?SiteRepository $sites = null,
        ?SSHService $ssh = null,

        // Configuration
        bool $envFileExists = true,
        string $envContent = 'API_KEY=test_value',
        bool $inventoryFileExists = true,
        array|string $inventoryData = []
    ): Container {
        $container = new Container();

        // Build or use provided services (matches BaseCommand constructor order)
        $env ??= mockEnvService($envFileExists, $envContent);
        $git ??= mockGitService();
        $inventory ??= mockInventoryService($inventoryFileExists, $inventoryData);
        $io ??= mockIOService();
        $proc ??= mockProcessService();
        $servers ??= mockServerRepository($inventoryFileExists, $inventoryData);
        $sites ??= mockSiteRepository($inventoryFileExists, $inventoryData);
        $ssh ??= mockSSHService();

        // Bind services to container (matches BaseCommand constructor order)
        $container->bind(EnvService::class, $env);
        $container->bind(GitService::class, $git);
        $container->bind(InventoryService::class, $inventory);
        $container->bind(IOService::class, $io);
        $container->bind(ProcessService::class, $proc);
        $container->bind(ServerRepository::class, $servers);
        $container->bind(SiteRepository::class, $sites);
        $container->bind(SSHService::class, $ssh);

        return $container;
    }
}
