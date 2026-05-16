---
title: Custom Casts
description: How the package resolves Laravel custom casts to TypeScript types
section: usage
order: 3
---

# Custom Casts

## Built-in cast mapping

Laravel's built-in casts are automatically resolved:

| Laravel cast | TypeScript type |
|---|---|
| `integer`, `int` | `number` |
| `float`, `double`, `decimal` | `number` |
| `boolean`, `bool` | `boolean` |
| `string` | `string` |
| `array`, `json` | `Record<string, unknown>` |
| `collection` | `unknown[]` |
| `date`, `datetime`, `immutable_date`, `immutable_datetime` | `string` |
| `timestamp` | `number` |
| `encrypted` | `string` |
| Enum class | Union of enum values |

## Custom cast classes

When a cast returns a known type, the package resolves it:

```php
class PriceCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): int
    {
        return (int) ($value * 100);
    }
}

class Product extends Model
{
    protected $casts = [
        'price' => PriceCast::class,
    ];
}
```

Generated:

```typescript
export interface IProduct {
    id: number;
    price: number; // resolved from PriceCast return type
}
```

## Enum casts

PHP enums are resolved to TypeScript union types:

```php
enum Status: string
{
    case Active = 'active';
    case Inactive = 'inactive';
    case Banned = 'banned';
}

class User extends Model
{
    protected $casts = [
        'status' => Status::class,
    ];
}
```

Generated:

```typescript
export interface IUser {
    id: number;
    status: 'active' | 'inactive' | 'banned';
}
```

## Fallback behavior

When the return type of a custom cast cannot be resolved (no return type hint, complex generics, etc.), the field is typed as `unknown`. You can override this with `props_with_types` in the config:

```php
'props_with_types' => [
    'User' => [
        'complex_field' => 'MyCustomType',
    ],
],
```
