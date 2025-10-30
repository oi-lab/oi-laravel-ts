<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

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
     * 1. Discover all models
     * 2. Extract types for each model
     * 3. Apply model-specific custom props
     * 4. Apply global custom props (? wildcard)
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
        $models = $this->modelDiscovery->discoverModels();
        $schema = [];

        // Extract types for each model
        foreach ($models as $model) {
            $types = $this->typeExtractor->extractTypes($model['namespace']);
            $schema[$model['model']] = [
                'model' => $model['model'],
                'namespace' => $model['namespace'],
                'types' => $types,
            ];
        }

        // Apply global custom props (properties with ? prefix)
        $this->applyGlobalCustomProps($schema);

        // Apply model-specific custom props
        $this->applyModelSpecificCustomProps($schema);

        return $schema;
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
                if (! $data['types']->contains('field', $field)) {
                    $data['types']->push([
                        'field' => $field,
                        'type' => $value,
                        'relation' => false,
                        'isImport' => is_string($value) && str_starts_with($value, '@/'),
                    ]);
                }
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

            // Apply each custom prop for this model
            foreach ($value as $field => $type) {
                if (! $schema[$key]['types']->contains('field', $field)) {
                    $schema[$key]['types']->push([
                        'field' => $field,
                        'type' => $type,
                        'relation' => false,
                        'isImport' => is_string($type) && str_starts_with($type, '@/'),
                    ]);
                }
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
}
