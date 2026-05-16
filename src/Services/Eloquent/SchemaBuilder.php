<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Database\Eloquent\Relations\MorphPivot;
use Illuminate\Database\Eloquent\Relations\Pivot;
use Illuminate\Support\Collection;

/**
 * Schema Builder
 *
 * Main orchestrator for building the complete TypeScript schema from Laravel models.
 * Coordinates the model discovery, type extraction, and custom property application
 * to generate a comprehensive schema that can be converted to TypeScript.
 *
 * The schema builder:
 * - Discovers all models in the application
 * - Extracts type information for each model
 * - Recursively discovers models referenced by relationships, including
 *   relationships provided by traits (e.g. spatie/laravel-permission's HasRoles)
 * - Applies custom property overrides
 * - Processes global custom properties (with ? wildcard)
 * - Generates a structured schema ready for TypeScript conversion
 *
 *
 * @example
 * ```php
 * $builder = new SchemaBuilder(
 *     new ModelDiscovery(),
 *     new TypeExtractor(...)
 * );
 *
 * $builder->setAdditionalModels([CustomModel::class]);
 * $builder->setCustomProps(['User' => ['role' => 'UserRole']]);
 *
 * $schema = $builder->buildSchema();
 * // Returns complete schema for all models
 * ```
 */
class SchemaBuilder
{
    /**
     * Model discovery service instance.
     */
    private ModelDiscovery $modelDiscovery;

    /**
     * Type extractor instance.
     */
    private TypeExtractor $typeExtractor;

    /**
     * Custom property type overrides.
     *
     * Supports both model-specific and global overrides:
     * - Model-specific: ['User' => ['role' => 'UserRole']]
     * - Global (with ?): ['?status' => 'Status'] applies to all models
     *
     * @var array<string, array<string, string>|string>
     */
    private array $customProps = [];

    /**
     * Whether to recursively discover models referenced by relationships.
     *
     * When enabled, any model targeted by a relationship (and any custom Pivot
     * model) is added to the schema even if it lives outside app/Models and was
     * not listed explicitly. This ensures interfaces such as IRole are generated
     * for relationships brought in by traits (e.g. spatie/laravel-permission).
     */
    private bool $discoverRelatedModels = true;

    /**
     * Create a new schema builder instance.
     *
     * @param  ModelDiscovery  $modelDiscovery  Service for discovering models
     * @param  TypeExtractor  $typeExtractor  Service for extracting type information
     */
    public function __construct(
        ModelDiscovery $modelDiscovery,
        TypeExtractor $typeExtractor
    ) {
        $this->modelDiscovery = $modelDiscovery;
        $this->typeExtractor = $typeExtractor;
    }

    /**
     * Build the complete schema for all models.
     *
     * The schema includes:
     * - Model name and namespace
     * - All field types (attributes, relationships, custom props)
     * - DataObject definitions
     * - Import information
     *
     * Process flow:
     * 1. Discover all explicitly known models (app/Models + additional models)
     * 2. Extract types for each model
     * 3. Follow relationships to discover and extract referenced models
     * 4. Apply global custom props (? wildcard)
     * 5. Apply model-specific custom props
     *
     * @return array<string, array{
     *   model: string,
     *   namespace: class-string,
     *   types: \Illuminate\Support\Collection
     * }> Complete schema indexed by model name
     *
     * @throws \ReflectionException If reflection fails during type extraction
     *
     * @example
     * ```php
     * $schema = $builder->buildSchema();
     * // [
     * //   'User' => [
     * //     'model' => 'User',
     * //     'namespace' => 'App\Models\User',
     * //     'types' => Collection [
     * //       ['field' => 'id', 'type' => 'number', 'relation' => false],
     * //       ['field' => 'name', 'type' => 'string', 'relation' => false],
     * //       ...
     * //     ]
     * //   ],
     * //   'Post' => [...]
     * // ]
     * ```
     */
    public function buildSchema(): array
    {
        $schema = [];
        $processed = [];

        // Seed the queue with explicitly known models (app/Models + additional).
        // The 'auto' flag marks whether a model was discovered through a
        // relationship, in which case extraction failures degrade gracefully.
        $queue = array_map(
            fn (array $model): array => [...$model, 'auto' => false],
            $this->modelDiscovery->discoverModels()
        );

        while ($queue !== []) {
            $entry = array_shift($queue);
            $namespace = ltrim($entry['namespace'], '\\');

            // Skip classes already considered (cycle guard).
            if (isset($processed[$namespace])) {
                continue;
            }
            $processed[$namespace] = true;

            // Keep the first model registered under a given short name so that
            // explicitly listed models take precedence over discovered ones.
            if (isset($schema[$entry['model']])) {
                continue;
            }

            $types = $this->extractModelTypes($namespace, $entry['auto']);

            if ($types === null) {
                continue;
            }

            $schema[$entry['model']] = [
                'model' => $entry['model'],
                'namespace' => $namespace,
                'types' => $types,
            ];

            // Follow relationships to discover models that are not listed
            // explicitly (e.g. package models attached through traits).
            if ($this->discoverRelatedModels) {
                foreach ($this->collectRelatedClasses($types) as $relatedClass) {
                    $queue[] = [
                        'model' => class_basename($relatedClass),
                        'namespace' => $relatedClass,
                        'auto' => true,
                    ];
                }
            }
        }

        // Apply global custom props (properties with ? prefix)
        $this->applyGlobalCustomProps($schema);

        // Apply model-specific custom props
        $this->applyModelSpecificCustomProps($schema);

        return $schema;
    }

    /**
     * Extract the type collection for a single model class.
     *
     * Models discovered through relationships ($auto === true) may live in
     * third-party packages and fail to instantiate; in that case extraction
     * degrades gracefully by returning null instead of aborting generation.
     *
     * @param  class-string  $modelClass  The fully qualified model class name
     * @param  bool  $auto  Whether the model was discovered via a relationship
     * @return Collection<int, array{field: string, type: string, relation: bool}>|null
     *
     * @throws \ReflectionException If reflection fails for an explicit model
     */
    private function extractModelTypes(string $modelClass, bool $auto): ?Collection
    {
        if (! $auto) {
            return $this->typeExtractor->extractTypes($modelClass);
        }

        if (! class_exists($modelClass)) {
            return null;
        }

        try {
            return $this->typeExtractor->extractTypes($modelClass);
        } catch (\Throwable) {
            return null;
        }
    }

    /**
     * Collect the related model classes referenced by a model's relationships.
     *
     * Returns both the target model of each relationship and any custom Pivot
     * model used by a BelongsToMany/MorphToMany relationship. The default
     * Illuminate Pivot/MorphPivot classes are excluded since they have no
     * generated interface.
     *
     * @param  Collection<int, array<string, mixed>>  $types  The model's type collection
     * @return array<int, class-string> Unique related class names
     */
    private function collectRelatedClasses(Collection $types): array
    {
        $classes = [];

        foreach ($types as $type) {
            if (empty($type['relation']) || empty($type['model'])) {
                continue;
            }

            $classes[] = ltrim((string) $type['model'], '\\');

            $pivotClass = $type['pivot']['class'] ?? null;
            if (is_string($pivotClass) && $this->isCustomPivotClass($pivotClass)) {
                $classes[] = ltrim($pivotClass, '\\');
            }
        }

        return array_values(array_unique($classes));
    }

    /**
     * Determine whether a pivot class is a custom Pivot model.
     *
     * The base Illuminate Pivot and MorphPivot classes are not real models in
     * the schema and must not be followed for discovery.
     *
     * @param  class-string  $pivotClass  The pivot class used by the relation
     * @return bool True when a custom Pivot model is in use
     */
    private function isCustomPivotClass(string $pivotClass): bool
    {
        return ! in_array(ltrim($pivotClass, '\\'), [
            Pivot::class,
            MorphPivot::class,
        ], true);
    }

    /**
     * Apply global custom properties to all models.
     *
     * Global properties are defined with a ? prefix in the customProps array.
     * For example: ['?status' => 'Status'] will add a 'status' field with type 'Status'
     * to all models that don't already have that field.
     *
     * @param  array<string, array{model: string, namespace: string, types: \Illuminate\Support\Collection}>  $schema  The schema to modify
     */
    private function applyGlobalCustomProps(array &$schema): void
    {
        foreach ($this->customProps as $key => $value) {
            if (! str_contains($key, '?')) {
                continue;
            }

            $field = str_replace('?', '', $key);

            foreach ($schema as &$data) {
                $data['types'] = $data['types']->reject(fn ($type) => $type['field'] === $field)->values();
                $data['types']->push([
                    'field' => $field,
                    'type' => $value,
                    'relation' => false,
                    'isImport' => is_string($value) && str_starts_with($value, '@/'),
                ]);
            }
        }
    }

    /**
     * Apply model-specific custom properties.
     *
     * Processes custom properties defined for specific models.
     * Only applies to models that exist in the schema.
     *
     * @param  array<string, array{model: string, namespace: string, types: \Illuminate\Support\Collection}>  $schema  The schema to modify
     */
    private function applyModelSpecificCustomProps(array &$schema): void
    {
        foreach ($this->customProps as $key => $value) {
            // Skip global props (already processed)
            if (str_contains($key, '?')) {
                continue;
            }

            // Skip if model doesn't exist in schema
            if (! isset($schema[$key])) {
                continue;
            }

            // Apply each custom prop for this model, overwriting existing fields
            foreach ($value as $field => $type) {
                $schema[$key]['types'] = $schema[$key]['types']->reject(fn ($t) => $t['field'] === $field)->values();
                $schema[$key]['types']->push([
                    'field' => $field,
                    'type' => $type,
                    'relation' => false,
                    'isImport' => is_string($type) && str_starts_with($type, '@/'),
                ]);
            }
        }
    }

    /**
     * Set additional models to include in the schema.
     *
     * These models are included in addition to those discovered in app/Models.
     *
     * @param  array<int, class-string>  $models  Array of fully qualified model class names
     */
    public function setAdditionalModels(array $models): void
    {
        $this->modelDiscovery->setAdditionalModels($models);
    }

    /**
     * Set custom property type overrides.
     *
     * Supports two formats:
     * 1. Model-specific: ['User' => ['role' => 'UserRole', 'status' => 'Status']]
     * 2. Global (with ?): ['?created_by_id' => 'number']
     *
     * @param  array<string, array<string, string>|string>  $props  Custom property map
     *
     * @example
     * ```php
     * // Model-specific custom props
     * $builder->setCustomProps([
     *     'User' => [
     *         'role' => 'UserRole',
     *         'permissions' => 'Permission[]'
     *     ]
     * ]);
     *
     * // Global custom prop (applies to all models)
     * $builder->setCustomProps([
     *     '?status' => 'Status'
     * ]);
     * ```
     */
    public function setCustomProps(array $props): void
    {
        $this->customProps = $props;
        $this->typeExtractor->setCustomProps($props);
    }

    /**
     * Set whether to include count fields for relationships.
     *
     * When enabled, adds {relation}_count fields for collection relationships.
     *
     * @param  bool  $withCounts  Whether to include count fields
     */
    public function setWithCounts(bool $withCounts): void
    {
        $this->typeExtractor->setWithCounts($withCounts);
    }

    /**
     * Set whether to recursively discover models referenced by relationships.
     *
     * When enabled (default), models targeted by a relationship - including
     * relationships brought in by traits - are added to the schema so their
     * interfaces are generated even when they live outside app/Models.
     *
     * @param  bool  $discoverRelatedModels  Whether to follow relationships
     */
    public function setDiscoverRelatedModels(bool $discoverRelatedModels): void
    {
        $this->discoverRelatedModels = $discoverRelatedModels;
    }

    /**
     * Get the model discovery instance.
     *
     * @return ModelDiscovery The model discovery service
     */
    public function getModelDiscovery(): ModelDiscovery
    {
        return $this->modelDiscovery;
    }

    /**
     * Get the type extractor instance.
     *
     * @return TypeExtractor The type extractor service
     */
    public function getTypeExtractor(): TypeExtractor
    {
        return $this->typeExtractor;
    }

    /**
     * Get the custom properties configuration.
     *
     * @return array<string, array<string, string>|string> The custom properties map
     */
    public function getCustomProps(): array
    {
        return $this->customProps;
    }

    /**
     * Get whether related-model discovery is enabled.
     *
     * @return bool True when relationships are followed to discover models
     */
    public function getDiscoverRelatedModels(): bool
    {
        return $this->discoverRelatedModels;
    }
}
