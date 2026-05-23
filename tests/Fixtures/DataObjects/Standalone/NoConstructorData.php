<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\Standalone;

/**
 * DataObject contract without a constructor. Discovery must skip it rather than
 * emit an empty interface.
 */
class NoConstructorData
{
    /**
     * @param  array<string, mixed>  $data
     */
    public static function fromArray(array $data): self
    {
        return new self;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(): array
    {
        return [];
    }
}
