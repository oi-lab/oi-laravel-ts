<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Nullability;

class FooData
{
    public function __construct(
        public readonly string $label,
    ) {}
}
