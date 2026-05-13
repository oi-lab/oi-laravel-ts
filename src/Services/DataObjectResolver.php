<?php

namespace OiLab\OiLaravelTs\Services;

use ReflectionClass;
use ReflectionException;

/**
 * DataObject Resolver
 *
 * Resolves short DataObject class names against the namespaces configured in
 * `config('oi-laravel-ts.dataobject_namespaces')`. A class is considered a
 * DataObject when it exposes both `fromArray()` and `toArray()` methods, so
 * any value object following that contract works regardless of where it lives.
 */
class DataObjectResolver
{
    /**
     * @var array<int, string>
     */
    private array $namespaces;

    /**
     * @param  array<int, string>|null  $namespaces  Override the configured namespaces. Pass null to read from config.
     */
    public function __construct(?array $namespaces = null)
    {
        if ($namespaces === null) {
            $namespaces = function_exists('config')
                ? (array) config('oi-laravel-ts.dataobject_namespaces', ['App\\DataObjects'])
                : ['App\\DataObjects'];
        }

        $this->namespaces = array_values(array_unique(array_map(
            static fn (string $namespace): string => trim($namespace, '\\'),
            $namespaces
        )));
    }

    /**
     * Resolve a class reference to a fully qualified DataObject class name.
     *
     * Accepts either a short name (e.g. `Address`) or an FQCN. Returns null
     * when the class cannot be resolved or is not a DataObject.
     */
    public function resolveDataObjectClass(string $className): ?string
    {
        $className = ltrim($className, '\\');

        if ($className === '') {
            return null;
        }

        if (str_contains($className, '\\')) {
            return $this->isDataObject($className) ? $className : null;
        }

        foreach ($this->namespaces as $namespace) {
            $candidate = $namespace.'\\'.$className;

            if ($this->isDataObject($candidate)) {
                return $candidate;
            }
        }

        return null;
    }

    /**
     * Check whether the given class is a DataObject.
     */
    public function isDataObject(string $fqcn): bool
    {
        if (! class_exists($fqcn)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($fqcn);
        } catch (ReflectionException) {
            return false;
        }

        return $reflection->hasMethod('fromArray') && $reflection->hasMethod('toArray');
    }

    /**
     * @return array<int, string>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }
}
