<?php

namespace OiLab\OiLaravelTs\Tests\Fixtures\PackageModels;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PublishBlock extends Model
{
    protected $fillable = [
        'publish_page_id',
        'template_key',
    ];

    /**
     * @return BelongsTo<PublishPage, $this>
     */
    public function page(): BelongsTo
    {
        return $this->belongsTo(PublishPage::class, 'publish_page_id');
    }
}
