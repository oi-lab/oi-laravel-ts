<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Collisions\Alpha;

/**
 * Collides on short name with the Beta variant to exercise collision detection.
 */
class PointData
{
    public function __construct(
        public int $x,
        public int $y,
    ) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self(x: $data['x'] ?? 0, y: $data['y'] ?? 0);
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return ['x' => $this->x, 'y' => $this->y];
    }
}
