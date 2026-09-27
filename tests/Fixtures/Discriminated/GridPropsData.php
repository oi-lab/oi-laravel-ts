<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Discriminated;

class GridPropsData
{
    public function __construct(
        public readonly ?string $grid_title = null,
    ) {}
}
