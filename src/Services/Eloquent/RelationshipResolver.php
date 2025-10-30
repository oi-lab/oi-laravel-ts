<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\Relation;
use ReflectionException;
use ReflectionMethod;

/**
 * Relationship Resolver
 *
 * Resolves Eloquent model relationships to extract metadata for TypeScript generation.
 * Analyzes model methods to identify relationship definitions and extracts:
 * - Relationship type (HasMany, BelongsTo, etc.)
 * - Related model class
 * - Pivot table information (for BelongsToMany)
 *
 * Only methods that return Eloquent Relation instances are considered relationships.
 *
 *
 * @example
 * ```php
 * // For a User model with:
 * // public function posts(): HasMany { return $this->hasMany(Post::class); }
 * // public function roles(): BelongsToMany { return $this->belongsToMany(Role::class); }
 *
 * $resolver = new RelationshipResolver();
 * $relations = $resolver->resolveRelationships($userModel);
 * // Returns:
 * // [
 * //   ['name' => 'posts', 'type' => 'HasMany', 'model' => 'App\Models\Post'],
 * //   ['name' => 'roles', 'type' => 'BelongsToMany', 'model' => 'App\Models\Role', 'pivot' => [...]],
 * // ]
 * ```
 */
class RelationshipResolver
{
    /**
     * Resolve all relationships for a given model.
     *
     * Scans all public methods on the model that:
     * 1. Are not inherited from base Eloquent Model class
     * 2. Have a return type that extends Illuminate\Database\Eloquent\Relations\Relation
     *
     * For each relationship found, extracts metadata including relationship type,
     * related model, and pivot information if applicable.
     *
     * @param  Model  $model  The model instance to analyze
     * @return array<int, array{
     *   name: string,
     *   type: string,
     *   model: class-string,
     *   pivot?: array{accessor: string, class: class-string, columns: array<string>}
     * }> Array of relationship metadata
     *
     * @throws ReflectionException If reflection fails
     *
     * @example
     * ```php
     * $user = new User();
     * $relations = $resolver->resolveRelationships($user);
     * // [
     * //   [
     * //     'name' => 'posts',
     * //     'type' => 'HasMany',
     * //     'model' => 'App\Models\Post'
     * //   ],
     * //   [
     * //     'name' => 'roles',
     * //     'type' => 'BelongsToMany',
     * //     'model' => 'App\Models\Role',
     * //     'pivot' => [
     * //       'accessor' => 'pivot',
     * //       'class' => 'Illuminate\Database\Eloquent\Relations\Pivot',
     * //       'columns' => ['role_id', 'user_id', 'created_at']
     * //     ]
     * //   ]
     * // ]
     * ```
     */
    public function resolveRelationships(Model $model): array
    {
        $relations = [];
        $methods = get_class_methods($model);

        foreach ($methods as $method) {
            // Skip methods inherited from base Model class
            if (method_exists('\\Illuminate\\Database\\Eloquent\\Model', $method)) {
                continue;
            }

            $reflection = new ReflectionMethod($model, $method);

            // Check if method has a return type that is a Relation
            if (! $this->isRelationshipMethod($reflection)) {
                continue;
            }

            // Execute the relationship method to get metadata
            $relationData = $this->extractRelationshipData($model, $method);

            if ($relationData !== null) {
                $relations[] = $relationData;
            }
        }

        return $relations;
    }

    /**
     * Check if a method is a relationship method.
     *
     * A method is considered a relationship method if it has a return type
     * that extends Illuminate\Database\Eloquent\Relations\Relation.
     *
     * @param  ReflectionMethod  $reflection  The method to check
     * @return bool True if the method returns a Relation
     */
    private function isRelationshipMethod(ReflectionMethod $reflection): bool
    {
        $returnType = $reflection->getReturnType();

        if ($returnType === null) {
            return false;
        }

        if (! $returnType instanceof \ReflectionNamedType) {
            return false;
        }

        $returnTypeName = $returnType->getName();

        return is_subclass_of($returnTypeName, '\\Illuminate\\Database\\Eloquent\\Relations\\Relation');
    }

    /**
     * Extract relationship data from a model method.
     *
     * Executes the relationship method to get the actual Relation instance,
     * then extracts metadata including type and related model.
     *
     * For BelongsToMany relationships, also extracts pivot table information.
     *
     * @param  Model  $model  The model instance
     * @param  string  $methodName  The relationship method name
     * @return array{
     *   name: string,
     *   type: string,
     *   model: class-string,
     *   pivot?: array{accessor: string, class: class-string, columns: array<string>}
     * }|null Relationship data, or null if extraction fails
     */
    private function extractRelationshipData(Model $model, string $methodName): ?array
    {
        try {
            $relation = $model->$methodName();

            if (! $relation instanceof Relation) {
                return null;
            }

            $relationType = class_basename(get_class($relation));
            $relatedModel = get_class($relation->getRelated());

            $relationData = [
                'name' => $methodName,
                'type' => $relationType,
                'model' => $relatedModel,
            ];

            // Extract pivot information for BelongsToMany relationships
            if (method_exists($relation, 'getPivotAccessor')) {
                $pivotInfo = $this->extractPivotInformation($relation);
                if ($pivotInfo !== null) {
                    $relationData['pivot'] = $pivotInfo;
                }
            }

            return $relationData;
        } catch (\Throwable $e) {
            // If relationship method fails (e.g., database not available), skip it
            return null;
        }
    }

    /**
     * Extract pivot table information from a BelongsToMany relationship.
     *
     * Gets the pivot accessor name, pivot class, and fillable columns
     * from the pivot model.
     *
     * @param  Relation  $relation  The BelongsToMany relation instance
     * @return array{accessor: string, class: class-string, columns: array<string>}|null Pivot info, or null if unavailable
     */
    private function extractPivotInformation(Relation $relation): ?array
    {
        $pivotAccessor = $relation->getPivotAccessor();
        $pivotClass = $relation->getPivotClass();

        if (! $pivotClass) {
            return null;
        }

        try {
            $pivotModel = new $pivotClass;
            $pivotColumns = $pivotModel->getFillable();

            return [
                'accessor' => $pivotAccessor,
                'class' => $pivotClass,
                'columns' => $pivotColumns,
            ];
        } catch (\Throwable $e) {
            return null;
        }
    }
}
