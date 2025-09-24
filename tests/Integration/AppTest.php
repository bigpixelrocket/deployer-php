<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\App;
use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Services\EnvService;
use Bigpixelrocket\DeployerPHP\Deployer;

describe('App', function () {

    it('provides singleton container instance', function () {
        // ACT
        $container1 = App::getContainer();
        $container2 = App::getContainer();

        // ASSERT
        expect($container1)->toBeInstanceOf(Container::class)
            ->and($container1)->toBe($container2); // Same instance
    });

    it('delegates build to singleton container', function () {
        // ARRANGE
        $container = App::getContainer();

        // ACT
        $service = App::build(EnvService::class);

        // ASSERT - Verify App::build() produces same result as container->build()
        $directBuild = $container->build(EnvService::class);

        expect($service)->toBeInstanceOf(EnvService::class)
            ->and($service)->not->toBe($directBuild) // Different instances (not singleton services)
            ->and($service::class)->toBe($directBuild::class); // Same class
    });

    it('can build deployer instance via delegation', function () {
        // ARRANGE & ACT - Test that App can build the Deployer it would run
        $deployer = App::build(Deployer::class);

        // ASSERT - Verify delegation works for the class that run() would build
        expect($deployer)->toBeInstanceOf(Deployer::class);

        // Note: We don't test run() in unit tests - it's too integrated with console I/O
        // Integration tests verify the full App::run() behavior
    });

    it('provides singleton environment service instance', function () {
        // ACT
        $env1 = App::getEnvService();
        $env2 = App::getEnvService();

        // ASSERT
        expect($env1)->toBeInstanceOf(EnvService::class)
            ->and($env1)->toBe($env2); // Same instance (singleton)
    });

});
