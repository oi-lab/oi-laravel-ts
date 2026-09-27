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
    | Use Database Schema
    |--------------------------------------------------------------------------
    |
    | When enabled, a model's attributes are read from its table: every column
    | the model serializes (fillable or not, hidden ones excluded), typed from
    | its cast or its column type, and marked nullable when the column is.
    | Without it — or when the database is unreachable at generation time —
    | only `$fillable` is used, and an uncast column is typed `string`.
    |
    */
    'use_database_schema' => true,

    /*
    |--------------------------------------------------------------------------
    | Declaration Style
    |--------------------------------------------------------------------------
    |
    | How each generated shape is declared:
    |
    | - 'interface' (default): `export interface IUser { ... }`
    | - 'type'               : `export type IUser = { ... };`
    |
    | A type alias carries an implicit index signature, an interface never
    | does: only the 'type' style is assignable to `Record<string, T>`, which
    | form helpers such as Inertia's `useForm` / `useHttp` require of their
    | data. Extension models become intersections (`IUser & { ... }`).
    |
    */
    'declaration_style' => 'interface',

    /*
    |--------------------------------------------------------------------------
    | Data Discriminators
    |--------------------------------------------------------------------------
    |
    | Emit a DTO as a discriminated union: one property (the discriminant)
    | decides the type of another. Without it, a `@param A|B|array<string,
    | mixed>` union carries no discriminant and TypeScript cannot narrow it.
    |
    |   'data_discriminators' => [
    |       App\Data\BlockData::class => [
    |           'discriminant' => 'template_key',
    |           'property' => 'props',
    |           // A literal map, or a callable returning one, called at
    |           // generation time: [Registry::class, 'propsClasses'].
    |           'map' => [
    |               'hero' => App\Data\Blocks\HeroData::class,
    |               'grid' => App\Data\Blocks\GridData::class,
    |           ],
    |       ],
    |   ],
    |
    | produces `IBlockDataBase` (the shared properties), `IBlockDataPropsMap`
    | (`{ 'hero': IHeroData; 'grid': IGridData }`) and
    |
    |   export type IBlockData = IBlockDataBase & (
    |       | { template_key: 'hero'; props: IHeroData }
    |       | { template_key: 'grid'; props: IGridData }
    |   );
    |
    */
    'data_discriminators' => [],

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
    | Data Aliases (disambiguate colliding DTO short names)
    |--------------------------------------------------------------------------
    |
    | Two DTOs from different namespaces may share a short class name — say
    | `App\Data\ContentData` and a package's `...\Data\Blocks\ContentData`. Both
    | would claim `IContentData`, so generation aborts with a
    | DataObjectNameCollisionException.
    |
    | Map a DTO to a distinct interface base name (without the `I` prefix) to
    | resolve the conflict without renaming the PHP class. The alias is the name
    | used everywhere: the emitted interface, and any nested reference to it
    | from another DTO.
    |
    |   'data_aliases' => [
    |       OiLab\OiLaravelPublish\Data\Blocks\ContentData::class => 'PublishContentData',
    |   ],
    |
    | emits `IPublishContentData` and leaves `IContentData` to App\Data\ContentData.
    |
    */
    'data_aliases' => [
        // Example:
        // OiLab\OiLaravelPublish\Data\Blocks\ContentData::class => 'PublishContentData',
    ],

    /*
    |--------------------------------------------------------------------------
    | Data Nullable Style
    |--------------------------------------------------------------------------
    |
    | How nullability is rendered on DTO and DataObject interfaces (the ones
    | produced from `data_namespaces` and `dataobject_namespaces`). Model
    | interfaces are unaffected.
    |
    | - 'null' (default): `?` and `| null` describe two different facts.
    |       `?string $x = null`      => `x: string | null;`
    |       `int $x = 3`             => `x: number;`
    |       `string|Optional $x`     => `x?: string;`
    |   This matches the JSON a DTO actually produces: a serializer emits every
    |   declared property, so a default value never makes a key absent — only an
    |   `Optional` / `Lazy` marker does.
    |
    | - 'optional' (legacy): any nullable or defaulted property is rendered `?`
    |   and `| null` is never emitted. Keep this while consumers still feed DTO
    |   interfaces into form inputs that expect every field to be optional.
    |
    */
    'data_nullable_style' => 'null',

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
    | Included Model Namespaces
    |--------------------------------------------------------------------------
    |
    | Namespaces whose Eloquent models are added to the schema as if they lived
    | in app/Models — with their relationships, their `_count` fields, and their
    | own discovery of related models.
    |
    | `discover_related_models` only reaches a model that some other model in the
    | schema already points at. A package model that nothing in the application
    | references therefore never gets an interface. List its namespace here when
    | a controller hands that model straight to the front end.
    |
    |   'included_model_namespaces' => [
    |       'OiLab\\OiLaravelPublish\\Models',
    |       'OiLab\\OiLaravelAttachments\\Models',
    |   ],
    |
    | `excluded_namespaces` below still wins over this list.
    |
    */
    'included_model_namespaces' => [
        // Example:
        // 'OiLab\\OiLaravelPublish\\Models',
    ],

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
