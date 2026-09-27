<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Discriminated;

class BlockData
{
    /**
     * @param  HeroPropsData|GridPropsData|array<string, mixed>  $props
     */
    public function __construct(
        public readonly int $id,
        public readonly string $template_key,
        public readonly array $props,
        public readonly ?string $name = null,
    ) {}
}
