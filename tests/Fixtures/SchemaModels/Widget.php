<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\SchemaModels;

use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\SoftDeletes;
use OiLab\OiLaravelTs\Tests\Fixtures\Enums\UserLevel;
use OiLab\OiLaravelTs\Tests\Fixtures\Enums\UserStatus;

/**
 * A model whose table exists, so its interface can be checked against the
 * columns: types, nullability, casts, hidden attributes and accessors.
 */
class Widget extends Model
{
    use SoftDeletes;

    protected $table = 'widgets';

    /** Only two columns are fillable: the others must still be typed. */
    protected $fillable = ['name', 'status'];

    protected $hidden = ['secret'];

    protected $appends = ['label', 'slug_or_null', 'closure_typed'];

    protected function casts(): array
    {
        return [
            'status' => UserStatus::class,
            'level' => UserLevel::class,
            'is_active' => 'boolean',
            'price' => 'decimal:2',
            'options' => 'array',
            'seen_at' => 'immutable_datetime',
            'settings' => UntypedCast::class,
        ];
    }

    /**
     * @return Attribute<string, never>
     */
    protected function label(): Attribute
    {
        return Attribute::get(fn (): string => (string) $this->name);
    }

    /**
     * @return Attribute<string|null, never>
     */
    protected function slugOrNull(): Attribute
    {
        return Attribute::get(fn (): ?string => null);
    }

    protected function closureTyped(): Attribute
    {
        return Attribute::get(fn (): int => 1);
    }
}
