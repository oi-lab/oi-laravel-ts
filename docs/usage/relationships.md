---
title: Relationships
description: How the package handles Eloquent relationships
section: usage
order: 2
---

# Relationships

The package detects all standard Eloquent relationships and generates the corresponding TypeScript types.

## Supported relationship types

| Eloquent relationship | TypeScript type |
|-----------------------|-----------------|
| `HasOne` | `IRelated \| null` |
| `HasMany` | `IRelated[]` |
| `BelongsTo` | `IRelated \| null` |
| `BelongsToMany` | `IRelated[]` |
| `HasOneThrough` | `IRelated \| null` |
| `HasManyThrough` | `IRelated[]` |
| `MorphOne` | `IRelated \| null` |
| `MorphMany` | `IRelated[]` |
| `MorphTo` | `Record<string, unknown> \| null` |

All relationships are optional (`?`) since they may or may not be loaded.

## Example

```php
class Post extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
```

Generated interface:

```typescript
export interface IPost {
    id: number;
    title: string;
    body: string;
    user_id: number;
    created_at: string;
    updated_at: string;
    author?: IUser | null;
    tags?: ITag[];
    comments?: IComment[];
    comments_count?: number;
    tags_count?: number;
}
```

## Relationship counts

When `with_counts` is `true` (default), `HasMany` and `BelongsToMany` relationships generate an additional `{relation}_count?: number` field. This matches Laravel's `withCount()` query behavior.

To disable:

```php
// config/oi-laravel-ts.php
'with_counts' => false,
```

## Related model discovery

When `discover_related_models` is `true`, the package follows relationships to discover models outside `app/Models`. This is useful when using third-party packages:

```php
// spatie/laravel-permission
class User extends Model
{
    use HasRoles; // adds roles() and permissions() relationships
}
```

The generator follows these relationships and generates `IRole` and `IPermission` interfaces automatically.
