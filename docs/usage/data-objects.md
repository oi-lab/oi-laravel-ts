---
title: DataObjects
description: Automatic interface generation for PHP DataObjects and Value Objects
section: usage
order: 4
---

# DataObjects

The package automatically generates TypeScript interfaces for PHP DataObjects (also called Value Objects or DTOs) when they are used as custom casts.

> **DataObjects vs spatie/laravel-data DTOs.** This page covers *value objects*
> resolved through the `fromArray()`/`toArray()` contract and the
> `dataobject_namespaces` option — typically used inside custom casts. For
> spatie/laravel-data style DTOs (camelCase props, backed enums, `fromModel()`
> factories, model replacement), see the `data_namespaces`, `data_replaces_model`
> and `data_for_model` options in [Configuration](/configuration/configuration).
> The two mechanisms are independent.

## What is a DataObject?

A DataObject is a class that exposes both `fromArray()` and `toArray()` methods. The package uses these as a signal that the class is a structured data container that should get its own TypeScript interface.

```php
class Address
{
    public function __construct(
        public string $street,
        public string $city,
        public string $country,
        public ?string $zip = null,
    ) {}

    public static function fromArray(array $data): self
    {
        return new self(
            street: $data['street'],
            city: $data['city'],
            country: $data['country'],
            zip: $data['zip'] ?? null,
        );
    }

    public function toArray(): array
    {
        return [
            'street' => $this->street,
            'city' => $this->city,
            'country' => $this->country,
            'zip' => $this->zip,
        ];
    }
}
```

## Using a DataObject as a cast

```php
class AddressCast implements CastsAttributes
{
    public function get($model, string $key, $value, array $attributes): Address
    {
        return Address::fromArray(json_decode($value, true));
    }

    public function set($model, string $key, $value, array $attributes): string
    {
        return json_encode($value->toArray());
    }
}

class User extends Model
{
    protected $casts = [
        'address' => AddressCast::class,
    ];
}
```

## Generated output

The package generates a separate interface for `Address` and references it in `IUser`:

```typescript
export interface IAddress {
    street: string;
    city: string;
    country: string;
    zip?: string | null;
}

export interface IUser {
    id: number;
    address?: IAddress | null;
}
```

## DataObject namespaces

The package looks for DataObject classes in the namespaces configured in `dataobject_namespaces`. By default only `App\DataObjects` is scanned:

```php
'dataobject_namespaces' => [
    'App\\DataObjects',
    'App\\ValueObjects',
    'Domain\\Shared\\ValueObjects',
],
```

Short class names referenced in PHPDoc (e.g. `@var Address`) are resolved by checking each namespace in order.

## Discovering every DataObject

By default a DataObject only gets an interface when it is reachable from a model
cast (directly, or nested through another DataObject's PHPDoc). A DataObject that
no model ever exposes is never generated.

Set `discover_all_dataobjects` to `true` to emit an interface for **every**
DataObject found under `dataobject_namespaces`, regardless of whether a model
references it:

```php
'discover_all_dataobjects' => true,
```

With this enabled:

- Every class under the configured namespaces that exposes `fromArray()` and
  `toArray()` is emitted as `I{ClassName}`. Sub-namespaces are scanned
  recursively.
- Nested DataObjects (referenced through PHPDoc such as
  `@param array<int, OrderLine> $lines`) are resolved and emitted too.
- A DataObject that is *also* exposed by a cast is emitted only once.
- Classes without a constructor are skipped rather than emitted as empty
  interfaces.

### Name collisions

Interfaces are keyed by short class name (`App\DataObjects\Address` →
`IAddress`). If two distinct classes under the configured namespaces resolve to
the same short name, generation stops with a
`DataObjectNameCollisionException` listing the conflicting classes. Rename one of
them or narrow `dataobject_namespaces` to resolve the conflict.
