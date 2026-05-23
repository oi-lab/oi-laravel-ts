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

        $paths = $this->watchedPaths();
        $lastHash = $this->getWatchedHash($paths);

        while (true) {
            $newHash = $this->getWatchedHash($paths);

            if ($newHash !== $lastHash) {
                $this->generate();
                $lastHash = $newHash;
            }

            sleep(2);
        }
    }

    /**
     * Directories to watch for changes. Always includes the Models directory;
     * when DataObject discovery is enabled, the configured namespaces are added
     * so editing a standalone DataObject also triggers regeneration.
     *
     * @return array<int, string>
     */
    private function watchedPaths(): array
    {
        $config = config('oi-laravel-ts');
        $paths = [app_path('Models')];

        if ($config['discover_all_dataobjects'] ?? false) {
            $resolver = new \OiLab\OiLaravelTs\Services\DataObjectResolver(
                $config['dataobject_namespaces'] ?? null
            );

            foreach ($resolver->resolveNamespaceDirectories() as $directory) {
                $paths[] = $directory;
            }
        }

        return $paths;
    }

    /**
     * @param  array<int, string>  $paths
     */
    private function getWatchedHash(array $paths): string
    {
        $hash = '';

        foreach ($paths as $path) {
            $hash .= $this->getDirectoryHash($path);
        }

        return md5($hash);
    }

    private function getDirectoryHash(string $dir): string
    {
        if (! is_dir($dir)) {
            return '';
        }

        $iterator = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($dir, \FilesystemIterator::SKIP_DOTS)
        );

        $hashes = [];

        foreach ($iterator as $file) {
            if ($file->isFile()) {
                $hashes[$file->getPathname()] = md5_file($file->getPathname());
            }
        }

        ksort($hashes);

        return md5(implode('', $hashes));
    }

    private function generate(): void
    {
        $config = config('oi-laravel-ts');

        Eloquent::setWithCounts($config['with_counts']);
        Eloquent::setCustomProps($config['custom_props']);
        Eloquent::setDiscoverRelatedModels($config['discover_related_models'] ?? true);

        $schema = Eloquent::getSchema();

        if ($config['save_schema']) {
            Storage::disk('local')->put('dev/schema.json', json_encode($schema, JSON_PRETTY_PRINT));
        }

        $converter = new Convert(
            $schema,
            $config['with_json_ld'],
            $config['discover_all_dataobjects'] ?? false,
        );

        if (($config['output_mode'] ?? 'single') === 'multiple') {
            $converter->generateFiles($config['output_dir'] ?? resource_path('js/types'));
        } else {
            $converter->generateFile($config['output_path']);
        }

        $this->info('TypeScript types generated successfully!');
    }
}
