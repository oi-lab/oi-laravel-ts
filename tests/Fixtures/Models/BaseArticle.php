<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

abstract class BaseArticle extends Model
{
    public function author(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}
