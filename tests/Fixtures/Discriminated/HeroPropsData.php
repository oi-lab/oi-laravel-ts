<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Discriminated;

class HeroPropsData
{
    public function __construct(
        public readonly ?string $hero_title = null,
    ) {}
}
