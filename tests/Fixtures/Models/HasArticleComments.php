<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;

trait HasArticleComments
{
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }
}
