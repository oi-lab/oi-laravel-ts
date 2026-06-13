<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * Fixture mirroring packages such as spatie/laravel-permission that rely on
 * `$guarded` instead of `$fillable`. Its attribute columns can only be
 * discovered through the database schema.
 */
class GuardedModel extends Model
{
    protected $table = 'guarded_models';

    protected $guarded = [];

    protected $casts = [
        'is_active' => 'boolean',
    ];
}
