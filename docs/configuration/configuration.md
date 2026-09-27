---
title: Configuration
description: Complete reference for all configuration options
section: configuration
order: 1
---

# Configuration

After publishing the config file, you will find all options in `config/oi-laravel-ts.php`.

## output_path

**Type:** `string` — **Default:** `resource_path('js/types/interfaces.ts')`

The path where the generated TypeScript file is written.

```php
'output_path' => resource_path('js/types/interfaces.ts'),
```

Used only in `single` output mode.

## output_mode

**Type:** `string` — **Default:** `'single'`

Controls how the generated interfaces are written to disk:

- `'single'` — every interface is concatenated into one file at `output_path`.
- `'multiple'` — each interface is written to its own kebab-cased file in
  `output_dir`, alongside an `index.ts` barrel. Every file imports exactly the
  interfaces it references.

```php
'output_mode' => 'single',
```

See [Multi-file output](../advanced/multi-file-output.md) for the full layout and
import behavior.

## output_dir

**Type:** `string` — **Default:** `resource_path('js/types')`

Target directory for the generated files when `output_mode` is `'multiple'`.
Ignored in `single` mode.

```php
'output_dir' => resource_path('js/types'),
```

## with_counts

**Type:** `bool` — **Default:** `true`

When enabled, the generator adds an optional `{relation}_count` field for every `HasMany` and `BelongsToMany` relationship.

```php
'with_counts' => true,
```

With `true`, a `User` with a `posts` relationship also receives `posts_count?: number`.

## with_json_ld

**Type:** `bool` — **Default:** `false`

Adds a `JsonLdRawNode` interface to the generated file, useful when working with JSON-LD data structures.

```php
'with_json_ld' => false,
```

## discover_related_models

**Type:** `bool` — **Default:** `true`

When enabled, any model targeted by a relationship is added to the schema even if it lives outside `app/Models`. This is essential when using packages like `spatie/laravel-permission` — the `Role` and `Permission` models are discovered via relationship detection and get their own interfaces.

```php
'discover_related_models' => true,
```

## use_database_schema

**Type:** `bool` — **Default:** `true`

Reads each model's table to build its interface: every column the model
serializes — fillable or not, hidden ones excluded — typed from its cast or,
when uncast, from its column type. A nullable column renders as
`col?: T | null`. Enum casts become literal unions.

Generation never requires a database: when this is off, or when the table
cannot be read, only `$fillable` is used and an uncast column is typed
`string`.

```php
'use_database_schema' => true,
```

## declaration_style

**Type:** `string` — **Default:** `'interface'`

How each generated shape is declared:

| Value         | Output                          |
| ------------- | ------------------------------- |
| `'interface'` | `export interface IUser { ... }` |
| `'type'`      | `export type IUser = { ... };`   |

A type alias has an implicit index signature; an interface never does. Only
the `'type'` style is assignable to `Record<string, T>`, which Inertia's
`useForm` / `useHttp` require of their data — with interfaces, every consumer
has to restate the shape through a mapped type first. Extension models become
intersections (`IUser & { ... }`).

```php
'declaration_style' => 'type',
```

## save_schema

**Type:** `bool` — **Default:** `false`

Saves an intermediate `schema.json` file next to the output file. Useful for debugging when the generated TypeScript doesn't look right.

```php
'save_schema' => false,
```

## props_with_types

**Type:** `array` — **Default:** `[]`

Override the inferred TypeScript type for specific model properties.

```php
'props_with_types' => [
    'User' => [
        'status' => "'active' | 'inactive' | 'banned'",
    ],
],
```

## dataobject_namespaces

**Type:** `array` — **Default:** `['App\\DataObjects']`

Namespaces searched when resolving short DataObject class names found in PHPDoc annotations (e.g. `@var Address`). The list is iterated in order; the first matching class wins.

```php
'dataobject_namespaces' => [
    'App\\DataObjects',
    'App\\ValueObjects',
],
```

## discover_all_dataobjects

**Type:** `bool` — **Default:** `false`

When `true`, every DataObject under `dataobject_namespaces` is emitted as an
`I{ClassName}` interface, even if no model cast references it. Namespaces are
scanned recursively and nested DataObjects are resolved automatically. Two
classes resolving to the same short name throw a
`DataObjectNameCollisionException`.

When `false` (default), a DataObject is only generated when it is reachable from
a model cast. See [DataObjects](/usage/data-objects) for details.

```php
'discover_all_dataobjects' => false,
```

## data_namespaces

**Type:** `array` — **Default:** `[]`

Namespaces holding spatie/laravel-data style Data Transfer Objects (DTOs). Every
class found under these namespaces that has a constructor with promoted
properties is emitted as an `I{ClassName}` interface (e.g.
`App\Data\Knowledge\KnowledgeData` becomes `IKnowledgeData`).

Detection is structural — **no dependency on spatie/laravel-data is required**.
Property names are kept verbatim (camelCase), backed enums become literal unions
(`'draft' | 'published'`), nested DTOs become `I{Name}`, and typed arrays
declared through a property `@var Foo[]` annotation become `IFoo[]`. Namespaces
are scanned recursively and nested DTOs are resolved automatically.

This is fully opt-in: an empty list keeps the previous behavior unchanged. It is
a distinct mechanism from `dataobject_namespaces`, which resolves value objects
by short name through the `fromArray()`/`toArray()` contract.

```php
'data_namespaces' => [
    'App\\Data',
],
```

## data_nullable_style

**Type:** `string` — **Default:** `'null'`

Controls how nullability is rendered on DTO and DataObject interfaces (the ones
produced from `data_namespaces` and `dataobject_namespaces`). Model interfaces
are unaffected.

A generated `I{X}Data` interface describes the JSON a DTO **produces**, not the
arguments its constructor **accepts**. Serializers emit every declared property,
so two independent facts must not be collapsed onto one notation:

| Notation          | Meaning                                     |
| ----------------- | ------------------------------------------- |
| `name?: T`        | the key may be **absent** from the payload  |
| `name: T \| null` | the key is present, the value may be `null` |

With `'null'` (default), `| null` is emitted if and only if the property accepts
null, and `?` if and only if the property is declared through a
`Spatie\LaravelData\Optional` or `Lazy` marker. **A default value makes nothing
optional on the output side.**

| PHP declaration                       | TypeScript      |
| ------------------------------------- | --------------- |
| `public ?string $x = null`            | `x: string \| null;` |
| `public ?string $x`                   | `x: string \| null;` |
| `public string $x`                    | `x: string;`    |
| `public int $x = 3`                   | `x: number;`    |
| `public array $x = []` + `@var Foo[]` | `x: IFoo[];`    |
| `public ?FooData $x = null`           | `x: IFooData \| null;` |
| `public string\|Optional $x`          | `x?: string;`   |

With the legacy `'optional'` style, any nullable *or* defaulted property is
rendered `?` and `| null` is never emitted. Keep it while consumers still feed
DTO interfaces into form inputs that expect every field to be optional.

```php
'data_nullable_style' => 'null', // 'null' | 'optional'
```

## data_aliases

**Type:** `array` — **Default:** `[]`

Two DTOs from different namespaces may share a short class name — say
`App\Data\ContentData` and a package's `...\Data\Blocks\ContentData`. Both would
claim `IContentData`, so generation aborts with a
`DataObjectNameCollisionException`.

Map a DTO to a distinct interface base name (without the `I` prefix) to resolve
the conflict without renaming the PHP class. The alias is the name used
everywhere: the emitted interface, and any nested reference to it from another
DTO.

```php
'data_aliases' => [
    OiLab\OiLaravelPublish\Data\Blocks\ContentData::class => 'PublishContentData',
],
```

This emits `IPublishContentData` and leaves `IContentData` to
`App\Data\ContentData`.

## data_discriminators

**Type:** `array` — **Default:** `[]`

Emits a DTO as a discriminated union, for a DTO whose one property (the
discriminant) decides the type of another. A `@param HeroData|GridData|array<string, mixed>`
union alone gives TypeScript nothing to narrow on, and its `Record<string, unknown>`
member absorbs the others.

```php
'data_discriminators' => [
    App\Data\BlockData::class => [
        'discriminant' => 'template_key',
        'property' => 'props',
        'map' => [
            'hero' => App\Data\Blocks\HeroData::class,
            'grid' => App\Data\Blocks\GridData::class,
        ],
    ],
],
```

`map` may also be a callable returning that array — `[Registry::class, 'method']`
or an invokable class name — called through the container at generation time,
so a package can derive it from its own registry.

```typescript
export interface IBlockDataBase { id: number; name: string | null; }

export type IBlockDataPropsMap = { 'hero': IHeroData; 'grid': IGridData; };

export type IBlockData = IBlockDataBase & (
    | { template_key: 'hero'; props: IHeroData }
    | { template_key: 'grid'; props: IGridData }
);
```

## data_replaces_model

**Type:** `bool` — **Default:** `false`

When `false` (default), DTO interfaces are emitted *in addition* to the Eloquent
model interfaces — `IKnowledge` (model) and `IKnowledgeData` (DTO) coexist.

When `true`, any model mapped to a DTO no longer emits its Eloquent `I{Model}`
interface: the DTO becomes the single source of truth for that model's shape. The
model is identified from the first parameter of the DTO's `fromModel()` factory,
or from `data_for_model`.

> Note: with this enabled, a relationship on another model that points to a
> replaced model will reference an interface that is no longer generated.

```php
'data_replaces_model' => false,
```

## data_for_model

**Type:** `array` — **Default:** `[]`

Explicit `model => DTO` overrides, used when a DTO has no `fromModel(Model $m)`
factory to introspect, or to force a specific pairing.

```php
'data_for_model' => [
    App\Models\Knowledge::class => App\Data\Knowledge\KnowledgeData::class,
],
```

## included_model_namespaces

**Type:** `array` — **Default:** `[]`

Namespaces whose Eloquent models are added to the schema as if they lived in
`app/Models` — with their relationships, their `_count` fields, and their own
discovery of related models.

`discover_related_models` only reaches a model that some other model already in
the schema points at. A package model that nothing in the application references
therefore never gets an interface. List its namespace here when a controller
hands that model straight to the front end.

```php
'included_model_namespaces' => [
    'OiLab\\OiLaravelPublish\\Models',
    'OiLab\\OiLaravelAttachments\\Models',
],
```

Abstract models and non-model classes found in the namespace are skipped, and
`excluded_namespaces` still wins over this list.

## excluded_namespaces

**Type:** `array` — **Default:** `[]`

Models whose fully-qualified class name begins with one of these namespace prefixes are excluded entirely from the generated schema — even when they are reached through a relationship. Relationship fields that point to an excluded model are also stripped from all other interfaces.

```php
'excluded_namespaces' => [
    'OiLab\\Prestashop\\Models',
],
```

This is useful when a third-party package registers models that you do not want to expose as TypeScript interfaces.

See [Namespace filters](../advanced/namespace-filters.md) for full examples.

## extended_namespaces

**Type:** `array` — **Default:** `[]`

Models in these namespaces do not generate standalone interfaces. Instead, for each such model whose short class name matches a base model already in the schema, an additional extension interface is emitted:

```typescript
export interface IUserExtended extends IUser { ... }
```

```php
'extended_namespaces' => [
    'OiLab\\Prestashop\\Extended\\Models',
],
```

This is useful for package-specific variants of your app models that add extra typed fields without replacing the base interface.

See [Namespace filters](../advanced/namespace-filters.md) for full examples.

## custom_props

**Type:** `array` — **Default:** `[]`

Add virtual properties to models — properties that don't exist in the database schema but are present in the serialized JSON (computed attributes, appended properties, etc.).

```php
'custom_props' => [
    // Add properties to a specific model
    'User' => [
        'full_name' => 'string',
        'avatar_url' => 'string | null',
    ],
    // Reference an external TypeScript type (format: 'path|TypeName')
    'Page' => [
        'layout' => '@/types/layouts|LayoutConfig',
    ],
],
```

The external type format `@/types/layouts|LayoutConfig` generates an import statement:

```typescript
import { LayoutConfig } from '@/types/layouts';
```

In `multiple` output mode this import is re-emitted in each generated file that
uses the type. See [Multi-file output](../advanced/multi-file-output.md).
