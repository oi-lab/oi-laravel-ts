# Changelog

All notable changes to `oi-laravel-ts` will be documented in this file.

## [Unreleased]

## [1.2.0] - 2026-09-27

### Added
- **`use_database_schema`** (default `true`): model attributes are read from the table. Every column the model serializes is emitted — fillable or not — typed from its cast or, when uncast, from its column type, and marked nullable (`col?: T | null`) when the column is. Falls back to `$fillable` when disabled or when the database is unreachable at generation time.
- **`declaration_style`** (default `'interface'`): `'type'` emits `export type IFoo = { ... };` instead of `export interface IFoo { ... }`. A type alias is assignable to `Record<string, T>` — what Inertia's `useForm` / `useHttp` require — where an interface never is. Extension models become intersections.
- **`data_discriminators`**: emit a DTO as a discriminated union. Given a discriminant property and a map `value => DTO class` (literal, or a callable resolved at generation time), the DTO comes out as `I{X}Base`, `I{X}{Property}Map` and `I{X} = I{X}Base & ( | { key: 'a'; prop: IA } | ... )`, so narrowing on the discriminant narrows the property. The `Record<string, unknown>` member that absorbed a `@param A|B|array<string, mixed>` union is gone.

### Fixed
- `list<T>`, `non-empty-list<T>`, `non-empty-array<K, V>`, `iterable<T>` and `Collection<K, T>` resolve like `array<K, T>` instead of falling back to `unknown` without warning.
- A union nested in a generic (`array<int, string|int>`) no longer recurses forever.
- Enum casts on models emit their literal union instead of `never`.
- Built-in casts are typed by what they serialize to: `int`/`bool`/`timestamp`/`immutable_datetime`/`decimal:*`/`hashed`/`encrypted` no longer come out as `never`; `array`/`json`/`collection` come out as `unknown`.
- Hidden attributes (`$hidden`, `#[Hidden]`) are left out of model interfaces — they never reach the JSON.
- A class cast whose value type cannot be read is typed `unknown` instead of falling back to its column (a json column read as `string`), and `| null` is no longer appended to `unknown`.
- `MorphTo` relations are typed `unknown` and `HasOneThrough` / `HasManyThrough` resolve to their related model, instead of `never`.
- New-style `Attribute` accessors listed in `$appends` are typed from their `@return Attribute<TGet, TSet>` annotation, or else from the getter closure's return type, instead of `unknown` (rendered `never`).

### Changed
- **Generated output** — with `use_database_schema` on, model interfaces gain their non-fillable columns and nullable columns become `col?: T | null`. Timestamps stay required. Set `use_database_schema => false` for the previous output.

## [1.1.0] - 2026-07-09

### Changed
- **BREAKING (generated output)** — a DTO interface now describes the JSON the DTO *produces*, not the arguments its constructor *accepts*. `| null` is emitted **iff** the property accepts null, and `?` **iff** the key can genuinely be absent from the payload, which for a DTO means a `Spatie\LaravelData\Optional` or `Lazy` marker. A default value no longer makes a property optional, since a serializer emits every declared property.

  | PHP declaration | Before | After |
  | --- | --- | --- |
  | `public ?string $x = null` | `x?: string;` | `x: string \| null;` |
  | `public int $x = 3` | `x?: number;` | `x: number;` |
  | `public array $x = []` + `@var Foo[]` | `x?: IFoo[];` | `x: IFoo[];` |
  | `public string\|Optional $x` | `x: string;` | `x?: string;` |

  This applies to the interfaces produced from `data_namespaces` **and** `dataobject_namespaces`. Eloquent model interfaces are untouched — `ModelInterfaceGenerator` already kept the two axes separate. Set `data_nullable_style => 'optional'` to restore the previous rendering.

### Added
- **`data_nullable_style`** (default `'null'`): selects the rendering above, or the legacy `'optional'` one where any nullable or defaulted property becomes `?` and `| null` is never emitted.
- **`data_aliases`**: map a DTO's FQCN to a distinct interface base name so two DTOs sharing a short class name can both be generated instead of aborting with `DataObjectNameCollisionException`. The alias is the name used everywhere, including nested references from other DTOs.
- **`included_model_namespaces`**: the positive counterpart to `excluded_namespaces`. Adds every Eloquent model of the listed namespaces to the schema as if it lived in `app/Models` — with its relationships and `_count` fields — even when nothing in the application points at it through a relationship. Abstract models and non-model classes are skipped, and `excluded_namespaces` still wins. Also available programmatically via `Eloquent::setIncludedModelNamespaces()`.

## [1.0.23] - 2026-07-07

### Changed
- Changelog entries brought up to date for DTO support, model replacement, and the AI skill command deprecation.

## [1.0.22] - 2026-06-16

### Added
- **DTO support** (`data_namespaces`): emit an `I{ClassName}` interface for every spatie/laravel-data style DTO found under the configured namespaces. Detection is structural — no dependency on `spatie/laravel-data` is required. Property names are kept verbatim (camelCase), backed enums become literal unions, nested DTOs become `I{Name}`, and typed arrays declared via a property `@var Foo[]` annotation become `IFoo[]`. Namespaces are scanned recursively; DTO directories are also watched in `--watch` mode.
- **Model replacement** (`data_replaces_model`, default `false`): when enabled, a model mapped to a DTO no longer emits its own Eloquent `I{Model}` interface. The model is inferred from the DTO's `fromModel()` factory or set explicitly via `data_for_model`. Disabled by default, so existing output is unchanged.

## [1.0.21] - 2026-06-15

### Changed
- The AI skill install command was renamed to `oi-ts:install-ai-skill` and **deprecated** in favor of the unified `php artisan oi:skills` command (provided by `oi-lab/oi-laravel-development`), which discovers and installs skills from all installed `oi-lab/*` packages.

## [1.0.19] - 2026-05-29

### Fixed
- The primary key is skipped for models that declare none.

## [1.0.18] - 2026-05-26

### Fixed
- Primary key type is now resolved properly: the generator checks `$casts` first (skipping class-based casts such as `AsUuid`), then falls back to `getKeyType()`. UUID / ULID primary keys declared as `string` via `HasUuids` or `$keyType` are now typed as `string` instead of `number`.
- Primary key column is no longer duplicated in the generated interface when it also appears in `$fillable`.

## [1.0.16] - 2026-05-25

### Added
- **Namespace exclusion** (`excluded_namespaces`): models whose fully-qualified class name begins with one of the listed prefixes are dropped entirely from the schema — including when they are reached through a relationship. Relationship fields pointing to excluded models are also stripped from other interfaces (1.0.17).
- **Extension interfaces** (`extended_namespaces`): models in these namespaces do not generate standalone interfaces. Instead, for each such model whose short class name matches a base model in the schema, an `I{Name}Extended extends I{Name}` interface is emitted with the extra fields.
- Both options are also available programmatically via `Eloquent::setExcludedNamespaces()` and `Eloquent::setExtendedNamespaces()`.

## [1.0.14] - 2026-05-23

### Fixed
- `TypeScriptTypeConverter::convertColumnType()` now passes native TypeScript types (`[]`, `|`, `Record<...>`) through unchanged, allowing `custom_props` config entries to declare TypeScript types directly.

## [1.0.11] - 2026-05-17

### Fixed
- Casts returning an array of PHP primitives (e.g. `@return array<int, int>`) now resolve to native TypeScript array types (`number[]`, `string[]`, `boolean[]`) instead of `never`.

> Earlier `1.0.x` patch releases were not recorded here at the time. See `git log` between `v1.0.0` and `v1.0.23` for the full history.

## [1.0.0] - 2025-01-30

Initial release of OI Laravel TypeScript Generator - a comprehensive Laravel package that automatically generates TypeScript interfaces from Eloquent models.

### Core Features
- **Automatic Interface Generation**: Converts Eloquent models to TypeScript interfaces with full type safety
- **Relationship Support**: Handles all Laravel relationship types (HasOne, HasMany, BelongsTo, BelongsToMany, MorphTo, MorphMany, etc.)
- **Custom Casts**: Automatic detection and conversion of Laravel custom casts
- **DataObject Support**: Analyzes and generates interfaces for PHP DataObject classes
- **PHPDoc Support**: Reads PHPDoc annotations for complex types
- **Watch Mode**: Monitor models directory and automatically regenerate on changes
- **Configurable Options**: Extensive configuration for customization
- **JSON-LD Support**: Optional support for JSON-LD data structures
- **Relationship Counts**: Automatic generation of `_count` fields for relationships
- **External Type Imports**: Reference and import external TypeScript types

### Architecture
Built with clean architecture principles and separation of concerns:

#### Analysis Pipeline (Eloquent)
- `Eloquent`: Facade for model analysis and schema generation
- `ModelDiscovery`: Discovers all Eloquent models in the application
- `TypeExtractor`: Extracts type information from model properties
- `RelationshipResolver`: Detects and extracts relationship metadata
- `CastTypeResolver`: Resolves custom Laravel casts to TypeScript types
- `DataObjectAnalyzer`: Analyzes PHP DataObject classes
- `PhpToTypeScriptConverter`: Converts PHP types to TypeScript
- `SchemaBuilder`: Orchestrates complete schema building

#### Generation Pipeline (Convert)
- `Convert`: Main orchestrator coordinating TypeScript generation
- `TypeScriptTypeConverter`: Handles schema to TypeScript type conversion
- `DataObjectProcessor`: Processes PHP DataObjects and generates interfaces
- `ModelInterfaceGenerator`: Generates TypeScript interfaces for models
- `ImportManager`: Manages TypeScript import statements
- `JsonLdGenerator`: Generates JSON-LD support interfaces

### Technical Excellence
- **SOLID Principles**: Each class follows Single Responsibility Principle
- **Dependency Injection**: Used throughout for better testability
- **Comprehensive Documentation**: ~900+ lines of PHPDoc documentation
- **Type Safety**: Full PHP type hints with structured array shapes
- **Modular Design**: Plugin-like architecture for easy extension
- **Error Handling**: Robust error handling throughout pipelines

### Requirements
- PHP 8.2, 8.3, or 8.4
- Laravel 11.0+ or 12.0+

### Testing
- 39 comprehensive tests with 160 assertions
- Unit tests for all components
- Feature tests for integration scenarios
- Architecture tests for code quality

### Command Line Interface
- `php artisan oi:gen-ts` - Generate TypeScript interfaces
- `php artisan oi:gen-ts --watch` - Watch mode for automatic regeneration

### Configuration Options
- `output_path` - Custom output path for generated TypeScript
- `with_counts` - Include relationship count fields
- `with_json_ld` - Enable JSON-LD support
- `save_schema` - Save intermediate schema.json for debugging
- `props_with_types` - Define specific types for properties
- `custom_props` - Add custom properties to models
