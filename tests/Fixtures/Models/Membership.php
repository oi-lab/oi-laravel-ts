<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\Pivot;

class Membership extends Pivot
{
    protected $fillable = [
        'assigned_at',
        'assigned_by',
    ];

    protected function casts(): array
    {
        return [
            'assigned_at' => 'datetime',
        ];
    }
}
