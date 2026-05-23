<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\Standalone;

/**
 * Standalone DataObject not referenced by any model cast. Used to verify
 * autonomous discovery and nested-DataObject chaining.
 */
class OrderData
{
    /**
     * @param  string  $reference
     * @param  array<int, OrderLineData>  $lines
     * @param  OrderLineData|null  $featuredLine
     */
    public function __construct(
        public string $reference,
        public array $lines = [],
        public ?OrderLineData $featuredLine = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            reference: $data['reference'] ?? '',
            lines: $data['lines'] ?? [],
            featuredLine: $data['featured_line'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'reference' => $this->reference,
            'lines' => $this->lines,
            'featured_line' => $this->featuredLine,
        ];
    }
}
