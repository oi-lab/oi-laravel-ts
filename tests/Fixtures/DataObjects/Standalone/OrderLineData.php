<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\Standalone;

/**
 * Nested DataObject reached only through OrderData's PHPDoc annotations.
 */
class OrderLineData
{
    public function __construct(
        public string $sku,
        public int $quantity = 1,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            sku: $data['sku'] ?? '',
            quantity: $data['quantity'] ?? 1,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'sku' => $this->sku,
            'quantity' => $this->quantity,
        ];
    }
}
