<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Collisions\Beta;

/**
 * Collides on short name with the Alpha variant to exercise collision detection.
 */
class PointData
{
    public function __construct(
        public float $latitude,
        public float $longitude,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(
            latitude: $data['latitude'] ?? 0.0,
            longitude: $data['longitude'] ?? 0.0,
        );
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['latitude' => $this->latitude, 'longitude' => $this->longitude];
    }
}
