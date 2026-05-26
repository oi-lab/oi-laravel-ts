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
