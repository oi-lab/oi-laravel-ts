<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use OiLab\OiLaravelTs\Services\DataClassResolver;
use OiLab\OiLaravelTs\Support\EnumTypeResolver;
use OiLab\OiLaravelTs\Support\GenericTypeNormalizer;
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
 *
 * Nullability and optionality are reported as two independent facts:
 *   - `nullable`: the property accepts null, so its JSON value may be null.
 *   - `optional`: the property is declared through an `Optional` / `Lazy`
 *     marker, so its key may be missing from the JSON altogether.
 *
 * A default value implies neither: a serializer emits every declared property.
 */
class DataClassAnalyzer
{
    /**
     * Types that make a DTO property genuinely absent from the serialized payload.
     *
     * Matched on the fully qualified name or on the short class name, so a
     * project-local re-export of spatie's markers is recognized too.
     */
    public const DEFAULT_OPTIONAL_MARKERS = [
        'Spatie\\LaravelData\\Optional',
        'Spatie\\LaravelData\\Lazy',
    ];

    /**
     * @param  array<int, string>  $optionalMarkers
     */
    public function __construct(
        private readonly PhpToTypeScriptConverter $typeConverter,
        private readonly DataClassResolver $dataClassResolver,
        private readonly array $optionalMarkers = self::DEFAULT_OPTIONAL_MARKERS,
    ) {}

    /**
     * Extract properties from a DTO class.
     *
     * @return array<int, array{name: string, type: string, nullable: bool, hasDefault: bool, optional: bool}>
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
            $nativeType = $parameter->getType();

            if ($phpDocType !== null) {
                $tsType = $this->resolveType($phpDocType, $reflection);
                $tokens = $this->typeConverter->splitUnionType($phpDocType);
            } elseif ($nativeType !== null) {
                $tsType = $this->resolveNativeType($nativeType, $reflection);
                $tokens = $this->nativeTypeTokens($nativeType);
            } else {
                $tsType = 'unknown';
                $tokens = [];
            }

            if ($tsType === '') {
                $tsType = 'unknown';
            }

            $properties[] = [
                'name' => $name,
                'type' => $tsType,
                'nullable' => $parameter->allowsNull() || $this->tokensAllowNull($tokens),
                'hasDefault' => $parameter->isDefaultValueAvailable(),
                'optional' => $this->tokensContainOptionalMarker($tokens),
            ];
        }

        return $properties;
    }

    /**
     * The declared type tokens of a native reflection type, as written in PHP.
     *
     * @return array<int, string>
     */
    private function nativeTypeTokens(\ReflectionType $type): array
    {
        if ($type instanceof ReflectionUnionType) {
            $tokens = [];

            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType) {
                    $tokens[] = $member->getName();
                }
            }

            return $tokens;
        }

        if ($type instanceof ReflectionNamedType) {
            return [$type->getName()];
        }

        return [];
    }

    /**
     * Whether a PHPDoc union carries an explicit `null` member. The native type
     * is covered separately by `ReflectionParameter::allowsNull()`.
     *
     * @param  array<int, string>  $tokens
     */
    private function tokensAllowNull(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if (strcasecmp(trim($token), 'null') === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param  array<int, string>  $tokens
     */
    private function tokensContainOptionalMarker(array $tokens): bool
    {
        foreach ($tokens as $token) {
            if ($this->isOptionalMarker($token)) {
                return true;
            }
        }

        return false;
    }

    /**
     * Whether a type token denotes an `Optional` / `Lazy` absence marker.
     */
    private function isOptionalMarker(string $token): bool
    {
        $token = ltrim(trim($token), '\\');

        if ($token === '') {
            return false;
        }

        foreach ($this->optionalMarkers as $marker) {
            $marker = ltrim($marker, '\\');

            if (strcasecmp($token, $marker) === 0
                || strcasecmp(class_basename($token), class_basename($marker)) === 0) {
                return true;
            }
        }

        return false;
    }

    /**
     * Resolve a native reflection type to TypeScript.
     *
     * `null` members and absence markers carry no shape of their own: they are
     * reported through the `nullable` / `optional` flags instead.
     */
    private function resolveNativeType(\ReflectionType $type, ReflectionClass $context): string
    {
        if ($type instanceof ReflectionUnionType) {
            $parts = [];

            foreach ($type->getTypes() as $member) {
                if ($member instanceof ReflectionNamedType
                    && $member->getName() !== 'null'
                    && ! $this->isOptionalMarker($member->getName())) {
                    $parts[] = $this->resolveLeaf($member->getName(), $context);
                }
            }

            return implode(' | ', array_values(array_unique($parts)));
        }

        if ($type instanceof ReflectionNamedType) {
            return $this->isOptionalMarker($type->getName())
                ? ''
                : $this->resolveLeaf($type->getName(), $context);
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

        if ($this->isOptionalMarker($type)) {
            return '';
        }

        // Split on top-level pipes only: `array<int, string|int>` is one member,
        // and recursing on it as a union would never terminate.
        $members = $this->typeConverter->splitUnionType($type);

        if (count($members) > 1) {
            $parts = [];

            foreach ($members as $part) {
                $part = trim($part);
                if ($part === 'null' || $part === '' || $this->isOptionalMarker($part)) {
                    continue;
                }
                $parts[] = $this->resolveType($part, $context);
            }

            return implode(' | ', array_values(array_unique($parts)));
        }

        $type = GenericTypeNormalizer::normalize($type);

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

            $dataClass = $this->dataClassResolver->resolveDataClass($fqcn);

            if ($dataClass !== null) {
                return $this->dataClassResolver->interfaceName($dataClass);
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
