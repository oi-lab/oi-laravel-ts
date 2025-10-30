<?php

namespace OiLab\OiLaravelTs\Services\Generators;

/**
 * Import Manager
 *
 * Manages TypeScript import statements for external types.
 * Collects imports from schema and generates properly formatted import statements.
 */
class ImportManager
{
    /**
     * Map of import paths to their type names.
     *
     * Structure: ['@/path/to/file' => ['Type1', 'Type2']]
     *
     * @var array<string, array<int, string>>
     */
    private array $imports = [];

    /**
     * Collect imports from the schema.
     *
     * Scans all models and their fields to find types marked as imports,
     * then organizes them by their source path.
     *
     * @param array<string, array{
     *     model: string,
     *     namespace: string,
     *     types: array<int, array{
     *         field: string,
     *         type: string,
     *         isImport?: bool
     *     }>
     * }> $schema The complete schema definition
     */
    public function collectImports(array $schema): void
    {
        foreach ($schema as $model) {
            foreach ($model['types'] as $field) {
                if (isset($field['isImport']) && $field['isImport']) {
                    $this->addImport($field['type']);
                }
            }
        }
    }

    /**
     * Add a single import type to the collection.
     *
     * Handles two import formats:
     * - "@/path/to/file|InterfaceName" - Explicit interface name
     * - "@/path/to/file" - Uses basename as interface name
     *
     * Arrays are handled by removing the [] suffix from the interface name.
     *
     * @param  string  $type  The import type definition
     */
    private function addImport(string $type): void
    {
        if (str_contains($type, '|')) {
            // Format: "@/path/to/file|InterfaceName"
            [$path, $interface] = explode('|', $type);
            $this->registerImport($path, $interface);
        } else {
            // Format: "@/path/to/file"
            $basename = basename($type);
            $this->registerImport($type, $basename);
        }
    }

    /**
     * Register an import in the collection.
     *
     * Removes array brackets from interface names and ensures no duplicates.
     *
     * @param  string  $path  The import path
     * @param  string  $interface  The interface name
     */
    private function registerImport(string $path, string $interface): void
    {
        // Remove array brackets from interface name for import
        $cleanInterface = str_replace('[]', '', $interface);

        if (! isset($this->imports[$path])) {
            $this->imports[$path] = [];
        }

        if (! in_array($cleanInterface, $this->imports[$path])) {
            $this->imports[$path][] = $cleanInterface;
        }
    }

    /**
     * Generate TypeScript import statements.
     *
     * Creates properly formatted import statements from collected imports.
     *
     * Example output:
     * ```typescript
     * import { Type1, Type2 } from '@/path/to/file';
     * import { Type3 } from '@/another/path';
     * ```
     *
     * @return string The formatted import statements
     */
    public function generateImports(): string
    {
        if (empty($this->imports)) {
            return '';
        }

        $output = '';

        foreach ($this->imports as $path => $types) {
            $typesList = implode(', ', $types);
            $output .= "import { $typesList } from '$path';\n";
        }

        return $output."\n";
    }

    /**
     * Check if there are any imports to generate.
     *
     * @return bool True if there are imports
     */
    public function hasImports(): bool
    {
        return ! empty($this->imports);
    }

    /**
     * Get the number of import statements.
     *
     * @return int The number of unique import paths
     */
    public function getImportCount(): int
    {
        return count($this->imports);
    }

    /**
     * Get all collected imports.
     *
     * @return array<string, array<int, string>> The imports map
     */
    public function getImports(): array
    {
        return $this->imports;
    }

    /**
     * Clear all collected imports.
     */
    public function reset(): void
    {
        $this->imports = [];
    }
}
