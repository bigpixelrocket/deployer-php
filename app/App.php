<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use Bigpixelrocket\DeployerPHP\Services\EnvService;

/**
 * Application entry point
 */
class App
{
    private static ?Container $container = null;

    private static ?EnvService $envService = null;

    /**
     * Build a class instance with auto-wired dependencies.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public static function build(string $className): object
    {
        return self::container()->build($className);
    }

    /**
     * Get the shared container instance.
     */
    public static function container(): Container
    {
        return self::$container ??= new Container();
    }

    /**
     * Build and run the Deployer application.
     */
    public static function run(): int
    {
        return self::build(Deployer::class)->run();
    }

    /**
     * Get the environment service instance.
     */
    public static function env(): EnvService
    {
        return self::$envService ??= self::build(EnvService::class);
    }
}
