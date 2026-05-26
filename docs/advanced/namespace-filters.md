---
title: Namespace Filters
description: Exclude or extend models from specific namespaces
section: advanced
order: 5
---

# Namespace Filters

Two configuration options let you control how models from specific namespaces are handled: `excluded_namespaces` drops them entirely, and `extended_namespaces` turns them into extension interfaces.

## Excluding namespaces

Models whose fully-qualified class name begins with one of the `excluded_namespaces` prefixes are silently dropped from the schema — including when they are reached through a relationship.

```php
// config/oi-laravel-ts.php
'excluded_namespaces' => [
    'OiLab\\Prestashop\\Models',
],
```

With this configuration, `OiLab\Prestashop\Models\Category` is never emitted, and any relationship field on another model that resolves to an excluded model is also stripped.

**Before:**

```typescript
export interface IProduct {
    id: number;
    name: string;
    category?: ICategory; // points to an excluded model
}
```

**After:**

```typescript
export interface IProduct {
    id: number;
    name: string;
    // category field removed
}
```

## Extension interfaces

Models in `extended_namespaces` do not generate standalone interfaces. Instead, for each such model whose **short class name** matches a base model in the schema, an additional `I{Name}Extended extends I{Name}` interface is generated.

```php
// config/oi-laravel-ts.php
'extended_namespaces' => [
    'OiLab\\Prestashop\\Extended\\Models',
],
```

For example, if `OiLab\Prestashop\Extended\Models\User` has an extra `prestashop_id` field and `App\Models\User` is already in the schema:

```typescript
export interface IUser {
    id: number;
    name: string;
    email: string;
}

export interface IUserExtended extends IUser {
    prestashop_id: number;
}
```

`IUserExtended` contains only the **additional** fields from the extended model — the base `IUser` fields are inherited via the `extends` clause.

> If no base model with the matching short name exists in the schema, the extension model is skipped silently.

## Programmatic API

Both options are available at runtime via the `Eloquent` facade:

```php
use OiLab\OiLaravelTs\Services\Eloquent;

Eloquent::setExcludedNamespaces(['OiLab\\Prestashop\\Models']);
Eloquent::setExtendedNamespaces(['OiLab\\Prestashop\\Extended\\Models']);
```

This is useful in service providers or when you need to configure the generator conditionally.
