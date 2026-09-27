<?php

namespace OiLab\OiLaravelTs\Support;

/**
 * Generic Type Normalizer
 *
 * Rewrites the PHPDoc generics that serialize to a JSON array onto the one
 * spelling the converters understand, `array<int, T>`, and those that
 * serialize to a JSON object onto `array<string, T>`.
 *
 * Without it, `list<string>` or `Collection<int, FooData>` reach the leaf
 * resolver as an unknown class name and come out as `unknown`, silently.
 *
 * Only the outermost generic is rewritten: the converters recurse into the
 * value type and normalize it on their way down.
 */
class GenericTypeNormalizer
{
    /**
     * Generics whose single type argument is the item of a JSON array.
     */
    private const LIST_GENERICS = [
        'list',
        'non-empty-list',
        'iterable',
    ];

    /**
     * Generics that behave like `array<K, V>`: a list when keyed by int (or not
     * keyed at all), a record when keyed by string.
     */
    private const ARRAY_GENERICS = [
        'array',
        'non-empty-array',
        'collection',
        'illuminate\\support\\collection',
        'illuminate\\database\\eloquent\\collection',
        'datacollection',
        'spatie\\laraveldata\\datacollection',
    ];

    /**
     * Normalize a single (non-union) PHPDoc type.
     */
    public static function normalize(string $type): string
    {
        $type = trim($type);

        if (! preg_match('/^\\\\?([A-Za-z_][\w\\\\-]*)\s*<(.+)>$/s', $type, $match)) {
            return $type;
        }

        $generic = strtolower($match[1]);
        $arguments = self::splitArguments($match[2]);

        if (in_array($generic, self::LIST_GENERICS, true) && count($arguments) === 1) {
            return 'array<int, '.$arguments[0].'>';
        }

        if (! in_array($generic, self::ARRAY_GENERICS, true)) {
            return $type;
        }

        if (count($arguments) === 1) {
            return 'array<int, '.$arguments[0].'>';
        }

        if (count($arguments) === 2) {
            $key = strtolower($arguments[0]);
            $keyType = in_array($key, ['string', 'non-empty-string', 'class-string'], true)
                ? 'string'
                : 'int';

            return 'array<'.$keyType.', '.$arguments[1].'>';
        }

        return $type;
    }

    /**
     * Split generic arguments on their top-level commas, so
     * `int, array<string, Foo>` yields two arguments rather than three.
     *
     * @return array<int, string>
     */
    private static function splitArguments(string $arguments): array
    {
        $parts = [];
        $current = '';
        $depth = 0;

        foreach (str_split($arguments) as $char) {
            if ($char === '<' || $char === '{' || $char === '(') {
                $depth++;
            } elseif ($char === '>' || $char === '}' || $char === ')') {
                $depth--;
            }

            if ($char === ',' && $depth === 0) {
                $parts[] = trim($current);
                $current = '';

                continue;
            }

            $current .= $char;
        }

        $parts[] = trim($current);

        return array_values(array_filter($parts, fn (string $part): bool => $part !== ''));
    }
}
