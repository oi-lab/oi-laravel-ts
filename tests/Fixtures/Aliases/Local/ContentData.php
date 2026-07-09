<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Aliases\Local;

/**
 * The application's own ContentData. Keeps the unprefixed `IContentData` name.
 */
class ContentData
{
    public function __construct(
        public readonly string $body,
    ) {}
}
