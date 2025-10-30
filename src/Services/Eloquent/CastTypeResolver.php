<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use ReflectionClass;
use ReflectionException;
use ReflectionNamedType;

/**
 * Cast Type Resolver
 *
 * Resolves Laravel custom cast classes to their TypeScript type equivalents.
 * Custom casts in Laravel implement the CastsAttributes interface and can
 * return complex types like DataObjects or arrays of DataObjects.
 *
 * This resolver:
 * - Identifies custom cast classes
 * - Analyzes their return types
 * - Detects DataObject returns
 * - Handles array return types with PHPDoc analysis
 * - Generates appropriate TypeScript type information
 *
 *
 * @example
 * ```php
 * // For a cast like:
 * // class AddressCast implements CastsAttributes {
 * //   public function get(...): Address { }
 * // }
 *
 * $resolver = new CastTypeResolver(new DataObjectAnalyzer(...));
 * $typeInfo = $resolver->resolve(AddressCast::class, 'address');
 * // Returns type information for generating TypeScript interface
 * ```
 */
class CastTypeResolver
{
    /**
     * DataObject analyzer instance.
     */
    private DataObjectAnalyzer $dataObjectAnalyzer;

    /**
     * Create a new cast type resolver instance.
     *
     * @param  DataObjectAnalyzer  $dataObjectAnalyzer  The analyzer to use for DataObject detection
     */
    public function __construct(DataObjectAnalyzer $dataObjectAnalyzer)
    {
        $this->dataObjectAnalyzer = $dataObjectAnalyzer;
    }

    /**
     * Resolve a custom cast class to its TypeScript type information.
     *
     * Analyzes the cast class's get() method to determine what type it returns.
     * Supports:
     * - Direct DataObject returns
     * - Array returns (with PHPDoc for item type)
     * - Nested DataObject discovery
     *
     * Returns null if the cast class is not a valid CastsAttributes implementation.
     *
     * @param  string  $castClass  The fully qualified cast class name
     * @param  string  $columnName  The column name this cast is applied to
     * @return array{
     *   field: string,
     *   type: string,
     *   relation: bool,
     *   isDataObject: bool,
     *   dataObjectClass?: class-string,
     *   properties?: array,
     *   nullable?: bool,
     *   isArray?: bool
     * }|null Type information for TypeScript generation, or null if not a custom cast
     *
     * @throws ReflectionException If reflection fails
     *
     * @example
     * ```php
     * // For AddressCast returning Address DataObject:
     * $info = $resolver->resolve(AddressCast::class, 'address');
     * // Returns:
     * // [
     * //   'field' => 'address',
     * //   'type' => 'Address',
     * //   'relation' => false,
     * //   'isDataObject' => true,
     * //   'dataObjectClass' => 'App\DataObjects\Address',
     * //   'properties' => [...],
     * //   'nullable' => false
     * // ]
     * ```
     */
    public function resolve(string $castClass, string $columnName): ?array
    {
        try {
            $reflection = new ReflectionClass($castClass);

            // Verify it's a custom cast implementing CastsAttributes
            if (! $reflection->implementsInterface(CastsAttributes::class)) {
                return null;
            }

            // Analyze the get() method to determine return type
            if (! $reflection->hasMethod('get')) {
                return null;
            }

            $getMethod = $reflection->getMethod('get');
            $returnType = $getMethod->getReturnType();

            if (! $returnType instanceof ReflectionNamedType) {
                return null;
            }

            $returnTypeName = $returnType->getName();

            // Handle class return types (DataObjects)
            if (class_exists($returnTypeName)) {
                return $this->resolveClassReturnType($returnTypeName, $columnName, $returnType);
            }

            // Handle array return types (potentially arrays of DataObjects)
            if ($returnTypeName === 'array') {
                return $this->resolveArrayReturnType($getMethod, $reflection, $columnName, $returnType);
            }

            return null;
        } catch (ReflectionException $e) {
            return null;
        }
    }

    /**
     * Resolve a class return type to TypeScript type information.
     *
     * Checks if the returned class is a DataObject and extracts its properties.
     *
     * @param  string  $returnTypeName  The fully qualified class name of the return type
     * @param  string  $columnName  The column name
     * @param  ReflectionNamedType  $returnType  The return type reflection
     * @return array{
     *   field: string,
     *   type: string,
     *   relation: bool,
     *   isDataObject: bool,
     *   dataObjectClass: class-string,
     *   properties: array,
     *   nullable: bool
     * }|null Type information, or null if not a DataObject
     *
     * @throws ReflectionException If reflection fails
     */
    private function resolveClassReturnType(
        string $returnTypeName,
        string $columnName,
        ReflectionNamedType $returnType
    ): ?array {
        $dataObjectReflection = new ReflectionClass($returnTypeName);
        $dataObjectName = $dataObjectReflection->getShortName();

        if (! $this->dataObjectAnalyzer->isDataObject($dataObjectReflection)) {
            return null;
        }

        $properties = $this->dataObjectAnalyzer->extractProperties($dataObjectReflection);

        return [
            'field' => $columnName,
            'type' => $dataObjectName,
            'relation' => false,
            'isDataObject' => true,
            'dataObjectClass' => $returnTypeName,
            'properties' => $properties,
            'nullable' => $returnType->allowsNull(),
        ];
    }

    /**
     * Resolve an array return type to TypeScript type information.
     *
     * Analyzes PHPDoc comments to determine the array item type.
     * Looks for patterns like @return array<int, DataObject>.
     *
     * @param  \ReflectionMethod  $getMethod  The get() method to analyze
     * @param  ReflectionClass  $reflection  The cast class reflection
     * @param  string  $columnName  The column name
     * @param  ReflectionNamedType  $returnType  The return type reflection
     * @return array{
     *   field: string,
     *   type: string,
     *   relation: bool,
     *   isDataObject: bool,
     *   isArray: bool,
     *   dataObjectClass: class-string,
     *   properties: array,
     *   nullable: bool
     * }|null Type information, or null if array item type cannot be determined
     *
     * @throws ReflectionException If reflection fails
     */
    private function resolveArrayReturnType(
        \ReflectionMethod $getMethod,
        ReflectionClass $reflection,
        string $columnName,
        ReflectionNamedType $returnType
    ): ?array {
        $docComment = $getMethod->getDocComment();
        if ($docComment === false) {
            return null;
        }

        // Extract array item type from @return array<int, DataObject>
        if (! preg_match('/@return\s+array<[^,]+,\s*([^>]+)>/', $docComment, $matches)) {
            return null;
        }

        $arrayItemType = trim($matches[1]);

        // Resolve the full namespace if needed
        $arrayItemType = $this->resolveFullClassName($arrayItemType, $reflection);

        if (! class_exists($arrayItemType)) {
            return null;
        }

        $dataObjectReflection = new ReflectionClass($arrayItemType);
        $dataObjectName = $dataObjectReflection->getShortName();

        if (! $this->dataObjectAnalyzer->isDataObject($dataObjectReflection)) {
            return null;
        }

        $properties = $this->dataObjectAnalyzer->extractProperties($dataObjectReflection);

        return [
            'field' => $columnName,
            'type' => $dataObjectName.'[]',
            'relation' => false,
            'isDataObject' => true,
            'isArray' => true,
            'dataObjectClass' => $arrayItemType,
            'properties' => $properties,
            'nullable' => $returnType->allowsNull(),
        ];
    }

    /**
     * Resolve a short class name to its fully qualified name.
     *
     * Attempts to resolve relative class names by checking:
     * 1. The same namespace as the cast class
     * 2. The App\DataObjects namespace
     *
     * @param  string  $className  The short or relative class name
     * @param  ReflectionClass  $contextClass  The class providing namespace context
     * @return string The fully qualified class name
     */
    private function resolveFullClassName(string $className, ReflectionClass $contextClass): string
    {
        // Already fully qualified
        if (str_contains($className, '\\')) {
            return $className;
        }

        // Try same namespace as cast class
        $namespace = $contextClass->getNamespaceName();
        $possibleClass = $namespace.'\\'.ltrim($className, '\\');

        if (class_exists($possibleClass)) {
            return $possibleClass;
        }

        // Try App\DataObjects namespace
        $possibleClass = 'App\\DataObjects\\'.$className;
        if (class_exists($possibleClass)) {
            return $possibleClass;
        }

        return $className;
    }
}
