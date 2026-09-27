<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Collection;
use OiLab\OiLaravelTs\Support\ColumnTypeMapper;
use OiLab\OiLaravelTs\Support\EnumTypeResolver;
use ReflectionException;
use ReflectionFunction;
use ReflectionMethod;
use ReflectionNamedType;
use ReflectionUnionType;

/**
 * Type Extractor
 *
 * Extracts complete type information from Laravel Eloquent models for TypeScript generation.
 * Combines data from multiple sources:
 * - Model primary key and fillable attributes
 * - Cast types (including custom casts)
 * - Timestamps
 * - Relationships
 * - Custom property overrides
 *
 * Orchestrates the work of specialized components to build a complete type schema.
 *
 *
 * @example
 * ```php
 * $extractor = new TypeExtractor(
 *     new CastTypeResolver(...),
 *     new RelationshipResolver(),
 *     ['User' => ['role' => 'UserRole']]
 * );
 *
 * $types = $extractor->extractTypes(User::class, true);
 * // Returns Collection of type information for all model properties
 * ```
 */
class TypeExtractor
{
    /**
     * Cast type resolver instance.
     */
    private CastTypeResolver $castTypeResolver;

    /**
     * Relationship resolver instance.
     */
    private RelationshipResolver $relationshipResolver;

    /**
     * PHP to TypeScript type converter instance.
     */
    private PhpToTypeScriptConverter $phpToTsConverter;

    /**
     * Custom property type overrides.
     *
     * @var array<string, array<string, string>|string>
     */
    private array $customProps;

    /**
     * Whether to include count fields for relationships.
     */
    private bool $withCounts;

    /**
     * Reader of the model's table columns, or null to rely on the model's
     * declarations alone (`use_database_schema` disabled).
     */
    private ?ModelColumnReader $columnReader;

    /**
     * Create a new type extractor instance.
     *
     * @param  CastTypeResolver  $castTypeResolver  Resolver for custom cast types
     * @param  RelationshipResolver  $relationshipResolver  Resolver for model relationships
     * @param  PhpToTypeScriptConverter  $phpToTsConverter  PHP to TypeScript type converter
     * @param  array<string, array<string, string>|string>  $customProps  Custom property overrides
     * @param  bool  $withCounts  Whether to include relationship count fields
     * @param  ModelColumnReader|null  $columnReader  Source of column types and nullability
     */
    public function __construct(
        CastTypeResolver $castTypeResolver,
        RelationshipResolver $relationshipResolver,
        PhpToTypeScriptConverter $phpToTsConverter,
        array $customProps = [],
        bool $withCounts = true,
        ?ModelColumnReader $columnReader = null,
    ) {
        $this->castTypeResolver = $castTypeResolver;
        $this->relationshipResolver = $relationshipResolver;
        $this->phpToTsConverter = $phpToTsConverter;
        $this->customProps = $customProps;
        $this->withCounts = $withCounts;
        $this->columnReader = $columnReader;
    }

    /**
     * Extract all type information from a model class.
     *
     * Builds a complete collection of type information by:
     * 1. Adding the primary key
     * 2. Processing fillable attributes (with custom props and casts)
     * 3. Adding timestamps if enabled
     * 4. Adding relationships (with optional count fields)
     * 5. Adding any remaining custom props not covered above
     *
     * @param  class-string  $modelClass  The fully qualified model class name
     * @param  bool  $withCounts  Whether to include count fields for HasMany/BelongsToMany relations
     * @return Collection<int, array{
     *   field: string,
     *   type: string,
     *   relation: bool,
     *   nullable?: bool,
     *   isImport?: bool,
     *   isDataObject?: bool,
     *   dataObjectClass?: class-string,
     *   properties?: array,
     *   isArray?: bool,
     *   model?: class-string,
     *   pivot?: array
     * }> Collection of type information
     *
     * @throws ReflectionException If reflection fails
     *
     * @example
     * ```php
     * $types = $extractor->extractTypes(User::class);
     * // Collection [
     * //   ['field' => 'id', 'type' => 'number', 'relation' => false],
     * //   ['field' => 'name', 'type' => 'string', 'relation' => false],
     * //   ['field' => 'email', 'type' => 'string', 'relation' => false],
     * //   ['field' => 'created_at', 'type' => 'string', 'relation' => false],
     * //   ['field' => 'posts', 'type' => 'HasMany', 'relation' => true, 'model' => Post::class],
     * //   ['field' => 'posts_count', 'type' => 'number', 'relation' => false],
     * // ]
     * ```
     */
    public function extractTypes(string $modelClass): Collection
    {
        $model = new $modelClass;
        $modelName = class_basename($model);

        $types = collect([]);

        // Get custom props for this model
        $customModelProps = $this->customProps[$modelName] ?? [];

        // 1. Add primary key (pivot/ManyToMany models may have no primary key)
        $keyName = $model->getKeyName();
        $casts = $model->getCasts();
        if ($keyName !== null && $keyName !== '') {
            $types->push([
                'field' => $keyName,
                'type' => $this->resolveKeyType($model, $keyName, $casts),
                'relation' => false,
            ]);
        }

        // 2. Process attributes: the table's columns when the schema is
        // readable, the fillable list otherwise.
        $this->processAttributes($model, $types, $customModelProps);

        // 3. Add timestamps
        if ($model->timestamps) {
            $this->addTimestamps($model, $types, $customModelProps);
        }

        // 3b. Add soft delete timestamp if applicable
        if (in_array(SoftDeletes::class, class_uses_recursive($model))) {
            $this->addSoftDeleteTimestamp($model, $types, $customModelProps);
        }

        // 3c. Add appended attributes
        $this->addAppends($model, $types, $customModelProps);

        // 4. Add relationships
        $this->addRelationships($model, $types);

        // 5. Add remaining custom props
        $this->addRemainingCustomProps($types, $customModelProps);

        return $types;
    }

    /**
     * Resolve the column type for the model's primary key.
     *
     * Checks model casts first (simple types only — class-based casts such as
     * AsUuid are skipped so the key type falls back to `getKeyType()`).
     * Defaults to 'integer' for int keys and 'string' for string keys.
     *
     * @param  Model  $model  The model instance
     * @param  string  $keyName  The primary key column name
     * @param  array<string, string>  $casts  The model's cast definitions
     * @return string The column type (e.g. 'integer', 'string')
     */
    private function resolveKeyType(Model $model, string $keyName, array $casts): string
    {
        if (isset($casts[$keyName]) && ! class_exists($casts[$keyName])) {
            $cast = $casts[$keyName];

            // getCasts() auto-inserts 'int' for incrementing models; normalize to
            // 'integer' so convertColumnType() maps it correctly to 'number'.
            return $cast === 'int' ? 'integer' : $cast;
        }

        return match ($model->getKeyType()) {
            'string' => 'string',
            default => 'integer',
        };
    }

    /**
     * Process the model's attributes and add them to the types collection.
     *
     * The attributes are the table's columns when the schema can be read — that
     * is what the model serializes, fillable or not — and `$fillable` otherwise.
     * Hidden attributes never reach the JSON, so they are left out. Timestamp
     * and soft-delete columns are left to `addTimestamps()` and
     * `addSoftDeleteTimestamp()`.
     *
     * For each attribute, the type comes from, in order:
     * - a custom property override;
     * - an enum cast, as a literal union;
     * - a custom cast class;
     * - a built-in cast;
     * - the column's database type;
     * - `string`.
     *
     * Nullability comes from the column when the schema is readable.
     *
     * @param  Model  $model  The model instance
     * @param  Collection  $types  The types collection to add to
     * @param  array<string, string>  $customModelProps  Custom props for this model
     *
     * @throws ReflectionException If reflection fails
     */
    private function processAttributes(Model $model, Collection $types, array $customModelProps): void
    {
        $schemaColumns = $this->columnReader?->columns($model) ?? [];
        $casts = $model->getCasts();
        $keyName = $model->getKeyName();
        $hidden = $model->getHidden();
        $managed = array_filter([
            $model->usesTimestamps() ? $model->getCreatedAtColumn() : null,
            $model->usesTimestamps() ? $model->getUpdatedAtColumn() : null,
            method_exists($model, 'getDeletedAtColumn') ? $model->getDeletedAtColumn() : null,
        ]);

        $columns = $schemaColumns !== [] ? array_keys($schemaColumns) : $model->getFillable();

        foreach ($columns as $column) {
            // Skip the primary key — it was already added in extractTypes.
            if ($column === $keyName || in_array($column, $managed, true)) {
                continue;
            }

            if (in_array($column, $hidden, true) && ! isset($customModelProps[$column])) {
                continue;
            }

            $schemaColumn = $schemaColumns[$column] ?? null;
            $nullable = $schemaColumn !== null ? ['nullable' => $schemaColumn['nullable']] : [];

            // Check for custom property override first
            if (isset($customModelProps[$column])) {
                $customType = $customModelProps[$column];
                $types->push([
                    'field' => $column,
                    'type' => $customType,
                    'relation' => false,
                    'isImport' => is_string($customType) && str_starts_with($customType, '@/'),
                ]);

                continue;
            }

            $castType = $casts[$column] ?? null;
            $builtinCast = is_string($castType) ? ColumnTypeMapper::fromCast($castType) : null;
            // Only a cast that is not one of Laravel's built-in names can be a
            // class: `class_exists('datetime')` is true, PHP's DateTime.
            $castClass = is_string($castType) && $builtinCast === null ? explode(':', $castType, 2)[0] : null;

            // Enum casts serialize to their backing value (or case name).
            if ($castClass !== null && ($enum = EnumTypeResolver::toTypeScript($castClass)) !== null) {
                $types->push([
                    'field' => $column,
                    'type' => $castClass,
                    'relation' => false,
                    'tsType' => $enum,
                    ...$nullable,
                ]);

                continue;
            }

            // Check if it's a custom cast class
            if ($castClass !== null && class_exists($castClass)) {
                $castTypeInfo = $this->castTypeResolver->resolve($castClass, $column);
                if ($castTypeInfo !== null) {
                    $types->push([...$castTypeInfo, ...$nullable]);

                    continue;
                }

                // A class cast whose value could not be typed: whatever it
                // serializes to, it is not the raw column, so do not fall
                // back to the column type.
                $types->push([
                    'field' => $column,
                    'type' => $castClass,
                    'relation' => false,
                    'tsType' => 'unknown',
                    ...$nullable,
                ]);

                continue;
            }

            $tsType = $builtinCast
                ?? ($schemaColumn !== null
                    ? ColumnTypeMapper::fromColumn($schemaColumn['type_name'], $schemaColumn['type'])
                    : null);

            if ($tsType !== null) {
                $types->push([
                    'field' => $column,
                    'type' => $castType ?? $schemaColumn['type_name'] ?? 'string',
                    'relation' => false,
                    'tsType' => $tsType,
                    ...$nullable,
                ]);

                continue;
            }

            // Unknown cast and no schema: keep the legacy behaviour.
            $types->push([
                'field' => $column,
                'type' => $castType ?? 'string',
                'relation' => false,
                ...$nullable,
            ]);
        }
    }

    /**
     * Add timestamp fields to the types collection.
     *
     * Adds created_at and updated_at fields if they're not already present
     * (either from fillable or custom props).
     *
     * @param  Model  $model  The model instance
     * @param  Collection  $types  The types collection to add to
     * @param  array<string, string>  $customModelProps  Custom props for this model
     */
    private function addTimestamps(Model $model, Collection $types, array $customModelProps): void
    {
        $createdAtColumn = $model->getCreatedAtColumn();
        $updatedAtColumn = $model->getUpdatedAtColumn();

        // Add created_at if not already present (column may be null if const CREATED_AT = null)
        if ($createdAtColumn !== null && ! isset($customModelProps[$createdAtColumn]) && ! $types->contains('field', $createdAtColumn)) {
            $types->push([
                'field' => $createdAtColumn,
                'type' => 'string',
                'relation' => false,
                'nullable' => false,
                'isImport' => false,
            ]);
        }

        // Add updated_at if not already present (column may be null if const UPDATED_AT = null)
        if ($updatedAtColumn !== null && ! isset($customModelProps[$updatedAtColumn]) && ! $types->contains('field', $updatedAtColumn)) {
            $types->push([
                'field' => $updatedAtColumn,
                'type' => 'string',
                'relation' => false,
                'nullable' => false,
                'isImport' => false,
            ]);
        }
    }

    /**
     * Add the soft-delete timestamp field to the types collection.
     *
     * Only called when the model uses the SoftDeletes trait.
     * The deleted_at column is always nullable.
     *
     * @param  Model  $model  The model instance
     * @param  Collection  $types  The types collection to add to
     * @param  array<string, string>  $customModelProps  Custom props for this model
     */
    private function addSoftDeleteTimestamp(Model $model, Collection $types, array $customModelProps): void
    {
        $deletedAtColumn = $model->getDeletedAtColumn();

        if ($deletedAtColumn !== null
            && ! isset($customModelProps[$deletedAtColumn])
            && ! $types->contains('field', $deletedAtColumn)
        ) {
            $types->push([
                'field' => $deletedAtColumn,
                'type' => 'string',
                'relation' => false,
                'nullable' => true,
                'isImport' => false,
            ]);
        }
    }

    /**
     * Add appended attribute fields to the types collection.
     *
     * Reads $model->getAppends() and for each appended field, resolves the
     * TypeScript type by reflecting on the corresponding accessor method.
     *
     * @param  Model  $model  The model instance
     * @param  Collection  $types  The types collection to add to
     * @param  array<string, string>  $customModelProps  Custom props for this model
     */
    private function addAppends(Model $model, Collection $types, array $customModelProps): void
    {
        foreach ($model->getAppends() as $appendedField) {
            if ($types->contains('field', $appendedField) || isset($customModelProps[$appendedField])) {
                continue;
            }

            [$tsType, $nullable] = $this->resolveAppendType($model, $appendedField);

            $types->push([
                'field' => $appendedField,
                'type' => $tsType,
                'relation' => false,
                'nullable' => $nullable,
                'isImport' => false,
                'tsType' => $tsType,
            ]);
        }
    }

    /**
     * Resolve the TypeScript type for an appended attribute.
     *
     * Checks (in order):
     * 1. Old-style accessor: getFullNameAttribute() — uses reflection return type
     * 2. New-style accessor: fullName() returning Attribute — defaults to unknown
     * 3. Falls back to unknown
     *
     * @param  Model  $model  The model instance
     * @param  string  $field  The snake_case append field name
     * @return array{0: string, 1: bool} [tsType, nullable]
     */
    private function resolveAppendType(Model $model, string $field): array
    {
        // Old-style: full_name → getFullNameAttribute
        $oldStyle = 'get'.str_replace(' ', '', ucwords(str_replace('_', ' ', $field))).'Attribute';

        if (method_exists($model, $oldStyle)) {
            $reflection = new ReflectionMethod($model, $oldStyle);
            $returnType = $reflection->getReturnType();

            if ($returnType instanceof ReflectionNamedType) {
                $nullable = $returnType->allowsNull();
                $tsType = $this->phpToTsConverter->phpTypeToTypeScript($returnType->getName());

                return [$tsType, $nullable];
            }

            if ($returnType instanceof ReflectionUnionType) {
                $nullable = $returnType->allowsNull();
                $nonNull = array_filter($returnType->getTypes(), fn ($t) => $t->getName() !== 'null');
                $tsTypes = array_map(fn ($t) => $this->phpToTsConverter->phpTypeToTypeScript($t->getName()), $nonNull);

                return [implode(' | ', array_unique($tsTypes)), $nullable];
            }
        }

        // New-style: full_name → fullName (camelCase) returning Attribute
        $newStyle = lcfirst(str_replace(' ', '', ucwords(str_replace('_', ' ', $field))));

        if (method_exists($model, $newStyle)) {
            $reflection = new ReflectionMethod($model, $newStyle);
            $returnType = $reflection->getReturnType();

            if ($returnType instanceof ReflectionNamedType
                && is_a($returnType->getName(), Attribute::class, true)
            ) {
                return $this->resolveAttributeAccessorType($model, $reflection);
            }
        }

        return ['unknown', false];
    }

    /**
     * Resolve the getter type of a new-style `Attribute` accessor.
     *
     * Reads, in order, the `@return Attribute<TGet, TSet>` annotation — its
     * first argument is what the attribute serializes to — then the return type
     * of the getter closure itself. Falls back to `unknown`.
     *
     * @return array{0: string, 1: bool} [tsType, nullable]
     */
    private function resolveAttributeAccessorType(Model $model, ReflectionMethod $method): array
    {
        $doc = $method->getDocComment() ?: '';

        if (preg_match('/@return\s+\\\\?(?:[\w\\\\]*\\\\)?Attribute<(.+)>/', $doc, $match)) {
            $getter = $this->firstGenericArgument($match[1]);

            if ($getter !== '' && strtolower($getter) !== 'never') {
                $members = $this->phpToTsConverter->splitUnionType($getter);
                $nullable = in_array('null', array_map('strtolower', $members), true);

                return [$this->phpToTsConverter->convertPhpDocToTs($getter), $nullable];
            }
        }

        try {
            $method->setAccessible(true);
            $attribute = $method->invoke($model);
        } catch (\Throwable) {
            return ['unknown', false];
        }

        if (! $attribute instanceof Attribute || ! $attribute->get instanceof \Closure) {
            return ['unknown', false];
        }

        $returnType = (new ReflectionFunction($attribute->get))->getReturnType();

        if ($returnType instanceof ReflectionNamedType) {
            return [$this->phpToTsConverter->phpTypeToTypeScript($returnType->getName()), $returnType->allowsNull()];
        }

        if ($returnType instanceof ReflectionUnionType) {
            $nonNull = array_filter($returnType->getTypes(), fn ($t) => $t->getName() !== 'null');
            $tsTypes = array_map(fn ($t) => $this->phpToTsConverter->phpTypeToTypeScript($t->getName()), $nonNull);

            return [implode(' | ', array_unique($tsTypes)), $returnType->allowsNull()];
        }

        return ['unknown', false];
    }

    /**
     * The first top-level argument of a generic argument list:
     * `string|null, never` yields `string|null`.
     */
    private function firstGenericArgument(string $arguments): string
    {
        $depth = 0;

        foreach (str_split($arguments) as $index => $char) {
            if ($char === '<') {
                $depth++;
            } elseif ($char === '>') {
                $depth--;
            } elseif ($char === ',' && $depth === 0) {
                return trim(substr($arguments, 0, $index));
            }
        }

        return trim($arguments);
    }

    /**
     * Add relationship fields to the types collection.
     *
     * For each relationship:
     * - Converts camelCase method name to snake_case field name
     * - Adds the relationship field
     * - Optionally adds a _count field for collection relationships
     *
     * @param  Model  $model  The model instance
     * @param  Collection  $types  The types collection to add to
     *
     * @throws ReflectionException If reflection fails
     */
    private function addRelationships(Model $model, Collection $types): void
    {
        $relations = $this->relationshipResolver->resolveRelationships($model);

        foreach ($relations as $relation) {
            // Convert camelCase to snake_case for field name
            $relationData = [
                'field' => strtolower(preg_replace('/[A-Z]/', '_$0', lcfirst($relation['name']))),
                'type' => $relation['type'],
                'relation' => true,
                'model' => $relation['model'],
            ];

            if (isset($relation['pivot'])) {
                $relationData['pivot'] = $relation['pivot'];
            }

            $types->push($relationData);

            // Add count field for collection relationships
            if ($this->withCounts && $this->isCollectionRelationship($relation['type'])) {
                $types->push([
                    'field' => $relationData['field'].'_count',
                    'type' => 'number',
                    'relation' => false,
                ]);
            }
        }
    }

    /**
     * Check if a relationship type is a collection relationship.
     *
     * Collection relationships are those that return multiple models:
     * - HasMany
     * - BelongsToMany
     * - MorphMany
     * - MorphToMany
     *
     * @param  string  $relationType  The relationship type name
     * @return bool True if it's a collection relationship
     */
    private function isCollectionRelationship(string $relationType): bool
    {
        return in_array($relationType, ['HasMany', 'BelongsToMany', 'MorphMany', 'MorphToMany']);
    }

    /**
     * Add remaining custom props that weren't already added.
     *
     * Processes custom props that weren't covered by fillable or timestamps,
     * ensuring they're included in the type schema.
     *
     * @param  Collection  $types  The types collection to add to
     * @param  array<string, string>  $customModelProps  Custom props for this model
     */
    private function addRemainingCustomProps(Collection $types, array $customModelProps): void
    {
        foreach ($customModelProps as $field => $type) {
            if (! $types->contains('field', $field)) {
                $types->push([
                    'field' => $field,
                    'type' => $type,
                    'relation' => false,
                    'isImport' => is_string($type) && str_starts_with($type, '@/'),
                ]);
            }
        }
    }

    /**
     * Set custom property overrides.
     *
     * @param  array<string, array<string, string>|string>  $customProps  Custom property map
     */
    public function setCustomProps(array $customProps): void
    {
        $this->customProps = $customProps;
    }

    /**
     * Set whether to include count fields.
     *
     * @param  bool  $withCounts  Whether to include count fields
     */
    public function setWithCounts(bool $withCounts): void
    {
        $this->withCounts = $withCounts;
    }
}
