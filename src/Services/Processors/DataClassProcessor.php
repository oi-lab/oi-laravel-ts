<?php

namespace OiLab\OiLaravelTs\Services\Processors;

use OiLab\OiLaravelTs\Services\DataClassResolver;
use OiLab\OiLaravelTs\Services\Eloquent\DataClassAnalyzer;
use OiLab\OiLaravelTs\Services\Generators\InterfaceUnit;
use ReflectionClass;
use ReflectionException;

/**
 * Data Class Processor
 *
 * Converts spatie/laravel-data style DTOs into TypeScript interfaces named
 * `I{ClassName}`. Nested DTOs referenced by a property are discovered and
 * queued so the full graph is emitted.
 */
class DataClassProcessor
{
    /**
     * Short names of DTOs already emitted, to avoid duplicates.
     *
     * @var array<int, string>
     */
    private array $processed = [];

    /**
     * Queue of DTO class names pending processing.
     *
     * @var array<int, string>
     */
    private array $pending = [];

    /**
     * Generated interface units, in processing order.
     *
     * @var array<int, InterfaceUnit>
     */
    private array $units = [];

    public function __construct(
        private readonly DataClassAnalyzer $analyzer,
        private readonly DataClassResolver $resolver,
    ) {}

    /**
     * Enqueue a DTO class for processing.
     */
    public function enqueue(string $dataClass): void
    {
        $shortName = class_basename($dataClass);

        if (in_array($shortName, $this->processed, true) || in_array($dataClass, $this->pending, true)) {
            return;
        }

        $this->pending[] = $dataClass;
    }

    public function hasPending(): bool
    {
        return $this->pending !== [];
    }

    public function getNextPending(): ?string
    {
        return array_shift($this->pending);
    }

    /**
     * Process a DTO class: emit its interface and queue nested DTOs.
     */
    public function process(string $dataClass): void
    {
        $shortName = class_basename($dataClass);

        if (in_array($shortName, $this->processed, true)) {
            return;
        }

        $this->processed[] = $shortName;

        if (! class_exists($dataClass)) {
            return;
        }

        try {
            $reflection = new ReflectionClass($dataClass);
        } catch (ReflectionException) {
            return;
        }

        $properties = $this->analyzer->extractProperties($reflection);

        if ($properties === []) {
            return;
        }

        $interfaceName = "I{$shortName}";
        $body = "export interface {$interfaceName} {\n";

        foreach ($properties as $property) {
            $optional = $property['nullable'] || $property['hasDefault'];

            $this->detectNested($property['type']);

            $body .= "    {$property['name']}".($optional ? '?' : '').": {$property['type']};\n";
        }

        $body .= '}';

        $this->units[] = InterfaceUnit::make($interfaceName, $body);
    }

    /**
     * Detect nested DTO references (`I{Name}`) in a TypeScript type and queue them.
     */
    public function detectNested(string $tsType): void
    {
        if (! preg_match_all('/I([A-Z][a-zA-Z0-9]+)/', $tsType, $matches)) {
            return;
        }

        foreach ($matches[1] as $shortName) {
            $dataClass = $this->resolver->resolveDataClass($shortName);

            if ($dataClass === null) {
                continue;
            }

            $this->enqueue($dataClass);
        }
    }

    /**
     * @return array<int, InterfaceUnit>
     */
    public function getUnits(): array
    {
        return $this->units;
    }

    public function getOutput(): string
    {
        $output = '';

        foreach ($this->units as $unit) {
            $output .= $unit->body."\n\n";
        }

        return $output;
    }

    public function reset(): void
    {
        $this->processed = [];
        $this->pending = [];
        $this->units = [];
    }
}
