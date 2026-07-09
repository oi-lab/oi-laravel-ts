<?php

namespace OiLab\OiLaravelTs\Services\Eloquent;

use Illuminate\Database\Eloquent\Model;
use OiLab\OiLaravelTs\Services\Concerns\ScansPsr4Namespaces;
use ReflectionClass;
use ReflectionException;

/**
 * Model Discovery Service
 *
 * Responsible for discovering and collecting all Laravel Eloquent models
 * in the application. Scans the app/Models directory, walks any namespace
 * listed as included, and adds any model specified explicitly.
 *
 *
 * @example
 * ```php
 * $discovery = new ModelDiscovery();
 * $discovery->setAdditionalModels([CustomModel::class]);
 * $discovery->setIncludedNamespaces(['OiLab\OiLaravelPublish\Models']);
 * $models = $discovery->discoverModels();
 * // Returns: [['model' => 'User', 'namespace' => 'App\Models\User'], ...]
 * ```
 */
class ModelDiscovery
{
    use ScansPsr4Namespaces;

    /**
     * Additional model classes to include beyond the app/Models directory.
     *
     * @var array<int, class-string>
     */
    private array $additionalModels = [];

    /**
     * Namespace prefixes whose Eloquent models join the schema as if they lived
     * in app/Models.
     *
     * @var array<int, string>
     */
    private array $includedNamespaces = [];

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
     * Set namespaces whose Eloquent models are added to the schema wholesale.
     *
     * This is the positive counterpart to `excluded_namespaces`: it makes a
     * package's models discoverable even when no application model points at
     * them through a relationship.
     *
     * @param  array<int, string>  $namespaces  Fully-qualified namespace prefixes
     */
    public function setIncludedNamespaces(array $namespaces): void
    {
        $this->includedNamespaces = $namespaces;
    }

    /**
     * Discover all Eloquent models in the application.
     *
     * Scans the app/Models directory for model files, walks every included
     * namespace, and includes any model specified via setAdditionalModels().
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

        // Walk the included package namespaces
        $models = array_merge($models, $this->scanIncludedNamespaces());

        // Add additional models
        $models = array_merge($models, $this->processAdditionalModels());

        return $models;
    }

    /**
     * Walk every included namespace and collect the Eloquent models it declares.
     *
     * Non-model classes, abstract models and unloadable files are skipped.
     * Exclusion is not applied here — SchemaBuilder drops excluded-namespace
     * models later, whichever way they entered the queue.
     *
     * @return array<int, array{model: string, namespace: class-string}>
     */
    private function scanIncludedNamespaces(): array
    {
        if ($this->includedNamespaces === []) {
            return [];
        }

        $psr4 = $this->getPsr4Prefixes();
        $models = [];

        foreach ($this->includedNamespaces as $namespace) {
            foreach ($this->classesInNamespace(trim($namespace, '\\'), $psr4) as $class) {
                if (! $this->isConcreteModel($class)) {
                    continue;
                }

                $models[] = [
                    'model' => class_basename($class),
                    'namespace' => $class,
                ];
            }
        }

        return $models;
    }

    /**
     * Whether a class is an instantiable Eloquent model.
     */
    private function isConcreteModel(string $class): bool
    {
        if (! class_exists($class) || ! is_subclass_of($class, Model::class)) {
            return false;
        }

        try {
            return ! (new ReflectionClass($class))->isAbstract();
        } catch (ReflectionException) {
            return false;
        }
    }

    /**
     * Get the included namespace prefixes.
     *
     * @return array<int, string>
     */
    public function getIncludedNamespaces(): array
    {
        return $this->includedNamespaces;
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
