<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Nullability;

use OiLab\OiLaravelTs\Tests\Fixtures\Support\Lazy;
use OiLab\OiLaravelTs\Tests\Fixtures\Support\Optional;

/**
 * Covers the whole declaration surface a DTO property can have, so the two axes
 * — "the key may be absent" (`?`) and "the value may be null" (`| null`) — can
 * be asserted independently of each other.
 */
class SignatureData
{
    /**
     * @param  AlphaData|BetaData|array<string, mixed>  $props
     */
    public function __construct(
        public readonly string $c,
        public readonly ?string $b,
        public readonly AlphaData|BetaData|null $h,
        public readonly array $props,
        public readonly string|Optional $g,
        public readonly Lazy|int $lazyCount,
        public readonly ?string $a = null,
        public readonly int $d = 3,
        /** @var FooData[] */
        public readonly array $e = [],
        public readonly ?FooData $f = null,
    ) {}
}
