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
    /** @var array<string, bool> Currently resolving classes (circular dependency detection) */
    private array $resolving = [];

    /** @var array<string, array{reflector: ReflectionClass<object>, constructor: ?\ReflectionMethod, parameters: ReflectionParameter[]}> Reflection data cache */
    private array $reflectionCache = [];

    /**
     * Build a class instance using reflection and auto-wire dependencies.
     *
     * @template T of object
     * @param class-string<T> $className
     * @return T
     */
    public function build(string $className): object
    {
        // Circular dependency detection
        if (isset($this->resolving[$className])) {
            $chain = implode(' -> ', array_keys($this->resolving)) . " -> {$className}";
            throw new \RuntimeException("Circular dependency detected: {$chain}");
        }

        if (!class_exists($className)) {
            throw new \RuntimeException("Class [{$className}] does not exist");
        }

        // Get cached reflection data
        $reflectionData = $this->getReflectionData($className);
        $reflector = $reflectionData['reflector'];
        $constructor = $reflectionData['constructor'];
        $parameters = $reflectionData['parameters'];

        if (!$reflector->isInstantiable()) {
            throw new \RuntimeException("Class [{$className}] is not instantiable");
        }

        // Mark as currently resolving
        $this->resolving[$className] = true;

        try {
            // If no constructor, return new instance
            if ($constructor === null) {
                /** @var T */
                return $reflector->newInstance();
            }

            // Resolve constructor dependencies
            $dependencies = $this->resolveDependencies($parameters);

            /** @var T */
            return $reflector->newInstanceArgs($dependencies);
        } finally {
            // Always clean up resolving state
            unset($this->resolving[$className]);
        }
    }

    /**
     * Get cached reflection data for a class.
     *
     * @param class-string $className
     * @return array{reflector: ReflectionClass<object>, constructor: ?\ReflectionMethod, parameters: ReflectionParameter[]}
     */
    private function getReflectionData(string $className): array
    {
        if (!isset($this->reflectionCache[$className])) {
            $reflector = new ReflectionClass($className);
            $constructor = $reflector->getConstructor();
            $parameters = $constructor?->getParameters() ?? [];

            $this->reflectionCache[$className] = [
                'reflector' => $reflector,
                'constructor' => $constructor,
                'parameters' => $parameters,
            ];
        }

        return $this->reflectionCache[$className];
    }

    /**
     * Resolve all dependencies for constructor parameters.
     *
     * @param ReflectionParameter[] $parameters
     * @return array<int, mixed>
     */
    private function resolveDependencies(array $parameters): array
    {
        $dependencies = [];

        foreach ($parameters as $parameter) {
            $dependencies[] = $this->resolveParameter($parameter);
        }

        return $dependencies;
    }

    /**
     * Resolve a single constructor parameter.
     */
    private function resolveParameter(ReflectionParameter $parameter): mixed
    {
        $type = $parameter->getType();

        // Handle union types and built-in types
        if (!$type instanceof ReflectionNamedType || $type->isBuiltin()) {
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new \RuntimeException(
                "Cannot resolve parameter [{$parameter->getName()}] in class [{$parameter->getDeclaringClass()?->getName()}]"
            );
        }

        $className = $type->getName();

        try {
            /** @var class-string $className */
            return $this->build($className);
        } catch (\RuntimeException $e) {
            // If dependency resolution fails and parameter has default, use it
            if ($parameter->isDefaultValueAvailable()) {
                return $parameter->getDefaultValue();
            }

            throw new \RuntimeException(
                "Cannot resolve dependency [{$className}] for parameter [{$parameter->getName()}]",
                previous: $e
            );
        }
    }
}
