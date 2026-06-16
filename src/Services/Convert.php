<?php

namespace OiLab\OiLaravelTs\Services;

use Illuminate\Contracts\Filesystem\FileNotFoundException;
use OiLab\OiLaravelTs\Services\Converters\TypeScriptTypeConverter;
use OiLab\OiLaravelTs\Services\Eloquent\DataClassAnalyzer;
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;
use OiLab\OiLaravelTs\Services\Generators\ImportManager;
use OiLab\OiLaravelTs\Services\Generators\InterfaceFileWriter;
use OiLab\OiLaravelTs\Services\Generators\InterfaceUnit;
use OiLab\OiLaravelTs\Services\Generators\JsonLdGenerator;
use OiLab\OiLaravelTs\Services\Generators\ModelInterfaceGenerator;
use OiLab\OiLaravelTs\Services\Processors\DataClassProcessor;
use OiLab\OiLaravelTs\Services\Processors\DataObjectProcessor;

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
     * Whether to discover and emit every DataObject under the configured
     * namespaces, even those not referenced by any model cast.
     */
    private bool $discoverAllDataObjects;

    /**
     * Whether mapped models should be replaced by their DTO interface rather
     * than emitting their own Eloquent interface.
     */
    private bool $dataReplacesModel;

    /**
     * Short names of models replaced by a DTO (only when $dataReplacesModel).
     *
     * @var array<int, string>
     */
    private array $replacedModelNames;

    /**
     * Resolver shared with the DataObject processor.
     */
    private DataObjectResolver $dataObjectResolver;

    /**
     * Resolver shared with the Data class (DTO) processor.
     */
    private DataClassResolver $dataClassResolver;

    /**
     * Import manager instance.
     */
    private ImportManager $importManager;

    /**
     * DataObject processor instance.
     */
    private DataObjectProcessor $dataObjectProcessor;

    /**
     * Data class (DTO) processor instance.
     */
    private DataClassProcessor $dataClassProcessor;

    /**
     * Model interface generator instance.
     */
    private ModelInterfaceGenerator $modelGenerator;

    /**
     * JSON-LD generator instance.
     */
    private JsonLdGenerator $jsonLdGenerator;

    /**
     * File writer instance.
     */
    private InterfaceFileWriter $fileWriter;

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
     * @param  bool  $discoverAllDataObjects  Whether to emit every DataObject under the configured namespaces
     * @param  array<int, string>  $dataNamespaces  Namespaces holding spatie-style DTOs to emit
     * @param  bool  $dataReplacesModel  Whether a mapped model's Eloquent interface is suppressed in favor of its DTO
     * @param  array<string, string>  $dataForModel  Explicit model => DTO mapping
     */
    public function __construct(
        array $schema,
        bool $withJsonLd = false,
        bool $discoverAllDataObjects = false,
        array $dataNamespaces = [],
        bool $dataReplacesModel = false,
        array $dataForModel = [],
    ) {
        $this->schema = $schema;
        $this->withJsonLd = $withJsonLd;
        $this->discoverAllDataObjects = $discoverAllDataObjects;
        $this->dataReplacesModel = $dataReplacesModel;

        // Initialize all components
        $this->typeConverter = new TypeScriptTypeConverter;
        $this->importManager = new ImportManager;
        $this->dataObjectResolver = new DataObjectResolver;
        $this->dataObjectProcessor = new DataObjectProcessor($this->typeConverter, $this->dataObjectResolver);
        $this->dataClassResolver = new DataClassResolver($dataNamespaces, $dataForModel);
        $this->dataClassProcessor = new DataClassProcessor(
            new DataClassAnalyzer(new PhpToTypeScriptConverter($this->dataObjectResolver), $this->dataClassResolver),
            $this->dataClassResolver,
        );
        $this->modelGenerator = new ModelInterfaceGenerator($this->typeConverter);
        $this->jsonLdGenerator = new JsonLdGenerator;
        $this->fileWriter = new InterfaceFileWriter;

        $this->replacedModelNames = $dataReplacesModel
            ? $this->dataClassResolver->replacedModelShortNames()
            : [];
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
     * @throws FileNotFoundException
     */
    public function generateFile(string $path): void
    {
        $content = $this->toTypeScript();

        if ($this->withJsonLd) {
            $content .= $this->jsonLdGenerator->generate();
        }

        $this->fileWriter->writeSingle($content, $path);
    }

    /**
     * Generate one TypeScript file per interface plus a barrel file.
     *
     * Each file imports exactly the interfaces it references. Use this for the
     * `multiple` output mode.
     *
     * @param  string  $directory  The directory where the files will be written
     * @param  string  $barrelFile  Name of the barrel file (default: index.ts)
     */
    public function generateFiles(string $directory, string $barrelFile = 'index.ts'): void
    {
        $this->importManager->collectImports($this->schema);

        $this->fileWriter->writeMultiple(
            $this->getInterfaceUnits(),
            $this->importManager->getImports(),
            $directory,
            $barrelFile,
        );
    }

    /**
     * Collect every generated interface as a structured unit.
     *
     * Triggers DataObject and model processing (idempotent thanks to the
     * generators' dedup guards), then merges their units. When JSON-LD support
     * is enabled, the shared `JsonLdRawNode` interface is appended as its own
     * unit so multi-file mode can emit it as a standalone, importable file.
     *
     * @return array<int, InterfaceUnit>
     */
    public function getInterfaceUnits(): array
    {
        $this->processDataObjects();
        $this->processDataClasses();
        $this->processModels();

        $units = array_merge(
            $this->dataObjectProcessor->getUnits(),
            $this->dataClassProcessor->getUnits(),
            $this->modelGenerator->getUnits(),
        );

        if ($this->withJsonLd) {
            $units[] = InterfaceUnit::make('JsonLdRawNode', trim($this->jsonLdGenerator->generate()));
        }

        return $units;
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
        $output .= $this->processDataClasses();
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

                // Also scan every field type for IXxx references. Accessors that return a
                // DataObject class and custom props typed as an existing interface (e.g.
                // 'IProductPrice') produce types like 'IMetadataData' without setting the
                // isDataObject flag. detectNestedDataObjects enqueues any matching class so
                // its interface is generated and importable.
                $this->dataObjectProcessor->detectNestedDataObjects($field['type'] ?? '');
            }
        }

        // Enqueue every DataObject under the configured namespaces. Dedup via
        // processedDataObjects ensures a DO also exposed by a cast is not emitted twice.
        if ($this->discoverAllDataObjects) {
            foreach ($this->dataObjectResolver->listDataObjectsInNamespaces() as $fqcn) {
                $this->dataObjectProcessor->enqueue($fqcn);
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
     * Process every spatie/laravel-data style DTO found under the configured
     * `data_namespaces`, emitting an `I{ClassName}` interface for each and for
     * every nested DTO they reference.
     *
     * @return string The DTO interface definitions
     */
    private function processDataClasses(): string
    {
        if ($this->dataClassResolver->getNamespaces() === []) {
            return '';
        }

        foreach ($this->dataClassResolver->listDataClassesInNamespaces() as $dataClass) {
            $this->dataClassProcessor->enqueue($dataClass);
        }

        while ($this->dataClassProcessor->hasPending()) {
            $dataClass = $this->dataClassProcessor->getNextPending();
            if ($dataClass !== null) {
                $this->dataClassProcessor->process($dataClass);
            }
        }

        return $this->dataClassProcessor->getOutput();
    }

    /**
     * Process all models in the schema.
     *
     * Generates TypeScript interfaces for all Laravel models. When
     * `data_replaces_model` is enabled, models mapped to a DTO are skipped so
     * their DTO interface becomes the single source of truth.
     *
     * @return string The model interface definitions
     */
    private function processModels(): string
    {
        foreach ($this->schema as $model) {
            if ($this->dataReplacesModel && in_array($model['model'], $this->replacedModelNames, true)) {
                continue;
            }

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
