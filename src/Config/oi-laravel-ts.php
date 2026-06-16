<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Output Path
    |--------------------------------------------------------------------------
    |
    | The path where the TypeScript interfaces file will be generated.
    | Default: resource_path('js/types/interfaces.ts')
    |
    */
    'output_path' => resource_path('js/types/interfaces.ts'),

    /*
    |--------------------------------------------------------------------------
    | Output Mode
    |--------------------------------------------------------------------------
    |
    | How the generated interfaces are written:
    | - 'single'   : one concatenated file at `output_path` (default).
    | - 'multiple' : one kebab-cased file per interface plus an `index.ts`
    |                barrel, written to `output_dir`. Each file imports exactly
    |                the interfaces it references.
    |
    */
    'output_mode' => 'single',

    /*
    |--------------------------------------------------------------------------
    | Output Directory
    |--------------------------------------------------------------------------
    |
    | Target directory for the generated files when `output_mode` is 'multiple'.
    | Ignored in 'single' mode (which uses `output_path`).
    |
    */
    'output_dir' => resource_path('js/types'),

    /*
    |--------------------------------------------------------------------------
    | Barrel File
    |--------------------------------------------------------------------------
    |
    | Name of the barrel file generated in 'multiple' mode that re-exports every
    | interface. Defaults to `index.ts`. Override when `index.ts` is already used
    | by your project (e.g. `'barrel_file' => 'interfaces.ts'`).
    | Ignored in 'single' mode.
    |
    */
    'barrel_file' => 'index.ts',

    /*
    |--------------------------------------------------------------------------
    | Include Relationship Counts
    |--------------------------------------------------------------------------
    |
    | Whether to include _count fields for relationships (HasMany, BelongsToMany, etc.)
    |
    */
    'with_counts' => true,

    /*
    |--------------------------------------------------------------------------
    | Enable JSON-LD Support
    |--------------------------------------------------------------------------
    |
    | Whether to include JsonLdRawNode interface for JSON-LD support
    |
    */
    'with_json_ld' => false,

    /*
    |--------------------------------------------------------------------------
    | Discover Related Models
    |--------------------------------------------------------------------------
    |
    | When enabled, any model targeted by a relationship is added to the schema
    | even if it lives outside app/Models. This ensures interfaces are generated
    | for models attached through traits (e.g. spatie/laravel-permission's Role
    | reachable via the HasRoles trait), so generated relationship types such as
    | `roles?: IRole[]` always reference a defined interface.
    |
    */
    'discover_related_models' => true,

    /*
    |--------------------------------------------------------------------------
    | Save Schema
    |--------------------------------------------------------------------------
    |
    | Whether to save the intermediate schema.json file for debugging
    |
    */
    'save_schema' => false,

    /*
    |--------------------------------------------------------------------------
    | Props With Types
    |--------------------------------------------------------------------------
    |
    | Define specific types for model properties
    |
    */
    'props_with_types' => [
        // Example:
        // 'User' => [
        //     'email' => 'string',
        // ],
    ],

    /*
    |--------------------------------------------------------------------------
    | DataObject Namespaces
    |--------------------------------------------------------------------------
    |
    | Namespaces to search when resolving short DataObject / ValueObject class
    | names (e.g. references found in PHPDoc like `array<int, Address>`).
    |
    | A class is considered a DataObject when it exposes both `fromArray()` and
    | `toArray()` methods. The list is iterated in order; the first match wins.
    |
    */
    'dataobject_namespaces' => [
        'App\\DataObjects',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Namespaces (spatie/laravel-data style DTOs)
    |--------------------------------------------------------------------------
    |
    | Namespaces holding Data Transfer Objects (DTOs) such as those built on
    | spatie/laravel-data. Every class found under these namespaces that has a
    | constructor with promoted properties is emitted as an `I{ClassName}`
    | interface (e.g. `App\Data\Knowledge\KnowledgeData` => `IKnowledgeData`).
    |
    | Detection is structural — no dependency on spatie/laravel-data is
    | required. Property names are kept verbatim (camelCase), backed enums are
    | emitted as literal unions, nested DTOs as `I{Name}`, and typed arrays
    | declared through a property `@var Foo[]` annotation as `IFoo[]`.
    |
    | This is fully opt-in: an empty list keeps the previous behavior unchanged.
    | The `dataobject_namespaces` key above is a distinct, untouched mechanism
    | for value objects resolved by short name (the `fromArray()`/`toArray()`
    | contract).
    |
    */
    'data_namespaces' => [
        // 'App\\Data',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Replaces Model
    |--------------------------------------------------------------------------
    |
    | When false (default), DTO interfaces are emitted *in addition* to the
    | Eloquent model interfaces — `IKnowledge` (model) and `IKnowledgeData`
    | (DTO) coexist.
    |
    | When true, any model that is mapped to a DTO no longer emits its Eloquent
    | `I{Model}` interface: the DTO becomes the single source of truth for that
    | model's shape. The model is identified from the first parameter of the
    | DTO's `fromModel()` factory, or from `data_for_model` below.
    |
    | Note: with this enabled, a relationship on another model that points to a
    | replaced model will reference an interface that is no longer generated.
    |
    */
    'data_replaces_model' => false,

    /*
    |--------------------------------------------------------------------------
    | Data For Model (explicit DTO <=> model mapping)
    |--------------------------------------------------------------------------
    |
    | Explicit overrides used to associate a DTO with its model when the DTO has
    | no `fromModel(Model $m)` factory to introspect, or to force a specific
    | pairing. Keyed by model class, valued by DTO class.
    |
    */
    'data_for_model' => [
        // App\Models\Knowledge::class => App\Data\Knowledge\KnowledgeData::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Discover All DataObjects
    |--------------------------------------------------------------------------
    |
    | When enabled, every DataObject found under `dataobject_namespaces` is
    | emitted as an `I{Name}` interface, even if it is never referenced by a
    | model cast. Nested DataObjects are resolved automatically.
    |
    | Two distinct classes resolving to the same short name throw a
    | DataObjectNameCollisionException. Defaults to false (no behavior change).
    |
    */
    'discover_all_dataobjects' => false,

    /*
    |--------------------------------------------------------------------------
    | Excluded Namespaces
    |--------------------------------------------------------------------------
    |
    | Models whose fully-qualified class name begins with one of these namespace
    | prefixes are excluded entirely from the generated schema — even when they
    | are discovered through a relationship.
    |
    */
    'excluded_namespaces' => [
        // Example:
        // 'OiLab\\Prestashop\\Models',
    ],

    /*
    |--------------------------------------------------------------------------
    | Extended Namespaces
    |--------------------------------------------------------------------------
    |
    | Models in these namespaces do not generate standalone interfaces. Instead,
    | for each such model whose short class name matches a base model already in
    | the schema, an additional extension interface is emitted:
    |
    |   export interface I{Name}Extended extends I{Name} { ... }
    |
    | This is useful for package-specific variants of app models that add extra
    | typed fields without replacing the base interface.
    |
    */
    'extended_namespaces' => [
        // Example:
        // 'OiLab\\Prestashop\\Models\\Extended',
    ],

    /*
    |--------------------------------------------------------------------------
    | Custom Props
    |--------------------------------------------------------------------------
    |
    | Add custom properties to models that aren't in the database schema
    |
    | Format:
    | - Model-specific: 'ModelName' => ['field' => 'type']
    | - All models: '?field' => 'type'
    |
    */
    'custom_props' => [
        // Example:
        // 'Organization' => [
        //     'uuid' => 'string',
        // ],
        // 'Page' => [
        //     'uuid' => 'string',
        // ],
    ],
];
