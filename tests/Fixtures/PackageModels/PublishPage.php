<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\PackageModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A package model nothing in the application references. It only reaches the
 * schema through `included_model_namespaces`.
 */
class PublishPage extends Model
{
    protected $fillable = [
        'name',
        'slug',
    ];

    /**
     * @return HasMany<PublishBlock, $this>
     */
    public function blocks(): HasMany
    {
        return $this->hasMany(PublishBlock::class);
    }
}
