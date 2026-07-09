<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Aliases\Vendor;

/**
 * A package's ContentData, colliding on short name with the application's own.
 * Disambiguated through `data_aliases` rather than by renaming the PHP class.
 */
class ContentData
{
    public function __construct(
        public readonly string $html,
    ) {}
}
