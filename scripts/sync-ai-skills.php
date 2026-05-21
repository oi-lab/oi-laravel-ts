<?php

/**
 * Sync the canonical ai-skill.md stub to all AI assistant skill directories.
 * Run via: composer sync-ai-skills
 */

$root = dirname(__DIR__);
$stub = $root . '/resources/stubs/ai-skill.md';

$targets = [
    $root . '/.claude/skills/oilab-laravel-ts/skill.md',
    $root . '/.junie/skills/oilab-laravel-ts/skill.md',
];

foreach ($targets as $target) {
    $dir = dirname($target);

    if (! is_dir($dir)) {
        mkdir($dir, 0755, true);
    }

    copy($stub, $target);
    echo "Synced: " . str_replace($root . '/', '', $target) . PHP_EOL;
}
