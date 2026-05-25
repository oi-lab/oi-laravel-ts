<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use OiLab\OiLaravelTs\Tests\Fixtures\Casts\AddressCast;
use OiLab\OiLaravelTs\Tests\Fixtures\ExcludedModels\Category;

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

    protected $appends = ['full_name'];

    public function getFullNameAttribute(): string
    {
        return $this->name;
    }

    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    public function roles(): BelongsToMany
    {
        return $this->belongsToMany(Role::class)
            ->withPivot(['assigned_at', 'assigned_by']);
    }

    public function memberships(): BelongsToMany
    {
        return $this->belongsToMany(Role::class, 'memberships')
            ->using(Membership::class)
            ->withPivot(['assigned_at', 'assigned_by']);
    }

    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }
}
