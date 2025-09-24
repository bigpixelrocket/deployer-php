<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Services\VersionDetectionService;

/**
 * Application entry point
 */
class App
{
    private static ?Container $container = null;

    private static ?EnvService $envService = null;

    /**
     * Prevent instantiation - this is a static utility class.
     */
    private function __construct()
    {
        // This class should never be instantiated
    }

    //
    // App methods
    // -------------------------------------------------------------------------------

    /**
     * Build and run the Deployer application.
     */
    public static function run(): int
    {
        return self::build(SymfonyApp::class)->run();
    }

    public static function getName(): string
    {
        return 'Deployer PHP';
    }

    public static function getVersion(): string
    {
        return self::build(VersionDetectionService::class)->getVersion();
    }

    //
    // Container methods
    // -------------------------------------------------------------------------------

    /**
     * Build a class instance with auto-wired dependencies.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public static function build(string $className): object
    {
        return self::getContainer()->build($className);
    }

    /**
     * Get the shared container instance.
     */
    public static function getContainer(): Container
    {
        return self::$container ??= new Container();
    }

    //
    // Environment service methods
    // -------------------------------------------------------------------------------

    /**
     * Get an environment variable.
     *
     * @param array<int, string>|string $keys
     */
    public static function env(array|string $keys, bool $required = true): ?string
    {
        return self::getEnvService()->get($keys, $required);
    }

    /**
     * Get the environment service instance.
     */
    public static function getEnvService(): EnvService
    {
        return self::$envService ??= self::build(EnvService::class);
    }
}
