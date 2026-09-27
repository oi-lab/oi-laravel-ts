<?php

namespace OiLab\OiLaravelTs\Support;

/**
 * Column Type Mapper
 *
 * Maps what a model attribute serializes to — from its cast when it has one,
 * from its database column when it does not — onto a TypeScript type.
 *
 * The target is the JSON the model produces, not the PHP value: a `decimal:2`
 * cast serializes as a string, a `timestamp` cast as an integer, an uncast
 * `json` column as the raw string the driver returns.
 */
class ColumnTypeMapper
{
    /**
     * TypeScript type for a built-in cast, or null when the cast is not one of
     * Laravel's built-in string casts (a class cast, an enum, a typo).
     */
    public static function fromCast(string $cast): ?string
    {
        $cast = strtolower(trim($cast));

        // `encrypted:array`, `encrypted:collection`… decrypt to a structure.
        if (str_starts_with($cast, 'encrypted:')) {
            return 'unknown';
        }

        $base = str_contains($cast, ':') ? strstr($cast, ':', true) : $cast;

        return match ($base) {
            'int', 'integer', 'real', 'float', 'double', 'timestamp' => 'number',
            'decimal', 'string', 'hashed', 'encrypted' => 'string',
            'bool', 'boolean' => 'boolean',
            'date', 'datetime', 'immutable_date', 'immutable_datetime', 'custom_datetime' => 'string',
            'object' => 'Record<string, unknown>',
            'array', 'json', 'collection' => 'unknown',
            default => null,
        };
    }

    /**
     * TypeScript type for an uncast column, from the schema's type name (e.g.
     * `varchar`, `int8`) and full type (e.g. `tinyint(1)`).
     */
    public static function fromColumn(string $typeName, string $type = ''): string
    {
        $typeName = strtolower($typeName);
        $type = strtolower($type);

        if ($type === 'tinyint(1)' || in_array($typeName, ['bool', 'boolean', 'bit'], true)) {
            return 'boolean';
        }

        if (preg_match('/^(tiny|small|medium|big)?int(eger)?\d*$/', $typeName)
            || in_array($typeName, ['serial', 'bigserial', 'smallserial', 'float', 'float4', 'float8', 'double', 'double precision', 'real', 'year'], true)) {
            return 'number';
        }

        // Every other type — character, text, uuid, date and time, decimal
        // (drivers return it as a string to keep its precision), json (returned
        // as its raw string until cast) — reaches the JSON as a string.
        return 'string';
    }
}
