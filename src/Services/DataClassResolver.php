<?php

namespace OiLab\OiLaravelTs\Services;

use OiLab\OiLaravelTs\Exceptions\DataObjectNameCollisionException;
use OiLab\OiLaravelTs\Services\Concerns\ScansPsr4Namespaces;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Data Class Resolver
 *
 * Locates spatie/laravel-data style Data Transfer Objects (DTOs) declared under
 * the namespaces configured in `config('oi-laravel-ts.data_namespaces')`.
 *
 * Detection is purely structural — a class is treated as a DTO when it lives
 * under a configured namespace and exposes a constructor with at least one
 * (promoted) parameter. No dependency on spatie/laravel-data is required.
 *
 * The resolver also associates a DTO with the Eloquent model it represents,
 * either through an explicit `data_for_model` map or by introspecting the first
 * parameter of the DTO's `fromModel()` factory.
 */
class DataClassResolver
{
    use ScansPsr4Namespaces;

    /**
     * @var array<int, string>
     */
    private array $namespaces;

    /**
     * Explicit model => DTO map, normalized without leading backslashes.
     *
     * @var array<string, string>
     */
    private array $modelToData;

    /**
     * Lazily-built short-name => FQCN index of every discovered DTO.
     *
     * @var array<string, string>|null
     */
    private ?array $shortNameIndex = null;

    /**
     * @param  array<int, string>|null  $namespaces  Override the configured namespaces. Pass null to read from config.
     * @param  array<string, string>|null  $dataForModel  Explicit model => DTO map. Pass null to read from config.
     */
    public function __construct(?array $namespaces = null, ?array $dataForModel = null)
    {
        if ($namespaces === null) {
            $namespaces = function_exists('config')
                ? (array) config('oi-laravel-ts.data_namespaces', [])
                : [];
        }

        $this->namespaces = array_values(array_unique(array_map(
            static fn (string $namespace): string => trim($namespace, '\\'),
            $namespaces
        )));

        if ($dataForModel === null) {
            $dataForModel = function_exists('config')
                ? (array) config('oi-laravel-ts.data_for_model', [])
                : [];
        }

        $this->modelToData = [];

        foreach ($dataForModel as $model => $data) {
            $this->modelToData[ltrim((string) $model, '\\')] = ltrim((string) $data, '\\');
        }
    }

    /**
     * List every DTO class living under the configured namespaces.
     *
     * @return array<int, string> Fully qualified DTO class names.
     *
     * @throws DataObjectNameCollisionException When two distinct FQCNs share a short name.
     */
    public function listDataClassesInNamespaces(): array
    {
        $psr4 = $this->getPsr4Prefixes();
        $found = [];

        foreach ($this->namespaces as $namespace) {
            foreach ($this->classesInNamespace($namespace, $psr4) as $fqcn) {
                if ($this->isDataClass($fqcn)) {
                    $found[] = $fqcn;
                }
            }
        }

        $found = array_values(array_unique($found));

        $this->guardAgainstNameCollisions($found);

        return $found;
    }

    /**
     * Resolve a short DTO name (or FQCN) to a fully qualified DTO class name.
     *
     * Short names are resolved through an index of every DTO discovered under
     * the configured namespaces, so DTOs living in sub-namespaces resolve too.
     * Returns null when the reference is not a known DTO.
     */
    public function resolveDataClass(string $className): ?string
    {
        $className = ltrim($className, '\\');

        if ($className === '') {
            return null;
        }

        if (str_contains($className, '\\')) {
            return $this->isDataClass($className) ? $className : null;
        }

        return $this->shortNameIndex()[$className] ?? null;
    }

    /**
     * Resolve the Eloquent model a DTO represents.
     *
     * Resolution order:
     *   1. Explicit `data_for_model` mapping (reverse lookup).
     *   2. The first parameter type of the DTO's `fromModel()` factory.
     *
     * Returns null when no model can be associated.
     */
    public function resolveModelForDataClass(string $dataClass): ?string
    {
        $dataClass = ltrim($dataClass, '\\');

        $explicit = array_search($dataClass, $this->modelToData, true);
        if ($explicit !== false) {
            return $explicit;
        }

        return $this->modelFromFactory($dataClass);
    }

    /**
     * Short class names of every model that is replaced by a DTO.
     *
     * Used to suppress Eloquent interfaces when `data_replaces_model` is on.
     *
     * @return array<int, string>
     */
    public function replacedModelShortNames(): array
    {
        $names = [];

        foreach ($this->modelToData as $model => $data) {
            $names[] = class_basename($model);
        }

        foreach ($this->listDataClassesInNamespaces() as $dataClass) {
            $model = $this->resolveModelForDataClass($dataClass);
            if ($model !== null) {
                $names[] = class_basename($model);
            }
        }

        return array_values(array_unique($names));
    }

    /**
     * Resolve the configured namespaces to their backing directories.
     *
     * Used by the watch command to monitor DTO directories for changes.
     *
     * @return array<int, string> Existing directories, de-duplicated.
     */
    public function resolveNamespaceDirectories(): array
    {
        $psr4 = $this->getPsr4Prefixes();
        $directories = [];

        foreach ($this->namespaces as $namespace) {
            foreach ($this->directoriesForNamespace($namespace, $psr4) as $directory) {
                if (is_dir($directory)) {
                    $directories[] = $directory;
                }
            }
        }

        return array_values(array_unique($directories));
    }

    /**
     * Check whether the given class qualifies as a DTO.
     */
    public function isDataClass(string $fqcn): bool
    {
        if (! class_exists($fqcn)) {
            return false;
        }

        try {
            $reflection = new ReflectionClass($fqcn);
        } catch (ReflectionException) {
            return false;
        }

        if ($reflection->isAbstract() || $reflection->isInterface()) {
            return false;
        }

        $constructor = $reflection->getConstructor();

        return $constructor !== null && $constructor->getNumberOfParameters() > 0;
    }

    /**
     * @return array<int, string>
     */
    public function getNamespaces(): array
    {
        return $this->namespaces;
    }

    /**
     * Introspect a DTO's `fromModel()` factory to find the model it maps to.
     */
    private function modelFromFactory(string $dataClass): ?string
    {
        if (! class_exists($dataClass)) {
            return null;
        }

        try {
            $reflection = new ReflectionClass($dataClass);
        } catch (ReflectionException) {
            return null;
        }

        if (! $reflection->hasMethod('fromModel')) {
            return null;
        }

        $parameters = $reflection->getMethod('fromModel')->getParameters();

        if ($parameters === []) {
            return null;
        }

        $type = $parameters[0]->getType();

        if (! $type instanceof ReflectionNamedType || $type->isBuiltin()) {
            return null;
        }

        return ltrim($type->getName(), '\\');
    }

    /**
     * Build (and cache) the short-name => FQCN index for discovered DTOs.
     *
     * @return array<string, string>
     */
    private function shortNameIndex(): array
    {
        if ($this->shortNameIndex !== null) {
            return $this->shortNameIndex;
        }

        $index = [];

        foreach ($this->listDataClassesInNamespaces() as $fqcn) {
            $index[class_basename($fqcn)] = $fqcn;
        }

        return $this->shortNameIndex = $index;
    }

    /**
     * @param  array<int, string>  $fqcns
     *
     * @throws DataObjectNameCollisionException
     */
    private function guardAgainstNameCollisions(array $fqcns): void
    {
        $byShortName = [];

        foreach ($fqcns as $fqcn) {
            $byShortName[class_basename($fqcn)][] = $fqcn;
        }

        foreach ($byShortName as $shortName => $classes) {
            if (count($classes) > 1) {
                throw new DataObjectNameCollisionException($shortName, $classes);
            }
        }
    }
}
