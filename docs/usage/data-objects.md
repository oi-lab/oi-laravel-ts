---
title: DataObjects
description: Automatic interface generation for PHP DataObjects and Value Objects
section: usage
order: 4
---

# DataObjects

The package automatically generates TypeScript interfaces for PHP DataObjects (also called Value Objects or DTOs) when they are used as custom casts.

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
