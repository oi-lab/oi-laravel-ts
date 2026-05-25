<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\ExcludedModels;

use Illuminate\Database\Eloquent\Model;

class Category extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];
}
