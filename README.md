# OI Laravel TypeScript Generator

[![Latest Version](https://img.shields.io/github/v/release/oi-lab/oi-laravel-ts)](https://github.com/oi-lab/oi-laravel-ts/releases)
[![License](https://img.shields.io/github/license/oi-lab/oi-laravel-ts)](LICENSE)
[![Tests](https://img.shields.io/badge/tests-39%20passed-brightgreen)](TESTING.md)
[![PHP](https://img.shields.io/badge/php-8.2%20%7C%208.3%20%7C%208.4-blue)](composer.json)

A Laravel package that automatically generates TypeScript interfaces from your Eloquent models, complete with relationships, custom casts, and DataObjects support.

## Features

- **Automatic Interface Generation**: Converts Eloquent models to TypeScript interfaces
- **Relationship Support**: Handles all Laravel relationship types (HasOne, HasMany, BelongsTo, etc.)
- **Custom Casts**: Supports Laravel custom casts and automatically detects DataObjects
- **PHPDoc Support**: Reads PHPDoc annotations for complex types
- **Watch Mode**: Monitor your models directory and regenerate on changes
- **Configurable**: Extensive configuration options for customization
- **JSON-LD Support**: Optional support for JSON-LD data structures

## Architecture

This package uses a modular architecture with clear separation of concerns, organized in two main pipelines:

### Pipeline 1: Eloquent Analysis
- **Eloquent**: Facade for model analysis and schema generation
- **ModelDiscovery**: Discovers all Eloquent models in the application
- **TypeExtractor**: Extracts type information from models
- **CastTypeResolver**: Resolves custom Laravel casts to TypeScript types
- **RelationshipResolver**: Detects and extracts relationship metadata
- **DataObjectAnalyzer**: Analyzes PHP DataObject classes
- **PhpToTypeScriptConverter**: Converts PHP types to TypeScript
- **SchemaBuilder**: Orchestrates schema building for all models

### Pipeline 2: TypeScript Generation
- **Convert**: Main orchestrator coordinating the conversion process
- **TypeScriptTypeConverter**: Handles schema to TypeScript type conversion
- **DataObjectProcessor**: Processes PHP DataObjects and generates their interfaces
- **ModelInterfaceGenerator**: Generates TypeScript interfaces for Laravel models
- **ImportManager**: Manages TypeScript import statements
- **JsonLdGenerator**: Generates JSON-LD support interfaces

For detailed architecture documentation, see [ARCHITECTURE.md](ARCHITECTURE.md).

## Requirements

- PHP 8.2+
- Laravel 11.0+ or 12.0+

## Installation

### Via Composer

If the package is published on Packagist:

```bash
composer require oi-lab/oi-laravel-ts
```

### Via GitHub (Private Repository)

Add the repository to your `composer.json`:

```json
{
    "repositories": [
        {
            "type": "vcs",
            "url": "https://github.com/oi-lab/oi-laravel-ts"
        }
    ]
}
```

Then require the package:

```bash
composer require oi-lab/oi-laravel-ts
```

### Local Development

For local development, add this to your main project's `composer.json`:

```json
{
    "repositories": [
        {
            "type": "path",
            "url": "./packages/oi-lab/oi-laravel-ts"
        }
    ]
}
```

Then:

```bash
composer require oi-lab/oi-laravel-ts
```

## Configuration

Publish the configuration file:

```bash
php artisan vendor:publish --tag=oi-laravel-ts-config
```

This creates `config/oi-laravel-ts.php` with the following options:

```php
return [
    // Output path for generated TypeScript file
    'output_path' => resource_path('js/types/interfaces.ts'),

    // Include _count fields for relationships
    'with_counts' => true,

    // Enable JSON-LD support
    'with_json_ld' => false,

    // Save intermediate schema.json for debugging
    'save_schema' => false,

    // Define specific types for model properties
    'props_with_types' => [],

    // Add custom properties to models
    'custom_props' => [
        'Organization' => [
            'uuid' => 'string',
        ],
    ],
];
```

## Usage

### Basic Generation

Generate TypeScript interfaces from your models:

```bash
php artisan oi:gen-ts
```

This will scan all models in `app/Models` and generate a TypeScript file at the configured output path.

### Watch Mode

Automatically regenerate when models change:

```bash
php artisan oi:gen-ts --watch
```

### Example Output

Given a Laravel model:

```php
<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = ['name', 'email', 'bio'];

    protected $casts = [
        'email_verified_at' => 'datetime',
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

The package generates:

```typescript
export interface IUser {
    id: number;
    name: string;
    email: string;
    bio: string;
    email_verified_at?: string;
    created_at: string;
    updated_at: string;
    posts?: IPost[];
    posts_count?: number;
}
```

## Advanced Features

### Custom Properties

Add properties that aren't in your database schema:

```php
// config/oi-laravel-ts.php
'custom_props' => [
    'User' => [
        'full_name' => 'string',
        'avatar_url' => 'string',
    ],
],
```

### DataObject Support

The package automatically detects and converts custom DataObjects:

```php
// Your model
class Page extends Model
{
    protected $casts = [
        'metadata' => MetadataCast::class,
    ];
}

// Your Cast
class MetadataCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): Metadata
    {
        return Metadata::fromArray(json_decode($value, true));
    }
}

// Generated TypeScript
export interface IMetadata {
    title: string;
    description?: string;
}

export interface IPage {
    id: number;
    metadata?: IMetadata | null;
}
```

### Import External Types

Reference external TypeScript types:

```php
'custom_props' => [
    'User' => [
        'settings' => '@/types/settings|UserSettings',
    ],
],
```

Generates:

```typescript
import { UserSettings } from '@/types/settings';

export interface IUser {
    settings: UserSettings;
}
```

## Examples

### Complete Workflow

1. Define your models with relationships and casts
2. Configure custom properties if needed
3. Run the generator:

```bash
php artisan oi:gen-ts
```

4. Use the generated interfaces in your TypeScript code:

```typescript
import { IUser, IPost } from '@/types/interfaces';

const user: IUser = await fetchUser();
const posts: IPost[] = user.posts || [];
```

### Integration with Inertia.js

```typescript
import { PageProps } from '@inertiajs/core';
import { IUser } from '@/types/interfaces';

interface Props extends PageProps {
    user: IUser;
}

export default function Dashboard({ user }: Props) {
    // TypeScript knows all User properties
    console.log(user.email);
}
```

## Testing

This package includes comprehensive test coverage with **39 tests** and **160 assertions**.

### Run Tests

```bash
# Run all tests
vendor/bin/pest

# Run specific test suite
vendor/bin/pest tests/Unit
vendor/bin/pest tests/Feature

# Run with coverage
vendor/bin/pest --coverage
```

### Test Coverage

- ✅ Type conversion (PHP → TypeScript)
- ✅ Model analysis and schema building
- ✅ Relationship detection (HasMany, BelongsTo, etc.)
- ✅ Custom cast resolution
- ✅ DataObject handling
- ✅ TypeScript interface generation
- ✅ Integration tests for full pipeline

For detailed testing documentation, see [TESTING.md](TESTING.md).

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

When contributing:
1. Write tests for new features
2. Ensure all tests pass: `vendor/bin/pest`
3. Follow existing code style
4. Update documentation as needed

## License

This package is open-source software licensed under the [MIT license](LICENSE).

## Credits

**[Olivier Lacombe](https://www.olacombe.com)** - Creator and maintainer

Olivier is a Product & Technology Director based in Montpellier, France, with over 20 years of experience innovating in UX/UI and emerging technologies. He specializes in guiding enterprises toward cutting-edge digital solutions, combining user-centered design with continuous optimization and artificial intelligence integration.

**Projects & Resources:**
- [OnAI](https://onai.olacombe.com) - Training courses and masterclasses on generative AI for businesses
- [Promptr](https://promptr.olacombe.com) - Prompt engineering Management Platform

## Support

For support, please open an issue on the [GitHub repository](https://github.com/oi-lab/oi-laravel-ts/issues).
