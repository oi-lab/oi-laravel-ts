<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\Standalone\Deep;

/**
 * Lives in a sub-namespace to verify recursive PSR-4 discovery.
 */
class ShippingData
{
    public function __construct(
        public string $carrier,
        public ?string $trackingNumber = null,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            carrier: $data['carrier'] ?? '',
            trackingNumber: $data['tracking_number'] ?? null,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [
            'carrier' => $this->carrier,
            'tracking_number' => $this->trackingNumber,
        ];
    }
}
