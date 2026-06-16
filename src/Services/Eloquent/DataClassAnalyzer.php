<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use OiLab\OiLaravelTs\Services\DataClassResolver;
use OiLab\OiLaravelTs\Support\EnumTypeResolver;
use ReflectionClass;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Data Class Analyzer
 *
 * Extracts the TypeScript-facing structure of a spatie/laravel-data style DTO.
 *
 * Properties are read from the constructor's promoted parameters. The declared
 * type is resolved, in priority order, from:
 *   1. The promoted property's `@var` annotation (where spatie DTOs declare
 *      typed arrays such as `@var KnowledgeTagData[]`).
 *   2. The constructor's `@param` annotation.
 *   3. The native parameter type.
 *
 * Backed enums become literal unions, nested DTOs become `I{Name}` references,
 * and typed arrays become `IFoo[]`.
 */
class DataClassAnalyzer
{
    public function __construct(
        private readonly PhpToTypeScriptConverter $typeConverter,
        private readonly DataClassResolver $dataClassResolver,
    ) {}

    /**
     * Extract properties from a DTO class.
     *
     * @return array<int, array{name: string, type: string, nullable: bool, hasDefault: bool}>
     */
    public function extractProperties(ReflectionClass $reflection): array
    {
        $constructor = $reflection->getConstructor();

        if ($constructor === null) {
            return [];
        }

        $paramDocTypes = $this->extractParamDocTypes($constructor->getDocComment() ?: '');
        $properties = [];

        foreach ($constructor->getParameters() as $parameter) {
            $name = $parameter->getName();
            $phpDocType = $this->propertyVarType($reflection, $name) ?? ($paramDocTypes[$name] ?? null);

            if ($phpDocType !== null) {
                $tsType = $this->resolveType($phpDocType, $reflection);
            } elseif (($nativeType = $parameter->getType()) !== null) {
                $tsType = $this->resolveNativeType($nativeType, $reflection);
            } else {
                $tsType = 'unknown';
            }

            if ($tsType === '') {
                $tsType = 'unknown';
            }

            $properties[] = [
                'name' => $name,
                'type' => $tsType,
                'nullable' => $parameter->allowsNull(),
                'hasDefault' => $parameter->isDefaultValueAvailable(),
            ];
        }

        return $properties;
    }

    /**
     * Resolve a native reflection type to TypeScript.
     */
    private function resolveNativeType(\ReflectionType $type, ReflectionClass $context): string
    {
        if ($type instanceof ReflectionUnionType) {
            $parts = [];

            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType && $member->getName() !== 'null') {
                    $parts[] = $this->resolveLeaf($member->getName(), $context);
                }
            }

            return implode(' | ', array_values(array_unique($parts)));
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->resolveLeaf($type->getName(), $context);
        }

        return 'unknown';
    }

    /**
     * Resolve a PHPDoc type expression to TypeScript, handling unions, arrays
     * and generic collections recursively.
     */
    private function resolveType(string $type, ReflectionClass $context): string
    {
        $type = trim($type);

        if (str_contains($type, '|')) {
            $parts = [];

            foreach ($this->typeConverter->splitUnionType($type) as $part) {
                $part = trim($part);
                if ($part === 'null' || $part === '') {
                    continue;
                }
                $parts[] = $this->resolveType($part, $context);
            }

            return implode(' | ', array_values(array_unique($parts)));
        }

        if (str_ends_with($type, '[]')) {
            return $this->arrayOf($this->resolveType(substr($type, 0, -2), $context));
        }

        if (preg_match('/^array<\s*string\s*,\s*(.+)>$/s', $type, $match)) {
            $inner = trim($match[1]);

            return $inner === 'mixed'
                ? 'Record<string, unknown>'
                : 'Record<string, '.$this->resolveType($inner, $context).'>';
        }

        if (preg_match('/^array<\s*(?:int|integer)\s*,\s*(.+)>$/s', $type, $match)) {
            return $this->arrayOf($this->resolveType(trim($match[1]), $context));
        }

        if (preg_match('/^array<\s*([^,>]+)\s*>$/s', $type, $match)) {
            return $this->arrayOf($this->resolveType(trim($match[1]), $context));
        }

        return $this->resolveLeaf($type, $context);
    }

    /**
     * Resolve a single (non-composite) type token to TypeScript.
     */
    private function resolveLeaf(string $token, ReflectionClass $context): string
    {
        $token = trim($token);

        $primitive = $this->primitive($token);
        if ($primitive !== null) {
            return $primitive;
        }

        $fqcn = $this->qualify($token, $context);

        if ($fqcn !== null) {
            $enum = EnumTypeResolver::toTypeScript($fqcn);
            if ($enum !== null) {
                return $enum;
            }

            if ($this->dataClassResolver->resolveDataClass($fqcn) !== null) {
                return 'I'.class_basename($fqcn);
            }
        }

        // DataObjects (fromArray/toArray) and remaining fallbacks (e.g. unknown).
        return $this->typeConverter->phpTypeToTypeScript($token);
    }

    /**
     * Append a `[]` suffix, parenthesizing union members so `'a' | 'b'` becomes
     * `('a' | 'b')[]` rather than the malformed `'a' | 'b'[]`.
     */
    private function arrayOf(string $type): string
    {
        return (str_contains($type, '|') ? "({$type})" : $type).'[]';
    }

    /**
     * Map a primitive PHP/PHPDoc type to TypeScript, or null when not a primitive.
     */
    private function primitive(string $token): ?string
    {
        return match (strtolower($token)) {
            'int', 'integer', 'float', 'double' => 'number',
            'string' => 'string',
            'bool', 'boolean', 'true', 'false' => 'boolean',
            'array' => 'unknown[]',
            'mixed' => 'unknown',
            'object' => 'Record<string, unknown>',
            'void', 'never' => 'never',
            default => null,
        };
    }

    /**
     * Resolve a class/enum reference (short name or FQCN) to a fully qualified
     * name, or null when it cannot be resolved.
     */
    private function qualify(string $token, ReflectionClass $context): ?string
    {
        $token = ltrim($token, '\\');

        if ($token === '') {
            return null;
        }

        if (str_contains($token, '\\')) {
            return (class_exists($token) || enum_exists($token)) ? $token : null;
        }

        $dto = $this->dataClassResolver->resolveDataClass($token);
        if ($dto !== null) {
            return $dto;
        }

        $candidate = $context->getNamespaceName().'\\'.$token;
        if (class_exists($candidate) || enum_exists($candidate)) {
            return $candidate;
        }

        return null;
    }

    /**
     * Extract the `@var` type of a (promoted) property.
     */
    private function propertyVarType(ReflectionClass $reflection, string $name): ?string
    {
        if (! $reflection->hasProperty($name)) {
            return null;
        }

        $doc = $reflection->getProperty($name)->getDocComment();

        if ($doc === false) {
            return null;
        }

        if (preg_match('/@var\s+(.+)/', $doc, $match)) {
            return trim(preg_replace('/\s*\*\/\s*$/', '', $match[1]));
        }

        return null;
    }

    /**
     * Parse `@param TYPE $name` annotations from a constructor doc comment.
     *
     * @return array<string, string>
     */
    private function extractParamDocTypes(string $docComment): array
    {
        $types = [];

        if ($docComment === '') {
            return $types;
        }

        if (preg_match_all('/@param\s+(.+?)\s+\$(\w+)/', $docComment, $matches, PREG_SET_ORDER)) {
            foreach ($matches as $match) {
                $types[$match[2]] = trim($match[1]);
            }
        }

        return $types;
    }
}
