<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Illuminate\Database\Eloquent\Model;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\MetadataData;

class MetadataCast implements CastsAttributes
{
    /**
     * @return MetadataData
     */
    public function get(Model $model, string $key, mixed $value, array $attributes): MetadataData
    {
        $data = json_decode($value, true) ?? [];

        return MetadataData::fromArray($data);
    }

    public function set(Model $model, string $key, mixed $value, array $attributes): string
    {
        if ($value instanceof MetadataData) {
            return json_encode($value->toArray());
        }

        return json_encode($value);
    }
}
