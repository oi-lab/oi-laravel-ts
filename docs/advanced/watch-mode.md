---
title: Watch Mode
description: Automatically regenerate interfaces when models change
section: advanced
order: 1
---

# Watch Mode

Watch mode monitors your models directory and automatically regenerates the TypeScript file whenever a model file changes. It's designed for development workflows where you want instant feedback.

## Starting the watcher

```bash
php artisan oi:gen-ts --watch
```

The command stays running, watching for file changes in your models directory. When a change is detected, it regenerates the output file and reports the result.

## How it works

The watcher monitors all `.php` files in `app/Models`, recursively including
sub-directories. When any file is modified, the full generation pipeline runs
again.

When `discover_all_dataobjects` is enabled, the directories backing your
`dataobject_namespaces` are watched too, so editing a standalone DataObject also
triggers regeneration.

## Typical workflow

Open two terminal windows:

**Terminal 1 — frontend dev server:**
```bash
npm run dev
```

**Terminal 2 — model watcher:**
```bash
php artisan oi:gen-ts --watch
```

Now when you update a model, the TypeScript interfaces are regenerated automatically, and your frontend dev server picks up the new types without any manual steps.

## Stopping the watcher

Press `Ctrl+C` to stop the watcher.
