<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use ReflectionClass;
use ReflectionUnionType;

/**
 * DataObject Analyzer
 *
 * Analyzes PHP DataObject classes to extract their structure and properties.
 * DataObjects are PHP classes that have fromArray() and toArray() methods,
 * typically used for structured data transfer between layers.
 *
 * This analyzer:
 * - Validates if a class is a DataObject
 * - Extracts constructor parameters as properties
 * - Converts PHP types to TypeScript equivalents
 * - Handles PHPDoc annotations
 * - Supports nullable types and default values
 *
 *
 * @example
 * ```php
 * $analyzer = new DataObjectAnalyzer(new PhpToTypeScriptConverter());
 * $isDataObject = $analyzer->isDataObject(new ReflectionClass(UserData::class));
 * $properties = $analyzer->extractProperties(new ReflectionClass(UserData::class));
 * ```
 */
class DataObjectAnalyzer
{
    /**
     * Type converter for PHP to TypeScript conversion.
     */
    private PhpToTypeScriptConverter $typeConverter;

    /**
     * Create a new DataObject analyzer instance.
     *
     * @param  PhpToTypeScriptConverter  $typeConverter  The type converter to use
     */
    public function __construct(PhpToTypeScriptConverter $typeConverter)
    {
        $this->typeConverter = $typeConverter;
    }

    /**
     * Check if a class is a DataObject.
     *
     * A class is considered a DataObject if it has both fromArray()
     * and toArray() methods. This is a common pattern in Laravel
     * applications for data transfer objects.
     *
     * @param  ReflectionClass  $reflection  The class to check
     * @return bool True if the class is a DataObject
     *
     * @example
     * ```php
     * $reflection = new ReflectionClass(UserData::class);
     * if ($analyzer->isDataObject($reflection)) {
     *     // Process as DataObject
     * }
     * ```
     */
    public function isDataObject(ReflectionClass $reflection): bool
    {
        return $reflection->hasMethod('fromArray') && $reflection->hasMethod('toArray');
    }

    /**
     * Extract properties from a DataObject class.
     *
     * Analyzes the constructor parameters to extract property information.
     * For each parameter, extracts:
     * - Property name
     * - TypeScript type (from PHPDoc or native type)
     * - Nullable flag
     * - Default value availability
     *
     * PHPDoc type hints are preferred over native PHP types when available.
     *
     * @param  ReflectionClass  $reflection  The DataObject class to analyze
     * @return array<int, array{name: string, type: string, nullable: bool, hasDefault: bool}> Array of property metadata
     *
     * @example
     * ```php
     * // For this DataObject:
     * // class UserData {
     * //   public function __construct(
     * //     public string $name,
     * //     public ?int $age = null
     * //   ) {}
     * // }
     *
     * $properties = $analyzer->extractProperties(new ReflectionClass(UserData::class));
     * // Returns:
     * // [
     * //   ['name' => 'name', 'type' => 'string', 'nullable' => false, 'hasDefault' => false],
     * //   ['name' => 'age', 'type' => 'number', 'nullable' => true, 'hasDefault' => true],
     * // ]
     * ```
     */
    public function extractProperties(ReflectionClass $reflection): array
    {
        $properties = [];

        if (! $reflection->hasMethod('__construct')) {
            return $properties;
        }

        $constructor = $reflection->getMethod('__construct');
        $parameters = $constructor->getParameters();
        $phpDocTypes = $this->extractPhpDocTypes($constructor->getDocComment() ?: '');

        foreach ($parameters as $parameter) {
            $paramName = $parameter->getName();
            $paramType = $parameter->getType();
            $nullable = $parameter->allowsNull();
            $tsType = 'unknown';

            // ALWAYS prefer PHPDoc over native types if available
            if (isset($phpDocTypes[$paramName])) {
                $tsType = $this->typeConverter->convertPhpDocToTs($phpDocTypes[$paramName]);
            }
            // Fallback to ReflectionType if no PHPDoc
            elseif ($paramType) {
                $tsType = $this->convertReflectionType($paramType);
            }

            $properties[] = [
                'name' => $paramName,
                'type' => $tsType,
                'nullable' => $nullable,
                'hasDefault' => $parameter->isDefaultValueAvailable(),
            ];
        }

        return $properties;
    }

    /**
     * Extract PHPDoc type annotations from a doc comment.
     *
     * Parses @param annotations to extract parameter types.
     * Supports complex type annotations including generics and unions.
     *
     * @param  string  $docComment  The PHPDoc comment string
     *
     * @example
     * ```php
     * $docComment = <<<'DOC'
     * \/**
     *
     *  * @param string $name The user's name
     *  * @param array<int, string> $tags User tags
     *  *\/
     * DOC;
     *
     * $types = $analyzer->extractPhpDocTypes($docComment);
     * // Returns: ['name' => 'string', 'tags' => 'array<int, string>']
     * ```
     * @return array<string, string> Map of parameter names to their PHPDoc types
     */
    private function extractPhpDocTypes(string $docComment): array
    {
        $phpDocTypes = [];

        if ($docComment === '') {
            return $phpDocTypes;
        }

        // Match @param lines with format: @param TYPE $name
        if (preg_match_all('/@param\s+(.+?)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $paramType = trim($match[1]);
                $paramName = $match[2];
                $phpDocTypes[$paramName] = $paramType;
            }
        }

        return $phpDocTypes;
    }

    /**
     * Convert a ReflectionType to TypeScript.
     *
     * Handles both named types and union types from PHP reflection.
     * Falls back to this method when PHPDoc annotations are not available.
     *
     * @param  \ReflectionType  $paramType  The reflection type to convert
     * @return string The TypeScript equivalent
     *
     * @example
     * ```php
     * // For PHP: string|int|null
     * $tsType = $analyzer->convertReflectionType($reflectionUnionType);
     * // Returns: "string | number"
     * ```
     */
    private function convertReflectionType(\ReflectionType $paramType): string
    {
        // Handle Union Types (PHP 8+)
        if ($paramType instanceof ReflectionUnionType) {
            $types = [];
            foreach ($paramType->getTypes() as $type) {
                if ($type instanceof \ReflectionNamedType) {
                    $typeName = $type->getName();
                    if ($typeName !== 'null') {
                        $types[] = $this->typeConverter->phpTypeToTypeScript($typeName);
                    }
                }
            }

            return implode(' | ', array_unique($types));
        }

        // Handle Named Types
        if ($paramType instanceof \ReflectionNamedType) {
            return $this->typeConverter->phpTypeToTypeScript($paramType->getName());
        }

        return 'unknown';
    }
}
