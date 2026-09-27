<?php

namespace OiLab\OiLaravelTs\Services\Processors;

use OiLab\OiLaravelTs\Services\DataClassResolver;
use OiLab\OiLaravelTs\Services\Eloquent\DataClassAnalyzer;
use OiLab\OiLaravelTs\Services\Generators\InterfaceUnit;
use OiLab\OiLaravelTs\Services\Support\Declaration;
use OiLab\OiLaravelTs\Services\Support\PropertyRenderer;
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

    private readonly PropertyRenderer $renderer;

    public function __construct(
        private readonly DataClassAnalyzer $analyzer,
        private readonly DataClassResolver $resolver,
        ?PropertyRenderer $renderer = null,
    ) {
        $this->renderer = $renderer ?? new PropertyRenderer;
    }

    /**
     * Enqueue a DTO class for processing.
     */
    public function enqueue(string $dataClass): void
    {
        $shortName = $this->resolver->shortName($dataClass);

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
        $shortName = $this->resolver->shortName($dataClass);

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

        $discriminator = $this->discriminatorFor($dataClass, $properties);

        if ($discriminator !== null) {
            $this->emitDiscriminated($interfaceName, $properties, ...$discriminator);

            return;
        }

        $declaration = Declaration::fromConfig();
        $body = $declaration->open($interfaceName);

        foreach ($properties as $property) {
            $this->detectNested($property['type']);

            $body .= '    '.$this->renderer->render($property)."\n";
        }

        $body .= $declaration->close();

        $this->units[] = InterfaceUnit::make($interfaceName, $body);
    }

    /**
     * The discriminator declared for a DTO in `data_discriminators`, once its
     * map is resolved and both named properties are found on the DTO.
     *
     * @param  array<int, array{name: string, type: string, nullable: bool, hasDefault: bool, optional: bool}>  $properties
     * @return array{0: string, 1: string, 2: array<string, class-string>}|null [discriminant, property, map]
     */
    private function discriminatorFor(string $dataClass, array $properties): ?array
    {
        $definitions = function_exists('config') ? (array) config('oi-laravel-ts.data_discriminators', []) : [];
        $definition = null;

        foreach ($definitions as $class => $candidate) {
            if (ltrim((string) $class, '\\') === ltrim($dataClass, '\\')) {
                $definition = $candidate;

                break;
            }
        }

        if (! is_array($definition)) {
            return null;
        }

        $discriminant = $definition['discriminant'] ?? null;
        $property = $definition['property'] ?? null;
        $names = array_column($properties, 'name');

        if (! is_string($discriminant) || ! is_string($property)
            || ! in_array($discriminant, $names, true) || ! in_array($property, $names, true)) {
            return null;
        }

        $map = $this->resolveMap($definition['map'] ?? []);

        return $map === [] ? null : [$discriminant, $property, $map];
    }

    /**
     * Resolve a discriminator map: a literal `value => DTO class` array, or a
     * callable returning one — `[Class::class, 'method']`, an invokable class
     * name — called through the container at generation time, so a package can
     * derive the map from its own registry.
     *
     * @return array<string, class-string>
     */
    private function resolveMap(mixed $map): array
    {
        $isCallablePair = is_array($map) && array_is_list($map) && count($map) === 2
            && is_string($map[0]) && is_string($map[1]) && method_exists($map[0], $map[1]);

        if ($isCallablePair || $map instanceof \Closure) {
            $map = app()->call($map);
        } elseif (is_string($map) && class_exists($map) && method_exists($map, '__invoke')) {
            $map = app()->call([app($map), '__invoke']);
        }

        if (! is_array($map)) {
            return [];
        }

        $resolved = [];

        foreach ($map as $value => $class) {
            if (is_string($class) && $class !== '') {
                $resolved[(string) $value] = ltrim($class, '\\');
            }
        }

        return $resolved;
    }

    /**
     * Emit a DTO as a discriminated union.
     *
     * Three declarations come out of it:
     * - `I{X}Base`: every property but the discriminant and the discriminated one;
     * - `I{X}{Property}Map`: discriminant value => the discriminated property's type,
     *   for registries keyed on the discriminant;
     * - `I{X}`: the base, intersected with one member per discriminant value, so
     *   that narrowing on the discriminant narrows the property too.
     *
     * @param  array<int, array{name: string, type: string, nullable: bool, hasDefault: bool, optional: bool}>  $properties
     * @param  array<string, class-string>  $map
     */
    private function emitDiscriminated(
        string $interfaceName,
        array $properties,
        string $discriminant,
        string $propertyName,
        array $map,
    ): void {
        $declaration = Declaration::fromConfig();
        $baseName = "{$interfaceName}Base";
        $mapName = $interfaceName.str_replace(' ', '', ucwords(str_replace(['_', '-'], ' ', $propertyName))).'Map';

        $base = $declaration->open($baseName);
        $discriminated = null;

        foreach ($properties as $property) {
            if ($property['name'] === $propertyName) {
                $discriminated = $property;

                continue;
            }

            if ($property['name'] === $discriminant) {
                continue;
            }

            $this->detectNested($property['type']);

            $base .= '    '.$this->renderer->render($property)."\n";
        }

        $base .= $declaration->close();

        $mapBody = "export type {$mapName} = {\n";
        $members = '';

        foreach ($map as $value => $class) {
            $dataClass = $this->resolver->resolveDataClass($class);
            $type = $dataClass !== null ? $this->resolver->interfaceName($dataClass) : 'Record<string, unknown>';

            if ($dataClass !== null) {
                $this->enqueue($dataClass);
            }

            $literal = "'".str_replace("'", "\\'", $value)."'";
            $member = $this->renderer->render([...$discriminated, 'type' => $type]);

            $mapBody .= "    {$literal}: {$type};\n";
            $members .= "    | { {$discriminant}: {$literal}; ".rtrim($member, ';')." }\n";
        }

        $mapBody .= '};';

        $union = "export type {$interfaceName} = {$baseName} & (\n{$members});";

        $this->units[] = InterfaceUnit::make($baseName, $base);
        $this->units[] = InterfaceUnit::make($mapName, $mapBody);
        $this->units[] = InterfaceUnit::make($interfaceName, $union);
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
