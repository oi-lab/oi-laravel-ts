---
title: JSON-LD Support
description: Generate JSON-LD compatible TypeScript structures
section: advanced
order: 2
---

# JSON-LD Support

When `with_json_ld` is enabled, the package adds a `JsonLdRawNode` interface to the generated file. This is useful when your application works with structured data following the JSON-LD specification.

> In [multiple output mode](multi-file-output.md), `JsonLdRawNode` is written to
> its own `json-ld-raw-node.ts` file and imported by the interfaces that use it.

## Enabling JSON-LD

```php
// config/oi-laravel-ts.php
'with_json_ld' => true,
```

## Generated interface

With JSON-LD enabled, the following base interface is added:

```typescript
export interface JsonLdRawNode {
    '@context'?: string | Record<string, unknown>;
    '@type'?: string | string[];
    '@id'?: string;
    [key: string]: unknown;
}
```

## Usage example

Use `JsonLdRawNode` when working with schema.org data or other JSON-LD structures in your frontend:

```typescript
import type { JsonLdRawNode, IProduct } from '@/types/interfaces';

interface ProductPage {
    product: IProduct;
    jsonLd: JsonLdRawNode;
}

const schema: JsonLdRawNode = {
    '@context': 'https://schema.org',
    '@type': 'Product',
    name: product.name,
    description: product.description,
};
```
