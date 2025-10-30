<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

/**
 * PHP to TypeScript Type Converter
 *
 * Converts PHP type annotations (both native types and PHPDoc types)
 * to their TypeScript equivalents. Handles:
 * - Native PHP types (int, string, bool, etc.)
 * - Union types (string|int)
 * - Generic array types (array<int, string>)
 * - Record types (array<string, mixed>)
 * - Custom DataObject types
 *
 *
 * @example
 * ```php
 * $converter = new PhpToTypeScriptConverter();
 * $tsType = $converter->convertPhpDocToTs('array<int, string>|null');
 * // Returns: "string[]"
 * ```
 */
class PhpToTypeScriptConverter
{
    /**
     * Convert a PHPDoc type annotation to TypeScript.
     *
     * Handles complex PHPDoc types including unions, generics, and custom types.
     * Supports the following patterns:
     * - Union types: string|int|null
     * - Generic arrays: array<int, Type>
     * - Record types: array<string, mixed>
     * - DataObject references: CustomDataObject
     *
     * @param  string  $phpDocType  The PHPDoc type to convert
     * @return string The TypeScript equivalent type
     *
     * @example
     * ```php
     * $converter->convertPhpDocToTs('string|int');
     * // Returns: "string | number"
     *
     * $converter->convertPhpDocToTs('array<int, User>');
     * // Returns: "IUser[]"
     *
     * $converter->convertPhpDocToTs('array<string, mixed>');
     * // Returns: "Record<string, unknown>"
     * ```
     */
    public function convertPhpDocToTs(string $phpDocType): string
    {
        // Handle union types like string|array<int, string>|null
        if (str_contains($phpDocType, '|')) {
            $types = $this->splitUnionType($phpDocType);
            $tsTypes = [];

            foreach ($types as $type) {
                $type = trim($type);
                if ($type === 'null') {
                    continue; // null handled by optional flag
                }
                $tsTypes[] = $this->convertSinglePhpDocType($type);
            }

            return implode(' | ', array_unique($tsTypes));
        }

        return $this->convertSinglePhpDocType($phpDocType);
    }

    /**
     * Split a union type string by pipe character.
     *
     * Respects nested generic types (e.g., array<int, string>|null)
     * and doesn't split on pipes within angle brackets.
     *
     * @param  string  $type  The union type string to split
     * @return array<int, string> Array of individual types
     *
     * @example
     * ```php
     * $converter->splitUnionType('string|array<int, User>|null');
     * // Returns: ['string', 'array<int, User>', 'null']
     * ```
     */
    public function splitUnionType(string $type): array
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
     * Handles individual type conversions including generics and custom types.
     * This method is called by convertPhpDocToTs for each part of a union type.
     *
     * @param  string  $phpDocType  The single PHPDoc type to convert
     * @return string The TypeScript equivalent
     */
    private function convertSinglePhpDocType(string $phpDocType): string
    {
        // Handle array<string, mixed> -> Record<string, unknown>
        if (preg_match('/^array<string,\s*mixed>$/', $phpDocType)) {
            return 'Record<string, unknown>';
        }

        // Handle array<string, Type> -> Record<string, Type>
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

            return $this->phpTypeToTypeScript($itemType).'[]';
        }

        return $this->phpTypeToTypeScript($phpDocType);
    }

    /**
     * Convert a Record type value to TypeScript.
     *
     * Handles array<string, Type> conversions including union types
     * in the value position.
     *
     * @param  string  $valueType  The type of values in the record
     * @return string TypeScript Record type
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
                    $tsUnionParts[] = $this->phpTypeToTypeScript($part);
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

        return 'Record<string, '.$this->phpTypeToTypeScript($valueType).'>';
    }

    /**
     * Convert an array type to TypeScript.
     *
     * Handles array<int, Type> conversions including union types
     * in the element position.
     *
     * @param  string  $itemType  The type of array elements
     * @return string TypeScript array type
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
                    $tsUnionParts[] = $this->phpTypeToTypeScript($part);
                }
            }

            return '('.implode(' | ', $tsUnionParts).')[]';
        }

        if (class_exists("App\\DataObjects\\{$itemType}")) {
            return "I{$itemType}[]";
        }

        return $this->phpTypeToTypeScript($itemType).'[]';
    }

    /**
     * Convert a native PHP type to TypeScript.
     *
     * Converts basic PHP types and handles array notation (Type[]).
     * This is the final conversion step for primitive types.
     *
     * @param  string  $phpType  The PHP type to convert
     * @return string The TypeScript equivalent
     *
     * @example
     * ```php
     * $converter->phpTypeToTypeScript('int');      // Returns: "number"
     * $converter->phpTypeToTypeScript('string');   // Returns: "string"
     * $converter->phpTypeToTypeScript('bool');     // Returns: "boolean"
     * $converter->phpTypeToTypeScript('mixed');    // Returns: "unknown"
     * $converter->phpTypeToTypeScript('string[]'); // Returns: "string[]"
     * ```
     */
    public function phpTypeToTypeScript(string $phpType): string
    {
        // Handle array notation
        if (str_ends_with($phpType, '[]')) {
            $baseType = substr($phpType, 0, -2);

            return $this->phpTypeToTypeScript($baseType).'[]';
        }

        // Handle array<Type> pattern
        if (preg_match('/array<([^>]+)>/', $phpType, $match)) {
            return $this->phpTypeToTypeScript($match[1]).'[]';
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
}
