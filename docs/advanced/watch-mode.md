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

The watcher monitors all `.php` files in `app/Models` (and any additional paths configured for model discovery). When any file is modified, the full generation pipeline runs again.

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
