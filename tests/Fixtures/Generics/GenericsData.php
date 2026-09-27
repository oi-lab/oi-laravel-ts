<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Generics;

use Illuminate\Support\Collection;

/**
 * Every PHPDoc spelling of "a JSON array of T" or "a JSON object of T" a DTO
 * may use, so each can be asserted to resolve rather than fall to `unknown`.
 */
class GenericsData
{
    /**
     * @param  list<string>  $keywords
     * @param  non-empty-list<TagData>  $tags
     * @param  Collection<int, TagData>  $collected
     * @param  array<int, string|int>  $mixedIds
     * @param  list<list<string>>  $grid
     * @param  array<string, list<int>>  $byKey
     * @param  non-empty-array<string, TagData>  $tagsByKey
     * @param  iterable<int>  $numbers
     */
    public function __construct(
        public readonly array $keywords,
        public readonly array $tags,
        public readonly Collection $collected,
        public readonly array $mixedIds,
        public readonly array $grid,
        public readonly array $byKey,
        public readonly array $tagsByKey,
        public readonly iterable $numbers,
    ) {}
}
