<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class NoPrimaryKeyModel extends Model
{
    protected $primaryKey = null;

    public $incrementing = false;

    protected $fillable = [
        'user_id',
        'role_id',
    ];
}
