<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP\Tests\Fixtures;

//
// Simple services
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

//
// Services with dependencies
// -------------------------------------------------------------------------------

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

//
// Services with defaults and optional dependencies
// -------------------------------------------------------------------------------

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

//
// Circular dependency fixtures
// -------------------------------------------------------------------------------

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

//
// Error condition fixtures
// -------------------------------------------------------------------------------

class ServiceWithScalarParam
{
    public function __construct(private readonly string $required)
    {
    }
}

class ServiceWithUnresolvableDependency
{
    public function __construct(private readonly AbstractClass $dependency)
    {
    }
}

class PrivateConstructor
{
    private function __construct()
    {
    }
}

//
// Union and intersection type fixtures
// -------------------------------------------------------------------------------

class ServiceWithUnionType
{
    public function __construct(private readonly SimpleService|ServiceWithDependency $service)
    {
    }

    public function getServiceType(): string
    {
        return $this->service instanceof SimpleService ? 'simple' : 'complex';
    }
}

class ServiceWithIntersectionType
{
    public function __construct(private readonly (\Countable&\ArrayAccess)|null $data = null)
    {
    }

    public function hasData(): bool
    {
        return $this->data !== null;
    }
}

class ServiceWithUnionAndCircular
{
    public function __construct(private readonly CircularA|SimpleService $dependency)
    {
    }

    public function getDependency(): CircularA|SimpleService
    {
        return $this->dependency;
    }
}

//
// Interfaces and abstract classes
// -------------------------------------------------------------------------------

interface TestInterface
{
}

abstract class AbstractClass
{
}
