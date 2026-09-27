<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Discriminated;

/**
 * Stands for a package registry that knows, at runtime, which props class each
 * template uses.
 */
class BlockPropsRegistry
{
    /**
     * @return array<string, class-string>
     */
    public static function propsClasses(): array
    {
        return [
            'hero' => HeroPropsData::class,
            'grid' => GridPropsData::class,
        ];
    }
}
