<?php

namespace OiLab\OiLaravelTs\Console\Commands;

use Illuminate\Console\Command;

class InstallAiSkillCommand extends Command
{
    protected $signature = 'oi-ts:install-ai-skill';

    protected $description = '[Deprecated] Use `oi:skills` instead. Install AI assistant skill files for oi-laravel-ts';

    private const SKILL_NAME = 'oilab-laravel-ts';

    private const SECTION = 'oi-lab/oi-laravel-ts rules';

    public function handle(): int
    {
        $this->warn('`'.$this->getName().'` is deprecated. Use `php artisan oi:skills` (from oi-lab/oi-laravel-development) instead.');

        if ($this->getApplication()->has('oi:skills')) {
            return $this->call('oi:skills', [
                'skills' => [self::SKILL_NAME],
                '--project' => true,
            ]);
        }

        $this->installFallback();

        return self::SUCCESS;
    }

    private function installFallback(): void
    {
        $stub = __DIR__.'/../../../resources/stubs/ai-skill.md';

        $skillDirs = [
            '.claude/skills/'.self::SKILL_NAME,
            '.junie/skills/'.self::SKILL_NAME,
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
        $sectionHeader = '=== '.self::SECTION.' ===';
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
