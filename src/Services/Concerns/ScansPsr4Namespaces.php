<?php

namespace OiLab\OiLaravelTs\Services\Concerns;

use Composer\Autoload\ClassLoader;

/**
 * Scans PSR-4 Namespaces
 *
 * Shared helpers to resolve configured namespaces to their backing directories
 * (through Composer's registered PSR-4 prefixes) and walk them for declared
 * classes. Used by both DataObjectResolver and DataClassResolver so the
 * filesystem-scanning logic lives in a single place.
 */
trait ScansPsr4Namespaces
{
    /**
     * Resolve a namespace to its classes by walking the PSR-4 directory tree.
     *
     * @param  array<string, array<int, string>>  $psr4  Prefix => directories map.
     * @return array<int, string> Fully qualified class names declared under the namespace.
     */
    private function classesInNamespace(string $namespace, array $psr4): array
    {
        $namespace = trim($namespace, '\\');
        $directories = $this->directoriesForNamespace($namespace, $psr4);
        $classes = [];

        foreach ($directories as $directory) {
            if (! is_dir($directory)) {
                continue;
            }

            $iterator = new \RecursiveIteratorIterator(
                new \RecursiveDirectoryIterator($directory, \FilesystemIterator::SKIP_DOTS)
            );

            foreach ($iterator as $file) {
                if (! $file->isFile() || $file->getExtension() !== 'php') {
                    continue;
                }

                $relative = ltrim(str_replace($directory, '', $file->getPathname()), DIRECTORY_SEPARATOR);
                $relativeClass = str_replace([DIRECTORY_SEPARATOR, '.php'], ['\\', ''], $relative);

                $classes[] = $namespace.'\\'.$relativeClass;
            }
        }

        return $classes;
    }

    /**
     * Find the directories backing a namespace using the longest matching PSR-4 prefix.
     *
     * @param  array<string, array<int, string>>  $psr4  Prefix => directories map.
     * @return array<int, string>
     */
    private function directoriesForNamespace(string $namespace, array $psr4): array
    {
        $namespace = trim($namespace, '\\').'\\';
        $bestPrefix = null;

        foreach ($psr4 as $prefix => $directories) {
            if (str_starts_with($namespace, $prefix) && (
                $bestPrefix === null || strlen($prefix) > strlen($bestPrefix)
            )) {
                $bestPrefix = $prefix;
            }
        }

        if ($bestPrefix === null) {
            return [];
        }

        $suffix = substr($namespace, strlen($bestPrefix));
        $subPath = str_replace('\\', DIRECTORY_SEPARATOR, $suffix);

        return array_map(
            static fn (string $base): string => rtrim($base, DIRECTORY_SEPARATOR).
                ($subPath !== '' ? DIRECTORY_SEPARATOR.rtrim($subPath, DIRECTORY_SEPARATOR) : ''),
            $psr4[$bestPrefix]
        );
    }

    /**
     * Collect the PSR-4 prefix map from every registered Composer ClassLoader.
     *
     * @return array<string, array<int, string>>
     */
    private function getPsr4Prefixes(): array
    {
        $prefixes = [];

        foreach (ClassLoader::getRegisteredLoaders() as $loader) {
            foreach ($loader->getPrefixesPsr4() as $prefix => $directories) {
                $prefixes[$prefix] = array_merge($prefixes[$prefix] ?? [], $directories);
            }
        }

        return $prefixes;
    }
}
