<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;

class TagIdsCast implements CastsAttributes
{
    /**
     * @return array<int, int>
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): array
    {
        return json_decode($value, true) ?? [];
    }

    /**
     * @param  array<int, int>  $value
     */
    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        return json_encode($value);
    }
}
