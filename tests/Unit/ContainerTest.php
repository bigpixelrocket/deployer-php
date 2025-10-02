<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Unit;

use Bigpixelrocket\DeployerPHP\Container;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\AbstractClass;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\CircularA;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\NoConstructorService;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\PrivateConstructor;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithDefaults;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithIntersectionType;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithMultipleDeps;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithOptionalClassDep;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithScalarParam;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithUnionAndCircular;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithUnionType;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\ServiceWithUnresolvableDependency;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\SimpleService;
use Bigpixelrocket\DeployerPHP\Tests\Fixtures\TestInterface;

//
// Load test fixtures
// -------------------------------------------------------------------------------

require_once __DIR__ . '/../Fixtures/ContainerFixtures.php';

//
// Unit tests
// -------------------------------------------------------------------------------

describe('Container', function () {
    beforeEach(function () {
        $this->container = new Container();
    });

    it('builds classes without dependencies', function () {
        // ARRANGE & ACT
        $simple = $this->container->build(SimpleService::class);
        $noConstructor = $this->container->build(NoConstructorService::class);

        // ASSERT
        expect($simple->getName())->toBe('simple')
            ->and($noConstructor->getType())->toBe('no-constructor')
            ->and($this->container->build(SimpleService::class))->not->toBe($simple); // New instances
    });

    it('resolves dependencies recursively', function () {
        // ARRANGE & ACT
        $service = $this->container->build(ServiceWithMultipleDeps::class);

        // ASSERT
        expect($service->getSimple()->getName())->toBe('simple')
            ->and($service->getComplex()->getDependency()->getName())->toBe('simple');
    });

    it('uses default parameter values', function () {
        // ARRANGE & ACT
        $service = $this->container->build(ServiceWithDefaults::class);

        // ASSERT
        expect($service->getName())->toBe('default');
    });

    it('uses default values when class dependencies fail to resolve', function () {
        // ARRANGE & ACT
        $service = $this->container->build(ServiceWithOptionalClassDep::class);

        // ASSERT
        expect($service->hasDep())->toBeFalse();
    });

    it('detects circular dependencies', function () {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->container->build(CircularA::class))
            ->toThrow(\RuntimeException::class, 'Circular dependency detected');
    });

    it('throws exceptions for invalid classes', function (string $className, string $errorPattern) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->container->build($className))
            ->toThrow(\RuntimeException::class, $errorPattern);
    })->with([
        'non-existent class' => ['NonExistentClass', 'does not exist'],
        'interface' => [TestInterface::class, 'does not exist'], // Interfaces don't pass class_exists()
        'abstract class' => [AbstractClass::class, 'not instantiable'],
        'private constructor' => [PrivateConstructor::class, 'not instantiable'],
        'unresolvable scalar parameter' => [ServiceWithScalarParam::class, 'Cannot resolve parameter [required] in [Bigpixelrocket\\DeployerPHP\\Tests\\Fixtures\\ServiceWithScalarParam]'],
    ]);

    it('cleans up state after errors', function () {
        // ARRANGE
        try {
            $this->container->build(CircularA::class);
        } catch (\RuntimeException) {
            // Expected
        }

        // ACT - Should work fine after error
        $result = $this->container->build(SimpleService::class);

        // ASSERT
        expect($result->getName())->toBe('simple');
    });

    it('resolves union types by trying each class arm', function () {
        // ARRANGE & ACT
        $service = $this->container->build(ServiceWithUnionType::class);

        // ASSERT
        expect($service->getServiceType())->toBe('simple'); // First resolvable arm (SimpleService)
    });

    it('falls back to defaults for intersection types', function () {
        // ARRANGE & ACT
        $service = $this->container->build(ServiceWithIntersectionType::class);

        // ASSERT
        expect($service->hasData())->toBeFalse(); // Uses default null value
    });

    it('includes declaring class in dependency resolution errors', function () {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->container->build(ServiceWithUnresolvableDependency::class))
            ->toThrow(\RuntimeException::class, 'Cannot resolve dependency [Bigpixelrocket\\DeployerPHP\\Tests\\Fixtures\\AbstractClass] for parameter [dependency] in [Bigpixelrocket\\DeployerPHP\\Tests\\Fixtures\\ServiceWithUnresolvableDependency]');
    });

    it('detects circular dependencies in union types', function () {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->container->build(ServiceWithUnionAndCircular::class))
            ->toThrow(\RuntimeException::class, 'Circular dependency detected');
    });
});
