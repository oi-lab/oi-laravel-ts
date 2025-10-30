<?php

namespace OiLab\OiLaravelTs\Services\Generators;

use OiLab\OiLaravelTs\Services\Converters\TypeScriptTypeConverter;

/**
 * Model Interface Generator
 *
 * Generates TypeScript interfaces from Laravel model schema definitions.
 * Handles model fields, relationships, and custom properties.
 */
class ModelInterfaceGenerator
{
    /**
     * List of already processed interface names to avoid duplicates.
     *
     * @var array<int, string>
     */
    private array $processedTypes = [];

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
     * Generate TypeScript interface for a model.
     *
     * Creates a complete interface definition with all model fields,
     * relationships, and metadata.
     *
     * @param array{
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
     * } $model The model schema definition
     */
    public function processModel(array $model): void
    {
        $interfaceName = "I{$model['model']}";

        // Skip if already processed
        if (in_array($interfaceName, $this->processedTypes)) {
            return;
        }

        $this->processedTypes[] = $interfaceName;
        $properties = [];

        foreach ($model['types'] as $field) {
            $properties[] = $this->convertField($field);
        }

        $this->output .= "export interface {$interfaceName} {\n";
        $this->output .= '    '.implode("\n    ", $properties)."\n";
        $this->output .= "}\n\n";
    }

    /**
     * Convert a single field definition to TypeScript property.
     *
     * Handles different field types:
     * - DataObject fields (with special handling for JsonLdData)
     * - Regular fields (string, number, boolean, etc.)
     * - Relationship fields
     * - Custom imported types
     *
     * @param array{
     *     field: string,
     *     type: string,
     *     relation: bool,
     *     model?: string,
     *     nullable?: bool,
     *     isDataObject?: bool,
     *     isArray?: bool,
     *     isImport?: bool
     * } $field The field definition
     * @return string The TypeScript property definition
     */
    private function convertField(array $field): string
    {
        $name = $field['field'];

        // Handle DataObject fields
        if (isset($field['isDataObject']) && $field['isDataObject']) {
            return $this->convertDataObjectField($field);
        }

        $type = $this->getTypeScriptType($field);
        $optional = ! $this->isRequired($field);

        return "{$name}".($optional ? '?' : '').": {$type};";
    }

    /**
     * Convert a DataObject field to TypeScript property.
     *
     * Special handling for:
     * - JsonLdData -> JsonLdRawNode[] | null
     * - Regular DataObjects -> IDataObjectName | null
     * - Array DataObjects -> IDataObjectName[] | null
     *
     * @param array{
     *     field: string,
     *     type: string,
     *     nullable?: bool,
     *     isArray?: bool
     * } $field The DataObject field definition
     * @return string The TypeScript property definition
     */
    private function convertDataObjectField(array $field): string
    {
        $name = $field['field'];
        $dataObjectName = str_replace('[]', '', $field['type']);

        // Special case: JsonLdData -> JsonLdRawNode[]
        if ($dataObjectName === 'JsonLdData') {
            $optional = $field['nullable'] ?? false;

            return "{$name}".($optional ? '?' : '').': JsonLdRawNode[] | null;';
        }

        $type = 'I'.$dataObjectName;

        // Add array notation if needed
        if (isset($field['isArray']) && $field['isArray']) {
            $type .= '[]';
        }

        $optional = $field['nullable'] ?? false;

        return "{$name}".($optional ? '?' : '').": {$type} | null;";
    }

    /**
     * Get the TypeScript type for a field.
     *
     * Determines the appropriate TypeScript type based on:
     * - Import types (external TypeScript types)
     * - Relationship types (HasOne, HasMany, etc.)
     * - Column types (string, integer, etc.)
     *
     * @param array{
     *     type: string,
     *     relation: bool,
     *     model?: string,
     *     isImport?: bool
     * } $field The field definition
     * @return string The TypeScript type
     */
    private function getTypeScriptType(array $field): string
    {
        // Handle imported types
        if (isset($field['isImport']) && $field['isImport']) {
            return $this->extractImportedType($field['type']);
        }

        // Handle relationship types
        if ($field['relation']) {
            return $this->typeConverter->convertRelationType(
                $field['type'],
                $field['model']
            );
        }

        // Handle column types
        return $this->typeConverter->convertColumnType($field['type']);
    }

    /**
     * Extract the interface name from an import type definition.
     *
     * Supports two formats:
     * - "@/path/to/file|InterfaceName" -> "InterfaceName"
     * - "@/path/to/file" -> basename of the file
     *
     * @param  string  $importType  The import type definition
     * @return string The interface name
     */
    private function extractImportedType(string $importType): string
    {
        if (str_contains($importType, '|')) {
            [, $interface] = explode('|', $importType);

            return $interface;
        }

        return basename($importType);
    }

    /**
     * Check if a field is required (non-optional).
     *
     * A field is optional if:
     * - It's a relationship (always optional in TypeScript)
     * - It ends with "_count" (relationship count fields)
     * - It's in the nullable fields list
     *
     * @param array{
     *     field: string,
     *     relation: bool
     * } $field The field definition
     * @return bool True if the field is required
     */
    private function isRequired(array $field): bool
    {
        // Relations are always optional
        if (isset($field['relation']) && $field['relation']) {
            return false;
        }

        // Count fields are always optional
        if (str_ends_with($field['field'], '_count')) {
            return false;
        }

        // Specific nullable fields based on database schema
        $nullableFields = [
            'description', // Common nullable field
        ];

        if (in_array($field['field'], $nullableFields)) {
            return false;
        }

        // All other fields are required
        return true;
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
     * Clear the generator state.
     *
     * Resets all internal state including processed types list and output.
     */
    public function reset(): void
    {
        $this->processedTypes = [];
        $this->output = '';
    }
}
