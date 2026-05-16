---
title: External Type Imports
description: Reference TypeScript types from external files in your custom properties
section: advanced
order: 3
---

# External Type Imports

When defining `custom_props`, you can reference TypeScript types from other files using the `path|TypeName` syntax. The generator adds the appropriate import statement to the output file.

## Syntax

```
'path/to/module|TypeName'
```

- **path/to/module** — the import path (relative or absolute, using `@/` aliases)
- **TypeName** — the named export to import from that module

## Example

```php
// config/oi-laravel-ts.php
'custom_props' => [
    'Page' => [
        'layout' => '@/types/layouts|LayoutConfig',
        'blocks' => '@/types/blocks|ContentBlock',
    ],
    'User' => [
        'preferences' => '@/types/preferences|UserPreferences',
    ],
],
```

Generated output:

```typescript
import { LayoutConfig } from '@/types/layouts';
import { ContentBlock } from '@/types/blocks';
import { UserPreferences } from '@/types/preferences';

export interface IPage {
    id: number;
    layout: LayoutConfig;
    blocks: ContentBlock;
    // ...
}

export interface IUser {
    id: number;
    preferences: UserPreferences;
    // ...
}
```

## Named imports only

Only named exports are supported. Default imports are not handled by this feature. If you need a default import, add it manually to the generated file or wrap it in a re-export:

```typescript
// @/types/my-type.ts
export { default as MyType } from './my-type-impl';
```

Then reference it as `@/types/my-type|MyType`.
