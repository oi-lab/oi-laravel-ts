---
title: Multi-file Output
description: Generate one file per interface with an index barrel
section: advanced
order: 4
---

# Multi-file Output

By default the package writes every interface into a single concatenated file at
`output_path`. For larger projects you can split the output into one file per
interface by setting `output_mode` to `'multiple'`:

```php
// config/oi-laravel-ts.php
'output_mode' => 'multiple',
'output_dir' => resource_path('js/types'),
```

In `multiple` mode `output_path` is ignored and files are written to
`output_dir` instead.

## Generated layout

```
resources/js/types/
├── index.ts        // re-exports every interface
├── user.ts
├── post.ts
├── role.ts
└── membership.ts
```

Each interface lives in its own file, and `index.ts` is a barrel that re-exports
all of them:

```typescript
// index.ts
export * from './membership';
export * from './post';
export * from './role';
export * from './user';
```

## File naming

File names are the kebab-case of the interface name, with the leading `I`
stripped. The interfaces keep their `I` prefix in code.

| Interface | File |
|---|---|
| `IUser` | `user.ts` |
| `IUserType` | `user-type.ts` |
| `IOrderLineData` | `order-line-data.ts` |
| `JsonLdRawNode` | `json-ld-raw-node.ts` |

## Imports

Every file imports exactly the interfaces it references — nothing more.
Cross-references between interfaces become relative type imports:

```typescript
// user.ts
import type { IMembership } from './membership';
import type { IPost } from './post';
import type { IRole } from './role';

export interface IUser {
    posts?: IPost[];
    roles?: IRole[];
    memberships?: (IRole & { pivot?: IMembership })[];
}
```

A few details worth noting:

- **Pivot intersections** import both sides — `IRole` and `IMembership` above.
- **External `@/…` types** (from `custom_props`) are re-emitted in each file that
  uses them, so a type referenced only by `IUser` is not imported into `IPost`.
- **JSON-LD** — when `with_json_ld` is enabled, the shared `JsonLdRawNode`
  interface is written to `json-ld-raw-node.ts` and imported by the interfaces
  that use it.

## Importing in your app

Import from the directory; the barrel resolves to `index.ts`:

```typescript
import type { IUser, IPost } from '@/types';

const user: IUser = await fetchUser(1);
```

## Switching back to a single file

Set `output_mode` back to `'single'` (the default). The single-file output is
unchanged from previous versions — same bytes, same `output_path`.
