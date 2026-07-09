<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Aliases\Local;

use OiLab\OiLaravelTs\Tests\Fixtures\Aliases\Vendor\ContentData as VendorContentData;

/**
 * References both colliding DTOs, so nested resolution has to honour the alias.
 */
class PageData
{
    public function __construct(
        public readonly ContentData $localContent,
        public readonly VendorContentData $vendorContent,
    ) {}
}
