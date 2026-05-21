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

Run the install command once after adding the package:

```bash
php artisan oi:install-ai-skill
```

This command:
- Creates `.claude/skills/oilab-laravel-ts/skill.md` (Claude Code)
- Creates `.junie/skills/oilab-laravel-ts/skill.md` (JetBrains AI)
- Prepends `@.claude/skills/oilab-laravel-ts/skill.md` to your `CLAUDE.md`, importing the skill automatically

## Keeping the skill up to date

When you update the package, re-run the command to pull in the latest skill content:

```bash
php artisan oi:install-ai-skill
```

To automate this, add the command to your project's `post-autoload-dump` script in `composer.json`:

```json
"scripts": {
    "post-autoload-dump": [
        "Illuminate\\Foundation\\ComposerScripts::postAutoloadDump",
        "@php artisan package:discover --ansi",
        "@php artisan oi:install-ai-skill --quiet"
    ]
}
```

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
