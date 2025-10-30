<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use OiLab\OiLaravelTs\Tests\Fixtures\Casts\AddressCast;

class User extends Model
{
    protected $fillable = [
        'name',
        'email',
        'age',
        'bio',
    ];

    protected $casts = [
        'email_verified_at' => 'datetime',
        'age' => 'integer',
        'address' => AddressCast::class,
    ];

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_at', 'assigned_by']);
    }
}
