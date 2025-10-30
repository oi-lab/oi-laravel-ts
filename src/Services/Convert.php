<?php

namespace OiLab\OiLaravelTs\Services;

use OiLab\OiLaravelTs\Services\Converters\TypeScriptTypeConverter;
use OiLab\OiLaravelTs\Services\Generators\ImportManager;
use OiLab\OiLaravelTs\Services\Generators\JsonLdGenerator;
use OiLab\OiLaravelTs\Services\Generators\ModelInterfaceGenerator;
use OiLab\OiLaravelTs\Services\Processors\DataObjectProcessor;
use Illuminate\Support\Facades\File;

/**
 * TypeScript Converter
 *
 * Main orchestrator for converting Laravel models schema to TypeScript interfaces.
 * Coordinates the conversion process using specialized components:
 * - ImportManager: Handles TypeScript imports
 * - DataObjectProcessor: Processes PHP DataObjects
 * - ModelInterfaceGenerator: Generates model interfaces
 * - JsonLdGenerator: Generates JSON-LD support interfaces
 * - TypeScriptTypeConverter: Converts PHP types to TypeScript
 *
 *
 * @example
 * ```php
 * $schema = Eloquent::getSchema();
 * $converter = new Convert($schema, true);
 * $converter->generateFile(resource_path('js/types/interfaces.ts'));
 * ```
 */
class Convert
{
    /**
     * The model schema to convert.
     *
     * @var array<string, array{
     *     model: string,
     *     namespace: string,
     *     types: array<int, array{
     *         field: string,
     *         type: string,
     *         relation: bool,
     *         model?: string,
     *         nullable?: bool,
     *         isDataObject?: bool,
     *         isArray?: bool,
     *         isImport?: bool
     *     }>
     * }>
     */
    private array $schema;

    /**
     * Whether to include JSON-LD support.
     */
    private bool $withJsonLd;

    /**
     * Import manager instance.
     */
    private ImportManager $importManager;

    /**
     * DataObject processor instance.
     */
    private DataObjectProcessor $dataObjectProcessor;

    /**
     * Model interface generator instance.
     */
    private ModelInterfaceGenerator $modelGenerator;

    /**
     * JSON-LD generator instance.
     */
    private JsonLdGenerator $jsonLdGenerator;

    /**
     * Type converter instance.
     */
    private TypeScriptTypeConverter $typeConverter;

    /**
     * Create a new Convert instance.
     *
     * @param array<string, array{
     *     model: string,
     *     namespace: string,
     *     types: array
     * }> $schema The model schema from Eloquent service
     * @param  bool  $withJsonLd  Whether to include JSON-LD support
     */
    public function __construct(array $schema, bool $withJsonLd = false)
    {
        $this->schema = $schema;
        $this->withJsonLd = $withJsonLd;

        // Initialize all components
        $this->typeConverter = new TypeScriptTypeConverter;
        $this->importManager = new ImportManager;
        $this->dataObjectProcessor = new DataObjectProcessor($this->typeConverter);
        $this->modelGenerator = new ModelInterfaceGenerator($this->typeConverter);
        $this->jsonLdGenerator = new JsonLdGenerator;
    }

    /**
     * Generate a TypeScript file from the schema.
     *
     * Creates a complete TypeScript file with:
     * - File header with generation info
     * - Import statements
     * - DataObject interfaces
     * - Model interfaces
     * - JSON-LD support (if enabled)
     *
     * @param  string  $path  The absolute path where the file will be created
     *
     * @throws \Illuminate\Contracts\Filesystem\FileNotFoundException
     */
    public function generateFile(string $path): void
    {
        $content = $this->toTypeScript();

        if ($this->withJsonLd) {
            $content .= $this->jsonLdGenerator->generate();
        }

        File::put($path, $content);
    }

    /**
     * Convert the schema to TypeScript code.
     *
     * This is the main conversion method that orchestrates all components.
     * The conversion process follows these steps:
     *
     * 1. Generate file header
     * 2. Collect and generate imports
     * 3. Process DataObject interfaces
     * 4. Process nested DataObjects
     * 5. Generate model interfaces
     *
     * @return string The complete TypeScript code
     */
    public function toTypeScript(): string
    {
        $output = $this->generateHeader();
        $output .= $this->generateImports();
        $output .= $this->processDataObjects();
        $output .= $this->processModels();

        return $output;
    }

    /**
     * Generate the file header.
     *
     * Creates a commented header with:
     * - File description
     * - Warning about auto-generation
     * - Command to regenerate
     *
     * @return string The header comment block
     */
    private function generateHeader(): string
    {
        return "/**\n"
            ." * Generated TypeScript interfaces\n"
            ." *\n"
            ." * This file is auto-generated. Do not edit directly.\n"
            ." * Run `php artisan oi:gen-ts` to regenerate it.\n"
            ." *\n"
            .' * @generated '.date('Y-m-d H:i:s')."\n"
            ."*/\n\n";
    }

    /**
     * Generate TypeScript import statements.
     *
     * Scans the schema for external type imports and generates
     * the appropriate import statements.
     *
     * @return string The import statements (empty string if no imports)
     */
    private function generateImports(): string
    {
        $this->importManager->collectImports($this->schema);

        return $this->importManager->generateImports();
    }

    /**
     * Process all DataObjects in the schema.
     *
     * Handles both direct DataObject fields and nested DataObjects
     * discovered during processing.
     *
     * @return string The DataObject interface definitions
     */
    private function processDataObjects(): string
    {
        // Process DataObjects directly in the schema
        foreach ($this->schema as $model) {
            foreach ($model['types'] as $field) {
                if (isset($field['isDataObject']) && $field['isDataObject']) {
                    $this->dataObjectProcessor->processDataObject($field);
                }
            }
        }

        // Process nested DataObjects discovered during processing
        while ($this->dataObjectProcessor->hasPendingDataObjects()) {
            $dataObjectClass = $this->dataObjectProcessor->getNextPendingDataObject();
            if ($dataObjectClass) {
                $this->dataObjectProcessor->processNestedDataObject($dataObjectClass);
            }
        }

        return $this->dataObjectProcessor->getOutput();
    }

    /**
     * Process all models in the schema.
     *
     * Generates TypeScript interfaces for all Laravel models.
     *
     * @return string The model interface definitions
     */
    private function processModels(): string
    {
        foreach ($this->schema as $model) {
            $this->modelGenerator->processModel($model);
        }

        return $this->modelGenerator->getOutput();
    }

    /**
     * Get the schema being converted.
     *
     * @return array<string, array> The model schema
     */
    public function getSchema(): array
    {
        return $this->schema;
    }

    /**
     * Check if JSON-LD support is enabled.
     *
     * @return bool True if JSON-LD support is enabled
     */
    public function isJsonLdEnabled(): bool
    {
        return $this->withJsonLd;
    }

    /**
     * Get the import manager instance.
     *
     * Useful for testing or inspecting collected imports.
     *
     * @return ImportManager The import manager
     */
    public function getImportManager(): ImportManager
    {
        return $this->importManager;
    }

    /**
     * Get the DataObject processor instance.
     *
     * Useful for testing or accessing processed DataObjects.
     *
     * @return DataObjectProcessor The DataObject processor
     */
    public function getDataObjectProcessor(): DataObjectProcessor
    {
        return $this->dataObjectProcessor;
    }

    /**
     * Get the model interface generator instance.
     *
     * Useful for testing or accessing generated interfaces.
     *
     * @return ModelInterfaceGenerator The model generator
     */
    public function getModelGenerator(): ModelInterfaceGenerator
    {
        return $this->modelGenerator;
    }

    /**
     * Get the JSON-LD generator instance.
     *
     * @return JsonLdGenerator The JSON-LD generator
     */
    public function getJsonLdGenerator(): JsonLdGenerator
    {
        return $this->jsonLdGenerator;
    }
}
