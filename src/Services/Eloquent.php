<?php

namespace OiLab\OiLaravelTs\Services;

use OiLab\OiLaravelTs\Services\Eloquent\CastTypeResolver;
use OiLab\OiLaravelTs\Services\Eloquent\DataObjectAnalyzer;
use OiLab\OiLaravelTs\Services\Eloquent\ModelDiscovery;
use OiLab\OiLaravelTs\Services\Eloquent\PhpToTypeScriptConverter;
use OiLab\OiLaravelTs\Services\Eloquent\RelationshipResolver;
use OiLab\OiLaravelTs\Services\Eloquent\SchemaBuilder;
use OiLab\OiLaravelTs\Services\Eloquent\TypeExtractor;
use Illuminate\Support\Collection;

/**
 * Eloquent Schema Service
 *
 * Main facade for extracting TypeScript type information from Laravel Eloquent models.
 * This service coordinates specialized components to build a complete schema that can
 * be converted to TypeScript interfaces.
 *
 * The service provides:
 * - Model discovery (app/Models + additional models)
 * - Type extraction (attributes, casts, timestamps, relationships)
 * - Custom property overrides
 * - DataObject support
 * - Relationship count fields
 *
 * Configuration methods (static):
 * - setAdditionalModels() - Add models outside app/Models
 * - setCustomProps() - Override/add custom property types
 * - setWithCounts() - Enable/disable relationship count fields
 *
 *
 * @example
 * ```php
 * // Basic usage
 * $schema = Eloquent::getSchema();
 *
 * // With configuration
 * Eloquent::setAdditionalModels([CustomModel::class]);
 * Eloquent::setCustomProps([
 *     'User' => ['role' => 'UserRole'],
 *     '?status' => 'Status' // Global prop for all models
 * ]);
 * Eloquent::setWithCounts(true);
 * $schema = Eloquent::getSchema();
 * ```
 */
class Eloquent
{
    /**
     * Additional models to include beyond app/Models.
     *
     * @var array<int, class-string>
     */
    private static array $additionalModels = [];

    /**
     * Whether to include count fields for relationships.
     */
    private static bool $withCounts = true;

    /**
     * Custom property type overrides.
     *
     * Supports model-specific and global (with ?) overrides.
     *
     * @var array<string, array<string, string>|string>
     */
    private static array $customProps = [];

    /**
     * Set additional model classes to include in the schema.
     *
     * These models are included in addition to those discovered in app/Models.
     * Useful for package models or models in non-standard locations.
     *
     * @param  array<int, class-string>  $models  Array of fully qualified model class names
     *
     * @example
     * ```php
     * Eloquent::setAdditionalModels([
     *     \Vendor\Package\Models\CustomModel::class,
     *     \App\Legacy\OldModel::class,
     * ]);
     * ```
     */
    public static function setAdditionalModels(array $models): void
    {
        self::$additionalModels = $models;
    }

    /**
     * Set custom property type overrides for models.
     *
     * Allows you to override or add custom types for model properties.
     * Supports two formats:
     *
     * 1. Model-specific props:
     *    ['ModelName' => ['field' => 'TypeScriptType']]
     *
     * 2. Global props (applies to all models):
     *    ['?field' => 'TypeScriptType']
     *
     * @param  array<string, array<string, string>|string>  $props  Custom property map
     *
     * @example
     * ```php
     * Eloquent::setCustomProps([
     *     // Model-specific custom props
     *     'User' => [
     *         'role' => 'UserRole',
     *         'permissions' => 'Permission[]',
     *         'metadata' => '@/types/UserMetadata'
     *     ],
     *     // Global prop for all models
     *     '?status' => 'Status',
     *     '?created_by_id' => 'number'
     * ]);
     * ```
     */
    public static function setCustomProps(array $props): void
    {
        self::$customProps = $props;
    }

    /**
     * Set whether to include count fields for relationships.
     *
     * When enabled, generates {relation}_count fields for collection relationships:
     * - HasMany
     * - BelongsToMany
     * - MorphMany
     * - MorphToMany
     *
     * @param  bool  $withCounts  Whether to include count fields
     *
     * @example
     * ```php
     * // Enable relationship counts (default)
     * Eloquent::setWithCounts(true);
     * // User interface will have: posts, posts_count, roles, roles_count
     *
     * // Disable relationship counts
     * Eloquent::setWithCounts(false);
     * // User interface will have: posts, roles (no _count fields)
     * ```
     */
    public static function setWithCounts(bool $withCounts): void
    {
        self::$withCounts = $withCounts;
    }

    /**
     * Get the complete schema for all models.
     *
     * Builds a comprehensive schema that includes all discovered models with their:
     * - Model name and namespace
     * - Field types (attributes, casts, relationships)
     * - Custom property overrides
     * - DataObject information
     *
     * The returned schema is ready to be passed to the Convert service
     * for TypeScript generation.
     *
     * @return array<string, array{
     *   model: string,
     *   namespace: class-string,
     *   types: Collection<int, array{
     *     field: string,
     *     type: string,
     *     relation: bool,
     *     nullable?: bool,
     *     isImport?: bool,
     *     isDataObject?: bool,
     *     dataObjectClass?: class-string,
     *     properties?: array,
     *     isArray?: bool,
     *     model?: class-string,
     *     pivot?: array
     *   }>
     * }> Complete schema indexed by model name
     *
     * @throws \ReflectionException If reflection fails during processing
     *
     * @example
     * ```php
     * $schema = Eloquent::getSchema();
     * // [
     * //   'User' => [
     * //     'model' => 'User',
     * //     'namespace' => 'App\Models\User',
     * //     'types' => Collection [...]
     * //   ],
     * //   'Post' => [...]
     * // ]
     *
     * // Use with Convert service
     * $converter = new Convert($schema, withJsonLd: true);
     * $converter->generateFile(resource_path('js/types/models.ts'));
     * ```
     */
    public static function getSchema(): array
    {
        $schemaBuilder = self::createSchemaBuilder();

        return $schemaBuilder->buildSchema();
    }

    /**
     * Get all discovered models.
     *
     * Returns metadata for all models found in app/Models directory
     * plus any additional models configured via setAdditionalModels().
     *
     * @return array<int, array{model: string, namespace: class-string}> Array of model metadata
     *
     * @example
     * ```php
     * $models = Eloquent::getModels();
     * // [
     * //   ['model' => 'User', 'namespace' => 'App\Models\User'],
     * //   ['model' => 'Post', 'namespace' => 'App\Models\Post'],
     * // ]
     * ```
     */
    public static function getModels(): array
    {
        $discovery = new ModelDiscovery;
        $discovery->setAdditionalModels(self::$additionalModels);

        return $discovery->discoverModels();
    }

    /**
     * Get type information for a specific model class.
     *
     * Extracts complete type information for a single model including:
     * - Primary key
     * - Fillable attributes
     * - Cast types
     * - Timestamps
     * - Relationships
     * - Custom properties
     *
     * @param  class-string  $modelClass  Fully qualified model class name
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
     * @throws \ReflectionException If reflection fails
     *
     * @example
     * ```php
     * use App\Models\User;
     *
     * $types = Eloquent::getTypes(User::class);
     * // Collection [
     * //   ['field' => 'id', 'type' => 'number', 'relation' => false],
     * //   ['field' => 'name', 'type' => 'string', 'relation' => false],
     * //   ['field' => 'posts', 'type' => 'HasMany', 'relation' => true],
     * // ]
     * ```
     */
    public static function getTypes(string $modelClass): Collection
    {
        $typeExtractor = self::createTypeExtractor();

        return $typeExtractor->extractTypes($modelClass);
    }

    /**
     * Create and configure a schema builder instance.
     *
     * Internal method that assembles all required components and applies
     * current configuration settings.
     *
     * @return SchemaBuilder Configured schema builder
     */
    private static function createSchemaBuilder(): SchemaBuilder
    {
        $discovery = new ModelDiscovery;
        $discovery->setAdditionalModels(self::$additionalModels);

        $typeExtractor = self::createTypeExtractor();

        $builder = new SchemaBuilder($discovery, $typeExtractor);
        $builder->setCustomProps(self::$customProps);
        $builder->setWithCounts(self::$withCounts);

        return $builder;
    }

    /**
     * Create and configure a type extractor instance.
     *
     * Internal method that assembles the type extraction pipeline with
     * all required dependencies.
     *
     * @return TypeExtractor Configured type extractor
     */
    private static function createTypeExtractor(): TypeExtractor
    {
        $typeConverter = new PhpToTypeScriptConverter;
        $dataObjectAnalyzer = new DataObjectAnalyzer($typeConverter);
        $castTypeResolver = new CastTypeResolver($dataObjectAnalyzer);
        $relationshipResolver = new RelationshipResolver;

        $typeExtractor = new TypeExtractor(
            $castTypeResolver,
            $relationshipResolver,
            self::$customProps,
            self::$withCounts
        );

        return $typeExtractor;
    }
}
