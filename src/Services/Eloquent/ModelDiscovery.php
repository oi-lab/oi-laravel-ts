<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

/**
 * Model Discovery Service
 *
 * Responsible for discovering and collecting all Laravel Eloquent models
 * in the application. Scans the app/Models directory and includes any
 * additional models specified via configuration.
 *
 *
 * @example
 * ```php
 * $discovery = new ModelDiscovery();
 * $discovery->setAdditionalModels([CustomModel::class]);
 * $models = $discovery->discoverModels();
 * // Returns: [['model' => 'User', 'namespace' => 'App\Models\User'], ...]
 * ```
 */
class ModelDiscovery
{
    /**
     * Additional model classes to include beyond the app/Models directory.
     *
     * @var array<int, class-string>
     */
    private array $additionalModels = [];

    /**
     * Set additional model classes to include in the discovery process.
     *
     * These models will be included in addition to the models found
     * in the app/Models directory.
     *
     * @param  array<int, class-string>  $models  Fully qualified class names of additional models
     */
    public function setAdditionalModels(array $models): void
    {
        $this->additionalModels = $models;
    }

    /**
     * Discover all Eloquent models in the application.
     *
     * Scans the app/Models directory for model files and includes
     * any additional models specified via setAdditionalModels().
     *
     * @return array<int, array{model: string, namespace: class-string}> Array of discovered models with their metadata
     *
     * @example
     * ```php
     * $models = $discovery->discoverModels();
     * // [
     * //   ['model' => 'User', 'namespace' => 'App\Models\User'],
     * //   ['model' => 'Post', 'namespace' => 'App\Models\Post'],
     * // ]
     * ```
     */
    public function discoverModels(): array
    {
        $models = [];

        // Scan app/Models directory
        $models = array_merge($models, $this->scanModelsDirectory());

        // Add additional models
        $models = array_merge($models, $this->processAdditionalModels());

        return $models;
    }

    /**
     * Scan the app/Models directory for Eloquent models.
     *
     * Reads all PHP files in the app/Models directory and checks
     * if they are valid model classes.
     *
     * @return array<int, array{model: string, namespace: class-string}> Models found in app/Models
     */
    private function scanModelsDirectory(): array
    {
        $models = [];
        $modelsPath = app_path('Models');

        if (! is_dir($modelsPath)) {
            return $models;
        }

        $files = scandir($modelsPath);

        foreach ($files as $file) {
            if ($file === '.' || $file === '..') {
                continue;
            }

            $modelClass = '\\App\\Models\\'.pathinfo($file, PATHINFO_FILENAME);

            if (class_exists($modelClass)) {
                $models[] = [
                    'model' => pathinfo($file, PATHINFO_FILENAME),
                    'namespace' => $modelClass,
                ];
            }
        }

        return $models;
    }

    /**
     * Process additional models specified via setAdditionalModels().
     *
     * Validates that the specified classes exist and formats them
     * in the same structure as discovered models.
     *
     * @return array<int, array{model: string, namespace: class-string}> Formatted additional models
     */
    private function processAdditionalModels(): array
    {
        $models = [];

        foreach ($this->additionalModels as $namespace) {
            if (class_exists($namespace)) {
                $models[] = [
                    'model' => class_basename($namespace),
                    'namespace' => $namespace,
                ];
            }
        }

        return $models;
    }

    /**
     * Get the list of additional models.
     *
     * @return array<int, class-string> Array of additional model class names
     */
    public function getAdditionalModels(): array
    {
        return $this->additionalModels;
    }
}
