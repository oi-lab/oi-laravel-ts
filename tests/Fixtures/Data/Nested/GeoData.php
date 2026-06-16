<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Data\Nested;

/**
 * DTO living in a sub-namespace to exercise recursive discovery and nested
 * resolution. Mimics a spatie/laravel-data Data object without the dependency.
 */
class GeoData
{
    public function __construct(
        public readonly string $street,
        public readonly string $city,
        public readonly ?string $zipCode,
    ) {}
}
