<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\PackageModels;

/**
 * A plain class sharing the models namespace. Must never be emitted.
 */
class PublishHelper
{
    public function slugify(string $value): string
    {
        return strtolower($value);
    }
}
