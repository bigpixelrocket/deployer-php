<?php

declare(strict_types=1);

namespace Bigpixelrocket\DeployerPHP;

use ReflectionClass;
use ReflectionNamedType;
use ReflectionParameter;

/**
 * Automatically resolves constructor dependencies using reflection.
 *
 * Usage:
 *
 * ```php
 * $container = new Container();
 * $service = $container->build(MyService::class);
 * ```
 */
class Container
{
    /** @var array<string, array{reflector: ReflectionClass<object>, constructor: ?\ReflectionMethod, parameters: ReflectionParameter[]}> */
    private array $reflectionCache = [];

    /** @var array<string, bool> */
    private array $resolving = [];

    //
    // Public
    // -------------------------------------------------------------------------------

    /**
     * Build a class instance with auto-wired dependencies.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function build(string $className): object
    {
        $this->guardAgainstCircularDependency($className);
        $this->guardAgainstInvalidClass($className);

        $reflectionData = $this->getCachedReflectionData($className);
        $this->guardAgainstNonInstantiableClass($className, $reflectionData['reflector']);

        $this->resolving[$className] = true;

        try {
            /** @var T */
            return $reflectionData['constructor'] === null
                ? $reflectionData['reflector']->newInstance()
                : $reflectionData['reflector']->newInstanceArgs(
                    $this->buildDependencies($reflectionData['parameters'])
                );
        } finally {
            unset($this->resolving[$className]);
        }
    }

    //
    // Private
    // -------------------------------------------------------------------------------

    //
    // Dependency Resolution

    /**
     * Build dependencies for constructor parameters.
     *
     * @param ReflectionParameter[] $parameters
     * @return array<int, mixed>
     */
    private function buildDependencies(array $parameters): array
    {
        return array_values(array_map([$this, 'buildParameter'], $parameters));
    }

    /**
     * Build a single constructor parameter dependency.
     */
    private function buildParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return $this->resolveNonClassParameter($parameter);
        }

        return $this->resolveClassParameter($parameter, $type->getName());
    }

    //
    // Parameter Resolution

    /**
     * Resolve a class-type parameter by building its dependency.
     */
    private function resolveClassParameter(ReflectionParameter $parameter, string $className): mixed
    {
        try {
            /** @var class-string $className */
            return $this->build($className);
        } catch (\RuntimeException $e) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new \RuntimeException(
                "Cannot resolve dependency [{$className}] for parameter [{$parameter->getName()}]",
                previous: $e
            );
        }
    }

    /**
     * Resolve a non-class parameter by using its default value.
     */
    private function resolveNonClassParameter(ReflectionParameter $parameter): mixed
    {
        if ($parameter->isDefaultValueAvailable()) {
            return $parameter->getDefaultValue();
        }

        throw new \RuntimeException(
            "Cannot resolve parameter [{$parameter->getName()}] in class [{$parameter->getDeclaringClass()?->getName()}]"
        );
    }

    //
    // Reflection Caching

    /**
     * Get cached reflection data for a class.
     *
     * @param class-string $className
     * @return array{reflector: ReflectionClass<object>, constructor: ?\ReflectionMethod, parameters: ReflectionParameter[]}
     */
    private function getCachedReflectionData(string $className): array
    {
        return $this->reflectionCache[$className] ??= $this->buildReflectionData($className);
    }

    /**
     * Build reflection data for a class.
     *
     * @param class-string $className
     * @return array{reflector: ReflectionClass<object>, constructor: ?\ReflectionMethod, parameters: ReflectionParameter[]}
     */
    private function buildReflectionData(string $className): array
    {
        $reflector = new ReflectionClass($className);
        $constructor = $reflector->getConstructor();

        return [
            'reflector' => $reflector,
            'constructor' => $constructor,
            'parameters' => $constructor?->getParameters() ?? [],
        ];
    }

    //
    // Guard Methods

    /**
     * Guard against circular dependencies.
     */
    private function guardAgainstCircularDependency(string $className): void
    {
        if (isset($this->resolving[$className])) {
            $chain = implode(' -> ', array_keys($this->resolving)) . " -> {$className}";
            throw new \RuntimeException("Circular dependency detected: {$chain}");
        }
    }

    /**
     * Guard against invalid class names.
     */
    private function guardAgainstInvalidClass(string $className): void
    {
        if (!class_exists($className)) {
            throw new \RuntimeException("Class [{$className}] does not exist");
        }
    }

    /**
     * Guard against non-instantiable classes.
     *
     * @param ReflectionClass<object> $reflector
     */
    private function guardAgainstNonInstantiableClass(string $className, ReflectionClass $reflector): void
    {
        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Class [{$className}] is not instantiable");
        }
    }
}
