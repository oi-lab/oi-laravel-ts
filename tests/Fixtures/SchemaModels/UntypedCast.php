<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\SchemaModels;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

/**
 * A cast whose value type cannot be read: it serializes to an object, never to
 * the raw json string its column holds.
 */
class UntypedCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return json_decode((string) $value);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): mixed
    {
        return json_encode($value);
    }
}
