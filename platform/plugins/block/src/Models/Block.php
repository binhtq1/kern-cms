<?php

namespace Kern\Block\Models;

use Kern\Base\Casts\SafeContent;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;
use Kern\Base\Models\Concerns\HasSlug;

class Block extends BaseModel
{
    use HasSlug;

    protected $table = 'blocks';

    protected $fillable = [
        'name',
        'alias',
        'description',
        'content',
        'raw_content',
        'status',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'name' => SafeContent::class,
        'content' => SafeContent::class,
        'description' => SafeContent::class,
    ];

    protected static function booted(): void
    {
        self::saving(function (self $model): void {
            $model->alias = self::createSlug($model->alias, $model->getKey(), 'alias');
        });
    }
}
