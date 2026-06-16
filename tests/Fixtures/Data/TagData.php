<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Data;

class TagData
{
    public function __construct(
        public readonly string $id,
        public readonly string $name,
        public readonly ?string $description,
    ) {}
}
