<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Database\Eloquent\SoftDeletes;
use OiLab\OiLaravelTs\Tests\Fixtures\Casts\MetadataCast;

class Post extends Model
{
    use SoftDeletes;
    protected $fillable = [
        'title',
        'content',
        'published_at',
        'user_id',
    ];

    protected $casts = [
        'published_at' => 'datetime',
        'metadata' => MetadataCast::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /** @return MorphOne<Attachment, $this> */
    public function cover(): MorphOne
    {
        return $this->morphOne(Attachment::class, 'attachable');
    }
}
