<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\Models;

use Illuminate\Database\Eloquent\Model;
use OiLab\OiLaravelTs\Tests\Fixtures\DataObjects\MetadataData;

class Order extends Model
{
    protected $fillable = ['title', 'total'];

    protected $casts = [
        'total' => 'float',
    ];

    protected $appends = ['metadata'];

    public function getMetadataAttribute(): MetadataData
    {
        return new MetadataData(title: $this->title ?? '');
    }
}
