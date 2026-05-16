---
title: Overview
description: How to generate and use TypeScript interfaces
section: usage
order: 1
---

# Usage

## Generating interfaces

Run the Artisan command to generate the TypeScript file:

```bash
php artisan oi:gen-ts
```

The generator scans all models in `app/Models`, builds a type schema, and writes the output file (default: `resources/js/types/interfaces.ts`).

## Using in TypeScript

Import interfaces directly from the generated file:

```typescript
import type { IUser, IPost } from '@/types/interfaces';

const user: IUser = await fetchUser(1);
const posts: IPost[] = user.posts ?? [];
```

All interfaces are prefixed with `I` by convention (e.g. `IUser`, `IPost`, `IRole`).

## Inertia.js integration

With Inertia.js, type your page props using the generated interfaces:

```typescript
import type { PageProps } from '@inertiajs/core';
import type { IUser } from '@/types/interfaces';

interface Props extends PageProps {
    user: IUser;
    posts: IPost[];
}

export default function Dashboard({ user, posts }: Props) {
    return <h1>Welcome, {user.name}</h1>;
}
```

## What gets included

The generator produces an interface for every discovered model. Each interface includes:

- **Database columns** — with types inferred from the database schema
- **Cast attributes** — resolved to their PHP return type, then converted to TypeScript
- **Relationships** — as optional typed arrays or single-object references
- **Relationship counts** — `{relation}_count?: number` (if `with_counts` is enabled)
- **Custom props** — any properties defined in `custom_props` config

See the individual pages for details on [relationships](relationships.md), [custom casts](custom-casts.md), and [DataObjects](data-objects.md).
