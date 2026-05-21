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

            copy($stub, $fullPath.'/skill.md');
            $this->info("Installed: {$dir}/skill.md");
        }

        $this->addSkillToClaudeMd();
    }

    private function addSkillToClaudeMd(): void
    {
        $claudeMdPath = base_path('CLAUDE.md');
        $import = '@.claude/skills/oilab-laravel-ts/skill.md';

        if (file_exists($claudeMdPath)) {
            $content = file_get_contents($claudeMdPath);

            if (str_contains($content, $import)) {
                $this->line('CLAUDE.md already references the skill — skipping.');

                return;
            }

            file_put_contents($claudeMdPath, $import."\n".$content);
        } else {
            file_put_contents($claudeMdPath, $import."\n");
        }

        $this->info('Added skill reference to CLAUDE.md.');
    }
}
