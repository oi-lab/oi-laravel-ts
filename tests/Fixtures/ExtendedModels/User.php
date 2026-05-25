<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\ExtendedModels;

use Illuminate\Database\Eloquent\Model;

class User extends Model
{
    protected $fillable = [
        'ps_id',
        'ps_email',
        'ps_firstname',
    ];

    protected $casts = [
        'ps_id' => 'integer',
    ];
}
