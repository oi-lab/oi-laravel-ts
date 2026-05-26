<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Concerns\HasUuids;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;

class HasUuidsModel extends Model
{
    use HasUuids, SoftDeletes;

    protected $fillable = [
        'id',
        'name',
        'metadata',
    ];

    protected function casts(): array
    {
        return [
            'id' => 'string',
            'metadata' => 'array',
        ];
    }
}
