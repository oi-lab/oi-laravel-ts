<?php

namespace OiLab\OiLaravelTs\Services\Generators;

/**
 * A single generated TypeScript interface as a structured unit.
 *
 * Holds the interface name, its full body (`export interface X { ... }` without
 * trailing newlines) and the names of other interfaces it references. The
 * references drive per-file import generation in multi-file output mode.
 */
class InterfaceUnit
{
    /**
     * @param  string  $name  The interface name, e.g. `IUser`.
     * @param  string  $body  The full interface body without trailing newlines.
     * @param  array<int, string>  $references  Names of other interfaces referenced in the body.
     */
    public function __construct(
        public readonly string $name,
        public readonly string $body,
        public readonly array $references,
    ) {}

    /**
     * Build a unit from its name and body, extracting references automatically.
     *
     * References are every `I{Pascal}` identifier plus the special
     * `JsonLdRawNode` shared type, minus the unit's own name. Over-collection is
     * harmless: the writer only emits imports for references that resolve to a
     * known local or external type.
     */
    public static function make(string $name, string $body): self
    {
        $references = [];

        if (preg_match_all('/\bI[A-Z][a-zA-Z0-9]*/', $body, $matches)) {
            $references = $matches[0];
        }

        if (str_contains($body, 'JsonLdRawNode')) {
            $references[] = 'JsonLdRawNode';
        }

        $references = array_values(array_unique(array_filter(
            $references,
            static fn (string $ref): bool => $ref !== $name
        )));

        return new self($name, $body, $references);
    }
}
