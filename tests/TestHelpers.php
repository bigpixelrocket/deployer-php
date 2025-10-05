<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Repositories\ServerRepository;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\FilesystemService;
use Bigpixelrocket\DeployerPHP\Services\InventoryService;
use Bigpixelrocket\DeployerPHP\Services\ProcessFactory;
use Bigpixelrocket\DeployerPHP\Services\SSHService;
use Bigpixelrocket\DeployerPHP\Services\VersionService;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockFilesystem;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockPrompter;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\MockSSHService;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestConsoleCommand;
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
        string $initialPath = '.deployer/inventory.yml'
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

if (!function_exists('mockProcessFactory')) {
    /**
     * Create a ProcessFactory for testing.
     *
     * Uses real Filesystem since directory validation requires is_dir() checks.
     * Tests should use real directories (e.g., __DIR__, sys_get_temp_dir()).
     *
     * @example
     *   $factory = mockProcessFactory();
     *   $process = $factory->create(['echo', 'test'], __DIR__);
     */
    function mockProcessFactory(): ProcessFactory
    {
        $filesystemService = new FilesystemService(new Filesystem());
        return new ProcessFactory($filesystemService);
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
     * Uses real Filesystem and ProcessFactory since git operations require real directory checks.
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
        $processFactory = new ProcessFactory($filesystemService);

        return match (true) {
            $packageName !== null && $fallback !== null => new VersionService($processFactory, $filesystemService, $packageName, $fallback),
            $packageName !== null => new VersionService($processFactory, $filesystemService, $packageName),
            default => new VersionService($processFactory, $filesystemService),
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

//
// Command & Integration Test Mocks
// -------------------------------------------------------------------------------

if (!function_exists('mockTestConsoleCommand')) {
    /**
     * Create a TestConsoleCommand for testing with mocked dependencies.
     *
     * Returns a fully configured command with all dependencies injected.
     * Useful for testing BaseCommand, console traits, and server helpers.
     *
     * @example
     *   // Default configuration
     *   $command = mockTestConsoleCommand();
     *
     * @example
     *   // Custom environment and inventory
     *   $command = mockTestConsoleCommand(
     *       envFileExists: true,
     *       envContent: 'API_KEY=secret',
     *       inventoryFileExists: true,
     *       inventoryData: ['servers' => []]
     *   );
     */
    function mockTestConsoleCommand(
        bool $envFileExists = true,
        string $envContent = 'API_KEY=test_value',
        bool $inventoryFileExists = true,
        array|string $inventoryData = []
    ): TestConsoleCommand {
        $container = new Container();
        $env = mockEnvService($envFileExists, $envContent);
        $inventory = mockInventoryService($inventoryFileExists, $inventoryData);
        $servers = mockServerRepository($inventoryFileExists, $inventoryData);
        $ssh = mockSSHService();
        $prompter = mockPrompter();

        return new TestConsoleCommand($container, $env, $inventory, $servers, $ssh, $prompter);
    }
}
