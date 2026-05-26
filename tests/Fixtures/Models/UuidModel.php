<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class UuidModel extends Model
{
    protected $keyType = 'string';

    public $incrementing = false;

    protected $fillable = [
        'id',
        'name',
    ];

    protected $casts = [
        'id' => 'string',
    ];
}
