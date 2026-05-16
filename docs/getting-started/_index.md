---
title: Introduction
description: Discover OI Laravel TypeScript and what it can do for your project
section: getting-started
order: 1
---

# OI Laravel TypeScript

OI Laravel TypeScript automatically generates TypeScript interfaces from your Eloquent models. No more writing and maintaining interfaces by hand — the package inspects your models, casts, and relationships to produce accurate, always-up-to-date TypeScript types.

## Why use this package?

When building a Laravel application with a TypeScript frontend (Inertia.js, Vue, React), you inevitably need TypeScript interfaces that mirror your backend models. Keeping them in sync is tedious and error-prone. OI Laravel TypeScript solves this by generating them directly from the source of truth: your Eloquent models.

## What gets generated?

Given this Laravel model:

```php
class User extends Model
{
    protected $casts = [
        'email_verified_at' => 'datetime',
        'settings' => SettingsCast::class,
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }
}
```

The package produces:

```typescript
export interface ISettings {
    theme: string;
    notifications: boolean;
}

export interface IUser {
    id: number;
    name: string;
    email: string;
    email_verified_at?: string;
    settings?: ISettings | null;
    created_at: string;
    updated_at: string;
    posts?: IPost[];
    posts_count?: number;
}
```

## Requirements

- PHP 8.2+
- Laravel 11, 12, or 13

## Next steps

Follow the [Installation](installation.md) guide to add the package to your project.
