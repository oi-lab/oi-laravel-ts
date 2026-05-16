---
title: Installation
description: How to install OI Laravel TypeScript via Composer
section: getting-started
order: 2
---

# Installation

## Via Composer

```bash
composer require oi-lab/oi-laravel-ts
```

The package auto-discovers and registers itself via Laravel's service provider mechanism — no manual registration required.

## Publish the configuration

```bash
php artisan vendor:publish --tag=oi-laravel-ts-config
```

This creates `config/oi-laravel-ts.php` with sensible defaults. See [Configuration](../configuration/configuration.md) for all available options.

## Local development

To use the package from a local checkout alongside your project, add a `path` repository to your project's `composer.json`:

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

Then require it:

```bash
composer require oi-lab/oi-laravel-ts
```

## Verify the installation

Run the generator once to confirm everything is working:

```bash
php artisan oi:gen-ts
```

You should see a success message and find the generated file at `resources/js/types/interfaces.ts` (or your configured output path).
