<?php

declare(strict_types=1);

use Bigpixelrocket\DeployerPHP\Container;

//
// Inline test fixtures
// -------------------------------------------------------------------------------

class SimpleService
{
    public function getName(): string
    {
        return 'simple';
    }
}

class NoConstructorService
{
    public function getType(): string
    {
        return 'no-constructor';
    }
}

class ServiceWithDependency
{
    public function __construct(private readonly SimpleService $service)
    {
    }
    public function getDependency(): SimpleService
    {
        return $this->service;
    }
}

class ServiceWithMultipleDeps
{
    public function __construct(private readonly SimpleService $s1, private readonly ServiceWithDependency $s2)
    {
    }
    public function getSimple(): SimpleService
    {
        return $this->s1;
    }
    public function getComplex(): ServiceWithDependency
    {
        return $this->s2;
    }
}

class ServiceWithDefaults
{
    public function __construct(private readonly SimpleService $service, private readonly string $name = 'default')
    {
    }
    public function getName(): string
    {
        return $this->name;
    }
}

class CircularA
{
    public function __construct(private readonly CircularB $b)
    {
    }
}

class CircularB
{
    public function __construct(private readonly CircularA $a)
    {
    }
}

class ServiceWithScalarParam
{
    public function __construct(private readonly string $required)
    {
    }
}

class ServiceWithOptionalClassDep
{
    public function __construct(private readonly ?AbstractClass $dep = null)
    {
    }

    public function hasDep(): bool
    {
        return $this->dep !== null;
    }
}

interface TestInterface
{
}

abstract class AbstractClass
{
}

class PrivateConstructor
{
    private function __construct()
    {
    }
}

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
            ->toThrow(RuntimeException::class, 'Cannot resolve dependency');
    });

    it('throws exceptions for invalid classes', function (string $className, string $errorPattern) {
        // ARRANGE & ACT & ASSERT
        expect(fn () => $this->container->build($className))
            ->toThrow(RuntimeException::class, $errorPattern);
    })->with([
        ['NonExistentClass', 'does not exist'],
        [TestInterface::class, 'does not exist'], // Interfaces don't pass class_exists()
        [AbstractClass::class, 'not instantiable'],
        [PrivateConstructor::class, 'not instantiable'],
        [ServiceWithScalarParam::class, 'Cannot resolve parameter'],
    ]);

    it('cleans up state after errors', function () {
        // ARRANGE
        try {
            $this->container->build(CircularA::class);
        } catch (RuntimeException) {
            // Expected
        }

        // ACT - Should work fine after error
        $result = $this->container->build(SimpleService::class);

        // ASSERT
        expect($result->getName())->toBe('simple');
    });
});
