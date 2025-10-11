<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Repositories\SiteRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\FilesystemService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\Services\ProcessService;
use Bigpixelrocket\DeployerPHP\Services\PrompterService;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Bigpixelrocket\DeployerPHP\Services\VersionService;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockFilesystem;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockPrompter;
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
     * Create a mock InventoryService for testing with configurable filesystem behavior.
     *
     * Accepts either array data (auto-converts to YAML) or raw string content.
     * Use arrays for clean test setup, strings for testing YAML parsing edge cases.
     *
     * @example
     *   // Using array data (recommended)
     *   $service = mockInventoryService(
     *       fileExists: true,
     *       data: ['servers' => ['web1' => ['host' => '192.168.1.1']]]
     *   );
     *
     * @example
     *   // Using raw YAML string
     *   $service = mockInventoryService(
     *       fileExists: true,
     *       data: "servers:\n  web1:\n    host: 192.168.1.1"
     *   );
     *
     * @example
     *   // Test write failures
     *   $service = mockInventoryService(fileExists: true, throwOnWrite: true);
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
     * Create a ProcessService for testing.
     *
     * Uses real Filesystem since directory validation requires is_dir() checks.
     * Tests should use real directories (e.g., __DIR__, sys_get_temp_dir()).
     *
     * @example
     *   $proc = mockProcessService();
     *   $process = $proc->run(['echo', 'test'], __DIR__);
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

if (!function_exists('mockPrompter')) {
    /**
     * Create a mock PrompterService for testing.
     *
     * Returns predefined values instead of displaying interactive prompts.
     * Values are consumed in order as prompts are called.
     *
     * @param array<string> $text Text input values
     * @param array<string> $password Password input values
     * @param array<bool> $confirm Confirmation values
     * @param array<int|string> $select Selection values
     * @param array<array<int|string>> $multiselect Multiselection values
     * @param array<string> $suggest Suggestion values
     * @param array<int|string> $search Search values
     * @param array<bool> $pause Pause values
     *
     * @example
     *   // Mock text inputs
     *   $prompter = mockPrompter(text: ['web1', '192.168.1.1']);
     *
     * @example
     *   // Mock confirmations
     *   $prompter = mockPrompter(confirm: [true, false]);
     */
    function mockPrompter(
        array $text = [],
        array $password = [],
        array $confirm = [],
        array $select = [],
        array $multiselect = [],
        array $suggest = [],
        array $search = [],
        array $pause = []
    ): MockPrompter {
        return new MockPrompter($text, $password, $confirm, $select, $multiselect, $suggest, $search, $pause);
    }
}

if (!function_exists('mockVersionService')) {
    /**
     * Create a VersionService for testing with configurable package name and fallback.
     *
     * Uses real Filesystem and ProcessService since git operations require real directory checks.
     *
     * @example
     *   // Default configuration
     *   $service = mockVersionService();
     *
     * @example
     *   // Custom package and fallback
     *   $service = mockVersionService(
     *       packageName: 'vendor/package',
     *       fallback: '1.0.0-dev'
     *   );
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

//
// Repository Layer Mocks
// -------------------------------------------------------------------------------

if (!function_exists('mockServerRepository')) {
    /**
     * Create a ServerRepository for testing with a loaded inventory service.
     *
     * Repository is returned fully initialized with inventory loaded and ready for use.
     *
     * @example
     *   // Empty repository
     *   $repo = mockServerRepository(fileExists: true, data: ['servers' => []]);
     *
     * @example
     *   // Pre-populated with servers
     *   $repo = mockServerRepository(
     *       fileExists: true,
     *       data: ['servers' => [
     *           'web1' => ['host' => '192.168.1.1', 'port' => 22]
     *       ]]
     *   );
     *   $repo->findByName('web1'); // Returns ServerDTO
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
     * Create a SiteRepository for testing with a loaded inventory service.
     *
     * Repository is returned fully initialized with inventory loaded and ready for use.
     *
     * @example
     *   // Empty repository
     *   $repo = mockSiteRepository(fileExists: true, data: ['sites' => []]);
     *
     * @example
     *   // Pre-populated with sites
     *   $repo = mockSiteRepository(
     *       fileExists: true,
     *       data: ['sites' => [
     *           ['domain' => 'example.com', 'repo' => 'git@github.com:user/repo.git', 'branch' => 'main', 'servers' => ['web1']]
     *       ]]
     *   );
     *   $repo->findByDomain('example.com'); // Returns SiteDTO
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
        // Base services
        ?EnvService $env = null,
        ?InventoryService $inventory = null,
        ?ProcessService $proc = null,
        ?PrompterService $prompter = null,

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
        $inventory ??= mockInventoryService($inventoryFileExists, $inventoryData);
        $proc ??= mockProcessService();
        $prompter ??= mockPrompter();
        $servers ??= mockServerRepository($inventoryFileExists, $inventoryData);
        $sites ??= mockSiteRepository($inventoryFileExists, $inventoryData);
        $ssh ??= mockSSHService();

        // Bind services to container (matches BaseCommand constructor order)
        $container->bind(EnvService::class, $env);
        $container->bind(InventoryService::class, $inventory);
        $container->bind(ProcessService::class, $proc);
        $container->bind(PrompterService::class, $prompter);
        $container->bind(ServerRepository::class, $servers);
        $container->bind(SiteRepository::class, $sites);
        $container->bind(SSHService::class, $ssh);

        return $container;
    }
}
