<?php

namespace OiLab\OiLaravelTs\Console\Commands;

use OiLab\OiLaravelTs\Services\Convert;
use OiLab\OiLaravelTs\Services\Eloquent;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;

class GenerateTypescriptCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'oi:gen-ts {--watch}';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Generate TypeScript interfaces from Laravel models';

    /**
     * Execute the console command.
     */
    public function handle(): void
    {
        if ($this->option('watch')) {
            $this->watchAndGenerate();

            return;
        }

        $this->generate();
    }

    private function watchAndGenerate(): void
    {
        $this->info('Watching for changes in Models directory...');

        $modelsPath = app_path('Models');
        $lastHash = $this->getDirectoryHash($modelsPath);

        while (true) {
            $newHash = $this->getDirectoryHash($modelsPath);

            if ($newHash !== $lastHash) {
                $this->generate();
                $lastHash = $newHash;
            }

            sleep(2);
        }
    }

    private function getDirectoryHash(string $dir): string
    {
        $files = scandir($dir);
        $hash = '';

        foreach ($files as $file) {
            if ($file !== '.' && $file !== '..') {
                $path = $dir.'/'.$file;
                $hash .= md5_file($path);
            }
        }

        return md5($hash);
    }

    private function generate(): void
    {
        $config = config('oi-laravel-ts');

        Eloquent::setWithCounts($config['with_counts']);
        Eloquent::setCustomProps($config['custom_props']);

        $schema = Eloquent::getSchema();

        if ($config['save_schema']) {
            Storage::disk('local')->put('dev/schema.json', json_encode($schema, JSON_PRETTY_PRINT));
        }

        $converter = new Convert($schema, $config['with_json_ld']);
        $converter->generateFile($config['output_path']);

        $this->info('TypeScript types generated successfully!');
    }
}
