<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use ReflectionException;

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
     * Create a new type extractor instance.
     *
     * @param  CastTypeResolver  $castTypeResolver  Resolver for custom cast types
     * @param  RelationshipResolver  $relationshipResolver  Resolver for model relationships
     * @param  array<string, array<string, string>|string>  $customProps  Custom property overrides
     * @param  bool  $withCounts  Whether to include relationship count fields
     */
    public function __construct(
        CastTypeResolver $castTypeResolver,
        RelationshipResolver $relationshipResolver,
        array $customProps = [],
        bool $withCounts = true
    ) {
        $this->castTypeResolver = $castTypeResolver;
        $this->relationshipResolver = $relationshipResolver;
        $this->customProps = $customProps;
        $this->withCounts = $withCounts;
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

        // 1. Add primary key
        $types->push([
            'field' => $model->getKeyName(),
            'type' => 'number',
            'relation' => false,
        ]);

        // 2. Process fillable attributes
        $this->processFillableAttributes($model, $types, $customModelProps);

        // 3. Add timestamps
        if ($model->timestamps) {
            $this->addTimestamps($model, $types, $customModelProps);
        }

        // 4. Add relationships
        $this->addRelationships($model, $types);

        // 5. Add remaining custom props
        $this->addRemainingCustomProps($types, $customModelProps);

        return $types;
    }

    /**
     * Process fillable attributes and add them to the types collection.
     *
     * For each fillable attribute:
     * - Checks for custom property override
     * - Checks for custom cast type
     * - Falls back to cast type from model
     *
     * @param  Model  $model  The model instance
     * @param  Collection  $types  The types collection to add to
     * @param  array<string, string>  $customModelProps  Custom props for this model
     *
     * @throws ReflectionException If reflection fails
     */
    private function processFillableAttributes(Model $model, Collection $types, array $customModelProps): void
    {
        $columns = $model->getFillable();
        $casts = $model->getCasts();

        foreach ($columns as $column) {
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

            $castType = $casts[$column] ?? 'string';

            // Check if it's a custom cast class
            if (is_string($castType) && class_exists($castType)) {
                $castTypeInfo = $this->castTypeResolver->resolve($castType, $column);
                if ($castTypeInfo !== null) {
                    $types->push($castTypeInfo);

                    continue;
                }
            }

            // Standard cast type
            $types->push([
                'field' => $column,
                'type' => $castType,
                'relation' => false,
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

        // Add created_at if not already present
        if (! isset($customModelProps[$createdAtColumn]) && ! $types->contains('field', $createdAtColumn)) {
            $types->push([
                'field' => $createdAtColumn,
                'type' => 'string',
                'relation' => false,
                'nullable' => false,
                'isImport' => false,
            ]);
        }

        // Add updated_at if not already present
        if (! isset($customModelProps[$updatedAtColumn]) && ! $types->contains('field', $updatedAtColumn)) {
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
