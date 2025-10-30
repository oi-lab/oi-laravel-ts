<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\AddressData;

class AddressCast implements CastsAttributes
{
    public function get(Model $model, string $key, mixed $value, array $attributes): AddressData
    {
        $data = json_decode($value, true) ?? [];

        return AddressData::fromArray($data);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof AddressData) {
            return json_encode($value->toArray());
        }

        return json_encode($value);
    }
}
