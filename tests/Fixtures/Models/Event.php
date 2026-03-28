<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

class Event extends Model
{
    const UPDATED_AT = null;

    protected $fillable = [
        'title',
        'description',
    ];
}
