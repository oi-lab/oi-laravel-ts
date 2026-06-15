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
| `save_schema` | `false` | Write intermediate `storage/app/dev/schema.json` for debugging |
| `props_with_types` | `[]` | Override specific property types per model |
| `dataobject_namespaces` | `['App\\DataObjects']` | Namespaces to search when resolving DataObject class names |
| `custom_props` | `[]` | Inject extra TypeScript properties per model (or globally with `?field`) |

## TypeScript Interface Naming

Generated interfaces follow the `I{ModelName}` pattern — `IUser`, `IPost`, `IComment`, etc.

## Updating the AI Skill

If you update this package, re-run the install command to refresh the skill files:

```bash
php artisan oi:skills
```
