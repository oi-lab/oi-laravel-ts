<?php

namespace OiLab\OiLaravelTs\Console\Commands;

use Illuminate\Console\Command;

class InstallAiSkillCommand extends Command
{
    protected $signature = 'oi:install-ai-skill';

    protected $description = 'Install AI assistant skill files for oi-laravel-ts into the project';

    public function handle(): void
    {
        $stub = __DIR__.'/../../../resources/stubs/ai-skill.md';

        $skillDirs = [
            '.claude/skills/oilab-laravel-ts',
            '.junie/skills/oilab-laravel-ts',
        ];

        foreach ($skillDirs as $dir) {
            $fullPath = base_path($dir);

            if (! is_dir($fullPath)) {
                mkdir($fullPath, 0755, true);
            }

            copy($stub, $fullPath.'/SKILL.md');
            $this->info("Installed: {$dir}/SKILL.md");
        }

        $this->addSkillToClaudeMd();
    }

    private function addSkillToClaudeMd(): void
    {
        $claudeMdPath = base_path('CLAUDE.md');
        $sectionHeader = '=== oi-lab/oi-laravel-ts rules ===';
        $body = file_get_contents(__DIR__.'/../../../resources/stubs/claude-rules.md');
        $newSection = $sectionHeader."\n\n".trim($body)."\n";

        if (! file_exists($claudeMdPath)) {
            file_put_contents($claudeMdPath, $newSection."\n");
            $this->info('Created CLAUDE.md with oi-laravel-ts rules.');

            return;
        }

        $content = file_get_contents($claudeMdPath);

        if (! str_contains($content, $sectionHeader)) {
            $separator = str_ends_with($content, "\n") ? "\n" : "\n\n";
            file_put_contents($claudeMdPath, $content.$separator.$newSection."\n");
            $this->info('Added oi-laravel-ts rules section to CLAUDE.md.');

            return;
        }

        // Replace the existing section: from the header until the next === ... === or EOF.
        // The lookahead \n=== matches the blank line before the next section header, so
        // the replacement naturally preserves inter-section spacing.
        $escaped = preg_quote($sectionHeader, '#');
        $updated = preg_replace(
            '#'.$escaped.'.*?(?=\n===|\z)#s',
            $newSection,
            $content
        );

        file_put_contents($claudeMdPath, $updated);
        $this->info('Updated oi-laravel-ts rules section in CLAUDE.md.');
    }
}
