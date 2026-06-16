<?php

namespace OiLab\OiLaravelTs\Services;

use OiLab\OiLaravelTs\Exceptions\DataObjectNameCollisionException;
use OiLab\OiLaravelTs\Services\Concerns\ScansPsr4Namespaces;
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
    use ScansPsr4Namespaces;

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
     * List every DataObject class living under the configured namespaces.
     *
     * Each configured namespace is mapped to a directory through Composer's
     * registered PSR-4 prefixes, then scanned recursively for `*.php` files.
     * Files whose class follows the DataObject contract (`fromArray()` +
     * `toArray()`) are returned as fully qualified class names.
     *
     * @return array<int, string> Fully qualified DataObject class names.
     *
     * @throws DataObjectNameCollisionException When two distinct FQCNs share a short name.
     */
    public function listDataObjectsInNamespaces(): array
    {
        $psr4 = $this->getPsr4Prefixes();
        $found = [];

        foreach ($this->namespaces as $namespace) {
            foreach ($this->classesInNamespace($namespace, $psr4) as $fqcn) {
                if ($this->isDataObject($fqcn)) {
                    $found[] = $fqcn;
                }
            }
        }

        $found = array_values(array_unique($found));

        $this->guardAgainstNameCollisions($found);

        return $found;
    }

    /**
     * Resolve the configured namespaces to their backing directories.
     *
     * Used by the watch command to know which directories to monitor when
     * DataObject discovery is enabled.
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
