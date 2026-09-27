# OI Laravel TS — AI Context

This package generates TypeScript interfaces from Laravel Eloquent models.

## Auto-Generated Output File

The file at the configured `output_path` (default: `resources/js/types/interfaces.ts`) is **auto-generated**. Never modify it manually — all changes will be overwritten on the next generation run.

To change the generated output, modify:
- The Eloquent models in `app/Models/`
- Their casts, relationships, or PHPDoc types
- The package configuration at `config/oi-laravel-ts.php`

## Available Commands

```bash
# Generate TypeScript interfaces once
php artisan oi:gen-ts

# Watch mode — auto-regenerate when models change (for development)
php artisan oi:gen-ts --watch
```

## Configuration

Publish the config file once with:

```bash
php artisan vendor:publish --tag=oi-laravel-ts-config
```

The file `config/oi-laravel-ts.php` exposes these options:

| Key | Default | Description |
|-----|---------|-------------|
| `output_path` | `resources/js/types/interfaces.ts` | Where the generated file is written |
| `with_counts` | `true` | Include `_count` fields for HasMany / BelongsToMany |
| `with_json_ld` | `false` | Add a `JsonLdRawNode` interface |
| `discover_related_models` | `true` | Auto-detect models outside `app/Models` reached via relationships |
| `included_model_namespaces` | `[]` | Add every Eloquent model of these namespaces to the schema, as if in `app/Models` |
| `use_database_schema` | `true` | Read model columns, their types and nullability from the database (falls back to `$fillable`) |
| `declaration_style` | `'interface'` | `'type'` emits `export type IFoo = {...}`, assignable to `Record<string, T>` (Inertia `useForm`/`useHttp`) |
| `save_schema` | `false` | Write intermediate `storage/app/dev/schema.json` for debugging |
| `props_with_types` | `[]` | Override specific property types per model |
| `dataobject_namespaces` | `['App\\DataObjects']` | Namespaces to search when resolving DataObject class names |
| `data_namespaces` | `[]` | Namespaces holding spatie/laravel-data style DTOs to emit as `I{ClassName}` interfaces |
| `data_aliases` | `[]` | Map a DTO to a distinct interface base name when two DTOs share a short class name |
| `data_discriminators` | `[]` | Emit a DTO as a discriminated union: `discriminant`, `property`, `map` (value => DTO class, or a callable returning it) |
| `data_nullable_style` | `'null'` | `'null'`: `?` means the key may be absent, `\| null` that the value may be null. `'optional'`: legacy, everything nullable or defaulted renders as `?` |
| `data_replaces_model` | `false` | When `true`, a model mapped to a DTO no longer emits its own Eloquent interface |
| `data_for_model` | `[]` | Explicit `model => DTO` map (otherwise inferred from the DTO's `fromModel()` factory) |
| `custom_props` | `[]` | Inject extra TypeScript properties per model (or globally with `?field`) |

## TypeScript Interface Naming

Generated interfaces follow the `I{ModelName}` pattern — `IUser`, `IPost`, `IComment`, etc.

## Updating the AI Skill

If you update this package, re-run the install command to refresh the skill files:

```bash
php artisan oi:skills
```
