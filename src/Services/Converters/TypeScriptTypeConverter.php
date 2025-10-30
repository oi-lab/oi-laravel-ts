<?php

namespace OiLab\OiLaravelTs\Services\Converters;

/**
 * TypeScript Type Converter
 *
 * Handles the conversion of PHP types (including PHPDoc types) to TypeScript types.
 * Supports complex types like generics, unions, and array notations.
 */
class TypeScriptTypeConverter
{
    /**
     * Convert PHPDoc type annotation to TypeScript type.
     *
     * Handles complex types including:
     * - Union types (string|int)
     * - Generic arrays (array<int, string>)
     * - Record types (array<string, mixed>)
     * - Nested generics
     *
     * @param  string  $phpDocType  The PHPDoc type annotation
     * @return string The equivalent TypeScript type
     */
    public function convertPhpDocToTypeScript(string $phpDocType): string
    {
        // Handle union types like string|array<int, string>|null
        if (str_contains($phpDocType, '|')) {
            return $this->convertUnionType($phpDocType);
        }

        return $this->convertSinglePhpDocType($phpDocType);
    }

    /**
     * Convert a union type to TypeScript.
     *
     * Example: "string|int|null" -> "string | number"
     *
     * @param  string  $phpDocType  The union type string
     * @return string The TypeScript union type
     */
    private function convertUnionType(string $phpDocType): string
    {
        $types = $this->splitUnionType($phpDocType);
        $tsTypes = [];

        foreach ($types as $type) {
            $type = trim($type);
            if ($type === 'null') {
                continue; // null is handled by optional flag
            }

            $tsTypes[] = $this->convertSinglePhpDocType($type);
        }

        return implode(' | ', array_unique($tsTypes));
    }

    /**
     * Split a union type by pipe (|) while respecting nested generic types.
     *
     * Example: "string|array<int, string>|null" -> ["string", "array<int, string>", "null"]
     *
     * @param  string  $type  The union type string
     * @return array<int, string> Array of individual types
     */
    private function splitUnionType(string $type): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        for ($i = 0; $i < strlen($type); $i++) {
            $char = $type[$i];

            if ($char === '<') {
                $depth++;
                $current .= $char;
            } elseif ($char === '>') {
                $depth--;
                $current .= $char;
            } elseif ($char === '|' && $depth === 0) {
                // Found a top-level union separator
                if ($current !== '') {
                    $parts[] = trim($current);
                    $current = '';
                }
            } else {
                $current .= $char;
            }
        }

        // Add the last part
        if ($current !== '') {
            $parts[] = trim($current);
        }

        return $parts;
    }

    /**
     * Convert a single PHPDoc type (non-union) to TypeScript.
     *
     * Handles various PHP type patterns:
     * - array<string, mixed> -> Record<string, unknown>
     * - array<string, Type> -> Record<string, Type>
     * - array<int, Type> -> Type[]
     * - Simple types (string, int, bool, etc.)
     *
     * @param  string  $phpDocType  The PHPDoc type to convert
     * @return string The TypeScript type
     */
    private function convertSinglePhpDocType(string $phpDocType): string
    {
        // Handle array<string, mixed> -> Record<string, unknown>
        if (preg_match('/^array<string,\s*mixed>$/', $phpDocType)) {
            return 'Record<string, unknown>';
        }

        // Handle array<string, Type> -> Record<string, Type> (with nested types)
        if (preg_match('/^array<string,\s*(.+)>$/', $phpDocType, $match)) {
            return $this->convertRecordType($match[1]);
        }

        // Handle array<int, Type> -> Type[]
        if (preg_match('/^array<(?:int|integer),\s*(.+)>$/', $phpDocType, $match)) {
            return $this->convertArrayType($match[1]);
        }

        // Handle simple array<Type> -> Type[]
        if (preg_match('/^array<([^>]+)>$/', $phpDocType, $match)) {
            $itemType = trim($match[1]);
            if (class_exists("App\\DataObjects\\{$itemType}")) {
                return "I{$itemType}[]";
            }

            return $this->getSimpleTypeScriptType($itemType).'[]';
        }

        return $this->getSimpleTypeScriptType($phpDocType);
    }

    /**
     * Convert a Record type (associative array) to TypeScript.
     *
     * Example: "mixed" -> "Record<string, unknown>"
     * Example: "string|int" -> "Record<string, string | number>"
     *
     * @param  string  $valueType  The type of values in the record
     * @return string The TypeScript Record type
     */
    private function convertRecordType(string $valueType): string
    {
        $valueType = trim($valueType);

        // Handle union types in Record values
        if (str_contains($valueType, '|')) {
            $unionParts = preg_split('/\|(?![^<>]*>)/', $valueType);
            $tsUnionParts = [];
            foreach ($unionParts as $part) {
                $part = trim($part);
                if ($part === 'mixed') {
                    $tsUnionParts[] = 'unknown';
                } elseif (preg_match('/^array<string,\s*mixed>$/', $part)) {
                    $tsUnionParts[] = 'Record<string, unknown>';
                } else {
                    $tsUnionParts[] = $this->getSimpleTypeScriptType($part);
                }
            }

            return 'Record<string, '.implode(' | ', $tsUnionParts).'>';
        }

        if (class_exists("App\\DataObjects\\{$valueType}")) {
            return "Record<string, I{$valueType}>";
        }

        if ($valueType === 'mixed') {
            return 'Record<string, unknown>';
        }

        return 'Record<string, '.$this->getSimpleTypeScriptType($valueType).'>';
    }

    /**
     * Convert an indexed array type to TypeScript array notation.
     *
     * Example: "string" -> "string[]"
     * Example: "string|int" -> "(string | number)[]"
     *
     * @param  string  $itemType  The type of items in the array
     * @return string The TypeScript array type
     */
    private function convertArrayType(string $itemType): string
    {
        $itemType = trim($itemType);

        // Handle union types within arrays
        if (str_contains($itemType, '|')) {
            $unionParts = preg_split('/\|(?![^<>]*>)/', $itemType);
            $tsUnionParts = [];

            foreach ($unionParts as $part) {
                $part = trim($part);
                if (preg_match('/^array<string,\s*mixed>$/', $part)) {
                    $tsUnionParts[] = 'Record<string, unknown>';
                } elseif (class_exists("App\\DataObjects\\{$part}")) {
                    $tsUnionParts[] = "I{$part}";
                } else {
                    $tsUnionParts[] = $this->getSimpleTypeScriptType($part);
                }
            }

            return '('.implode(' | ', $tsUnionParts).')[]';
        }

        if (class_exists("App\\DataObjects\\{$itemType}")) {
            return "I{$itemType}[]";
        }

        return $this->getSimpleTypeScriptType($itemType).'[]';
    }

    /**
     * Get the TypeScript type for a simple PHP type.
     *
     * Maps basic PHP types to their TypeScript equivalents:
     * - int/integer/float/double -> number
     * - string -> string
     * - bool/boolean -> boolean
     * - array -> unknown[]
     * - mixed -> unknown
     * - object -> Record<string, unknown>
     *
     * @param  string  $phpType  The PHP type
     * @return string The equivalent TypeScript type
     */
    public function getSimpleTypeScriptType(string $phpType): string
    {
        // Handle array<Type> notation
        if (preg_match('/array<([^>]+)>/', $phpType, $match)) {
            return $this->getSimpleTypeScriptType($match[1]).'[]';
        }

        return match ($phpType) {
            'int', 'integer', 'float', 'double' => 'number',
            'string' => 'string',
            'bool', 'boolean' => 'boolean',
            'array' => 'unknown[]',
            'mixed' => 'unknown',
            'object' => 'Record<string, unknown>',
            default => 'unknown',
        };
    }

    /**
     * Convert Laravel database column type to TypeScript type.
     *
     * Maps Laravel/database column types to TypeScript:
     * - string/text/char/uuid -> string
     * - integer/bigInteger/number/decimal/float -> number
     * - boolean -> boolean
     * - date/datetime/timestamp -> string
     * - array/json -> Record<string, never>
     *
     * @param  string  $columnType  The Laravel column type
     * @return string The TypeScript type
     */
    public function convertColumnType(string $columnType): string
    {
        return match ($columnType) {
            'string', 'text', 'char', 'uuid' => 'string',
            'integer', 'bigInteger', 'number', 'decimal:6', 'float' => 'number',
            'boolean' => 'boolean',
            'date', 'datetime', 'timestamp' => 'string',
            'array', 'json' => 'Record<string, never>',
            default => 'never',
        };
    }

    /**
     * Convert Laravel relationship type to TypeScript type.
     *
     * Maps relationship types to their TypeScript equivalents:
     * - HasOne/BelongsTo -> IModel
     * - HasMany/BelongsToMany/MorphToMany/MorphMany -> IModel[]
     *
     * @param  string  $relationType  The Laravel relationship type
     * @param  string  $relatedModel  The fully qualified class name of the related model
     * @return string The TypeScript type
     */
    public function convertRelationType(string $relationType, string $relatedModel): string
    {
        $modelName = class_basename($relatedModel);

        return match ($relationType) {
            'HasOne', 'BelongsTo' => "I{$modelName}",
            'HasMany', 'BelongsToMany', 'MorphToMany', 'MorphMany' => "I{$modelName}[]",
            default => 'never',
        };
    }
}
