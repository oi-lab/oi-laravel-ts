<?php

namespace OiLab\OiLaravelTs\Support;

use BackedEnum;

/**
 * Enum Type Resolver
 *
 * Converts PHP enums to TypeScript literal-union types:
 * - Backed enums use their case values (`'draft' | 'published'`, `1 | 2`).
 * - Pure enums use their case names as string literals (`'Active' | 'Inactive'`).
 */
class EnumTypeResolver
{
    /**
     * Determine whether the given class name is a PHP enum.
     */
    public static function isEnum(string $className): bool
    {
        return enum_exists(ltrim($className, '\\'));
    }

    /**
     * Convert an enum class to a TypeScript literal-union type.
     *
     * Returns null when the class is not an enum so callers can fall back to
     * their default type resolution.
     */
    public static function toTypeScript(string $className): ?string
    {
        $className = ltrim($className, '\\');

        if (! self::isEnum($className)) {
            return null;
        }

        /** @var array<int, \UnitEnum> $cases */
        $cases = $className::cases();

        if ($cases === []) {
            return 'never';
        }

        $literals = [];

        foreach ($cases as $case) {
            if ($case instanceof BackedEnum) {
                $literals[] = is_int($case->value) ? (string) $case->value : "'".$case->value."'";

                continue;
            }

            $literals[] = "'".$case->name."'";
        }

        return implode(' | ', array_values(array_unique($literals)));
    }
}
