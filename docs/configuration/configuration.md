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
