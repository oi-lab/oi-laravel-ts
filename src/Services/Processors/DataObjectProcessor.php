<?php

namespace OiLab\OiLaravelTs\Services\Processors;

use OiLab\OiLaravelTs\Services\Converters\TypeScriptTypeConverter;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * DataObject Processor
 *
 * Processes PHP DataObjects and converts them to TypeScript interfaces.
 * Handles nested DataObjects, PHPDoc types, and constructor parameters.
 */
class DataObjectProcessor
{
    /**
     * List of already processed DataObject names to avoid duplicates.
     *
     * @var array<int, string>
     */
    private array $processedDataObjects = [];

    /**
     * Queue of DataObject class names pending processing.
     *
     * @var array<int, string>
     */
    private array $pendingDataObjects = [];

    /**
     * The TypeScript output being built.
     */
    private string $output = '';

    /**
     * Constructor.
     *
     * @param  TypeScriptTypeConverter  $typeConverter  The type converter instance
     */
    public function __construct(
        private readonly TypeScriptTypeConverter $typeConverter
    ) {}

    /**
     * Process a DataObject field and generate its TypeScript interface.
     *
     * This method handles the conversion of a DataObject field from the schema
     * to a TypeScript interface definition.
     *
     * @param array{
     *     type: string,
     *     properties?: array<int, array{name: string, type: string, nullable: bool, hasDefault: bool}>,
     *     isDataObject?: bool
     * } $field The field definition from schema
     */
    public function processDataObject(array $field): void
    {
        $dataObjectName = str_replace('[]', '', $field['type']);

        // Skip JsonLdData - it will be treated as JsonLdRawNode[] directly
        if ($dataObjectName === 'JsonLdData') {
            return;
        }

        // Skip if already processed
        if (in_array($dataObjectName, $this->processedDataObjects)) {
            return;
        }

        $this->processedDataObjects[] = $dataObjectName;

        if (! isset($field['properties']) || empty($field['properties'])) {
            return;
        }

        $this->output .= "export interface I{$dataObjectName} {\n";

        foreach ($field['properties'] as $property) {
            $propName = $property['name'];
            $propType = $property['type'];
            $optional = $property['nullable'] || $property['hasDefault'];

            // Detect nested DataObjects
            $this->detectNestedDataObjects($propType);

            $this->output .= "    {$propName}".($optional ? '?' : '').": {$propType};\n";
        }

        $this->output .= "}\n\n";
    }

    /**
     * Detect nested DataObjects in a TypeScript type string.
     *
     * Searches for patterns like IJsonLdNode, IMetadata[], etc.,
     * and adds them to the pending queue for processing.
     *
     * @param  string  $tsType  The TypeScript type to analyze
     */
    public function detectNestedDataObjects(string $tsType): void
    {
        // Search for patterns like IJsonLdNode, IJsonLdNode[], etc.
        if (preg_match_all('/I([A-Z][a-zA-Z0-9]+)/', $tsType, $matches)) {
            foreach ($matches[1] as $dataObjectName) {
                $dataObjectClass = "App\\DataObjects\\{$dataObjectName}";
                if (class_exists($dataObjectClass) &&
                    ! in_array($dataObjectName, $this->processedDataObjects) &&
                    ! in_array($dataObjectClass, $this->pendingDataObjects)) {
                    $this->pendingDataObjects[] = $dataObjectClass;
                }
            }
        }
    }

    /**
     * Process a nested DataObject discovered during parsing.
     *
     * This method uses reflection to analyze the DataObject class structure
     * and extract its properties from the constructor.
     *
     * @param  string  $dataObjectClass  The fully qualified class name
     */
    public function processNestedDataObject(string $dataObjectClass): void
    {
        $dataObjectName = class_basename($dataObjectClass);

        // Skip if already processed
        if (in_array($dataObjectName, $this->processedDataObjects)) {
            return;
        }

        $this->processedDataObjects[] = $dataObjectName;

        // Verify class exists
        if (! class_exists($dataObjectClass)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($dataObjectClass);

            // Verify it's a DataObject (has fromArray and toArray methods)
            if (! $reflection->hasMethod('fromArray') || ! $reflection->hasMethod('toArray')) {
                return;
            }

            // Extract properties from constructor
            if (! $reflection->hasMethod('__construct')) {
                return;
            }

            $constructor = $reflection->getMethod('__construct');
            $parameters = $constructor->getParameters();
            $docComment = $constructor->getDocComment();

            // Parse PHPDoc for parameter types
            $phpDocTypes = $this->parsePhpDocTypes($docComment);

            $this->output .= "export interface I{$dataObjectName} {\n";

            foreach ($parameters as $parameter) {
                $paramName = $parameter->getName();
                $nullable = $parameter->allowsNull();
                $hasDefault = $parameter->isDefaultValueAvailable();
                $tsType = 'unknown';

                // Use PHPDoc if available
                if (isset($phpDocTypes[$paramName])) {
                    $tsType = $this->typeConverter->convertPhpDocToTypeScript($phpDocTypes[$paramName]);
                } elseif ($parameter->getType()) {
                    $tsType = $this->convertParameterType($parameter->getType());
                }

                // Detect nested DataObjects
                $this->detectNestedDataObjects($tsType);

                $optional = $nullable || $hasDefault;
                $this->output .= "    {$paramName}".($optional ? '?' : '').": {$tsType};\n";
            }

            $this->output .= "}\n\n";
        } catch (ReflectionException $e) {
            // Ignore reflection errors
        }
    }

    /**
     * Parse PHPDoc comment to extract parameter types.
     *
     * Extracts @param annotations and returns a map of parameter names to types.
     *
     * Example PHPDoc:
     * ```
     *
     * @param  string  $name  The user's name
     * @param  int|null  $age  The user's age
     *                         ```
     *
     * Returns: ['name' => 'string', 'age' => 'int|null']
     * @param  string|false  $docComment  The PHPDoc comment
     * @return array<string, string> Map of parameter name to type
     */
    private function parsePhpDocTypes(string|false $docComment): array
    {
        $phpDocTypes = [];

        if ($docComment === false) {
            return $phpDocTypes;
        }

        // Match complete @param lines: @param TYPE $name
        if (preg_match_all('/@param\s+.+?\s+\$\w+/', $docComment, $fullMatches)) {
            foreach ($fullMatches[0] as $line) {
                if (preg_match('/@param\s+(.+?)\s+\$(\w+)/', $line, $parts)) {
                    $phpDocTypes[$parts[2]] = trim($parts[1]);
                }
            }
        }

        return $phpDocTypes;
    }

    /**
     * Convert a reflection type to TypeScript.
     *
     * Handles both union types (PHP 8+) and named types.
     *
     * @param  \ReflectionType  $paramType  The reflection type
     * @return string The TypeScript type
     */
    private function convertParameterType(\ReflectionType $paramType): string
    {
        // Handle Union Types (PHP 8+)
        if ($paramType instanceof ReflectionUnionType) {
            $types = [];
            foreach ($paramType->getTypes() as $type) {
                if ($type instanceof ReflectionNamedType) {
                    $typeName = $type->getName();
                    if ($typeName !== 'null') {
                        $types[] = $this->typeConverter->getSimpleTypeScriptType($typeName);
                    }
                }
            }

            return implode(' | ', array_unique($types));
        }

        // Handle Named Types
        if ($paramType instanceof ReflectionNamedType) {
            $phpType = $paramType->getName();

            return $this->typeConverter->getSimpleTypeScriptType($phpType);
        }

        return 'unknown';
    }

    /**
     * Check if there are pending DataObjects to process.
     *
     * @return bool True if there are pending DataObjects
     */
    public function hasPendingDataObjects(): bool
    {
        return ! empty($this->pendingDataObjects);
    }

    /**
     * Get the next pending DataObject class from the queue.
     *
     * @return string|null The next DataObject class, or null if queue is empty
     */
    public function getNextPendingDataObject(): ?string
    {
        return array_shift($this->pendingDataObjects);
    }

    /**
     * Get the generated TypeScript output.
     *
     * @return string The TypeScript interface definitions
     */
    public function getOutput(): string
    {
        return $this->output;
    }

    /**
     * Clear the processor state.
     *
     * Resets all internal state including processed/pending lists and output.
     */
    public function reset(): void
    {
        $this->processedDataObjects = [];
        $this->pendingDataObjects = [];
        $this->output = '';
    }
}
