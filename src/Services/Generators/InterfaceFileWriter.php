<?php

namespace OiLab\OiLaravelTs\Services\Generators;

use Illuminate\Support\Facades\File;
use OiLab\OiLaravelTs\Services\Support\InterfaceNaming;

/**
 * Writes generated interface units to disk.
 *
 * Two strategies are supported:
 * - `single`   → the caller already holds the concatenated output; this writer
 *                only persists it (byte-identical to the legacy behavior).
 * - `multiple` → one kebab-cased `.ts` file per interface plus an `index.ts`
 *                barrel. Each file imports exactly the interfaces it references,
 *                resolving relative imports for local interfaces and re-emitting
 *                external imports for `@/…` aliased types.
 */
class InterfaceFileWriter
{
    /**
     * Persist the single-file output verbatim.
     */
    public function writeSingle(string $content, string $path): void
    {
        File::ensureDirectoryExists(dirname($path));
        File::put($path, $content);
    }

    /**
     * Write one file per interface plus an index barrel.
     *
     * @param  array<int, InterfaceUnit>  $units  Interface units in emission order.
     * @param  array<string, array<int, string>>  $externalImports  Path => external type names (from ImportManager).
     * @param  string  $directory  Target directory for the generated files.
     */
    public function writeMultiple(array $units, array $externalImports, string $directory): void
    {
        File::ensureDirectoryExists($directory);

        $localNames = array_map(static fn (InterfaceUnit $unit): string => $unit->name, $units);

        foreach ($units as $unit) {
            $content = $this->buildFileContent($unit, $localNames, $externalImports);
            $fileName = InterfaceNaming::toFileName($unit->name).'.ts';

            File::put($directory.DIRECTORY_SEPARATOR.$fileName, $content);
        }

        File::put($directory.DIRECTORY_SEPARATOR.'index.ts', $this->buildBarrel($units));
    }

    /**
     * Build the content of a single interface file: its imports followed by the
     * interface body.
     *
     * @param  array<int, string>  $localNames
     * @param  array<string, array<int, string>>  $externalImports
     */
    private function buildFileContent(InterfaceUnit $unit, array $localNames, array $externalImports): string
    {
        $imports = array_merge(
            $this->externalImportLines($unit, $externalImports),
            $this->relativeImportLines($unit, $localNames),
        );

        $header = $imports === [] ? '' : implode("\n", $imports)."\n\n";

        return $header.$unit->body."\n";
    }

    /**
     * Resolve relative imports for references that point to other local interfaces.
     *
     * @param  array<int, string>  $localNames
     * @return array<int, string>
     */
    private function relativeImportLines(InterfaceUnit $unit, array $localNames): array
    {
        $lines = [];

        foreach ($unit->references as $reference) {
            if ($reference === $unit->name || ! in_array($reference, $localNames, true)) {
                continue;
            }

            $fileName = InterfaceNaming::toFileName($reference);
            $lines[$fileName] = "import type { {$reference} } from './{$fileName}';";
        }

        ksort($lines);

        return array_values($lines);
    }

    /**
     * Re-emit external (`@/…`) imports for the aliased types this interface uses.
     *
     * @param  array<string, array<int, string>>  $externalImports
     * @return array<int, string>
     */
    private function externalImportLines(InterfaceUnit $unit, array $externalImports): array
    {
        $lines = [];

        foreach ($externalImports as $path => $types) {
            $used = array_filter(
                $types,
                fn (string $type): bool => $this->bodyReferencesType($unit->body, $type)
            );

            if ($used !== []) {
                $list = implode(', ', array_values(array_unique($used)));
                $lines[] = "import type { {$list} } from '{$path}';";
            }
        }

        return $lines;
    }

    private function bodyReferencesType(string $body, string $type): bool
    {
        return (bool) preg_match('/\b'.preg_quote($type, '/').'\b/', $body);
    }

    /**
     * Build the `index.ts` barrel re-exporting every generated file.
     *
     * @param  array<int, InterfaceUnit>  $units
     */
    private function buildBarrel(array $units): string
    {
        $lines = [];

        foreach ($units as $unit) {
            $fileName = InterfaceNaming::toFileName($unit->name);
            $lines[$fileName] = "export * from './{$fileName}';";
        }

        ksort($lines);

        return implode("\n", array_values($lines))."\n";
    }
}
