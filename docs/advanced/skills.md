---
title: AI Assistant Skills
description: Automatically provide AI coding assistants with context about this package
section: advanced
order: 5
---

# AI Assistant Skills

When working with an AI coding assistant (Claude Code, JetBrains AI, etc.) in a Laravel project that uses oi-laravel-ts, the AI needs to know two critical things:

1. The generated `interfaces.ts` file must never be modified manually.
2. TypeScript changes are driven by running `php artisan oi:gen-ts`.

The package ships a skill file that communicates this context automatically.

## Installing the skill

The recommended way to install skills is the unified `oi:skills` command (provided by `oi-lab/oi-laravel-development`):

```bash
php artisan oi:skills
```

It discovers the skills declared by every installed `oi-lab/*` package and lets you pick which ones to install via an interactive multiselect picker, choosing whether to install them in the project (`.claude` + `.junie`) or your Claude Code user profile (`~/.claude`).

To install only this package's skill non-interactively:

```bash
php artisan oi:skills oilab-laravel-ts --project
# or, into your Claude Code user profile:
php artisan oi:skills oilab-laravel-ts --global
```

This command:
- Copies the skill to `.claude/skills/oilab-laravel-ts/` (Claude Code)
- Copies the skill to `.junie/skills/oilab-laravel-ts/` (JetBrains AI)
- Adds (or refreshes) the `=== oi-lab/oi-laravel-ts rules ===` section in your `CLAUDE.md`

> A package-local command `php artisan oi-ts:install-ai-skill` is still available for projects that don't use `oi-lab/oi-laravel-development`, but it is **deprecated** in favor of `oi:skills`.

## Keeping the skill up to date

When you update the package, re-run the command to pull in the latest skill content:

```bash
php artisan oi:skills oilab-laravel-ts --project
```

To automate this, add the command to your project's `post-autoload-dump` script in `composer.json`:

```json
"scripts": {
    "post-autoload-dump": [
        "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
        "@php artisan package:discover --ansi",
        "@php artisan oi:skills oilab-laravel-ts --project --quiet"
    ]
}
```

This requires `oi-lab/oi-laravel-development` to be installed.

## What the skill tells the AI

The skill file instructs the AI assistant to:

- Never edit `resources/js/types/interfaces.ts` (or the configured `output_path`) directly.
- Use `php artisan oi:gen-ts` to regenerate interfaces after model changes.
- Configure output behaviour through `config/oi-laravel-ts.php`.

## Manual publishing

If you only need the skill file without touching `CLAUDE.md`, you can publish it via the standard vendor publish mechanism:

```bash
php artisan vendor:publish --tag=oi-laravel-ts-skill
```

This copies the skill to `.claude/skills/oilab-laravel-ts/skill.md`. You can then reference it manually in your `CLAUDE.md`:

```
@.claude/skills/oilab-laravel-ts/skill.md
```
