<?php

namespace Kern\Menu\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Kern\Base\Casts\SafeContent;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;
use Kern\Base\Models\Concerns\HasSlug;
use Kern\Support\Services\Cache\Cache;

class Menu extends BaseModel
{
    use HasSlug;

    protected $table = 'menus';

    protected $fillable = [
        'name',
        'slug',
        'status',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'name' => SafeContent::class,
    ];

    protected static function booted(): void
    {
        static::deleted(function (self $model): void {
            $model->menuNodes()->delete();
            $model->locations()->delete();
        });

        static::saving(function (self $model): void {
            if (! $model->slug) {
                $model->slug = self::createSlug($model->name, $model->getKey());
            }
        });

        static::saved(function (): void {
            Cache::make(static::class)->flush();
        });

        static::deleted(function (): void {
            Cache::make(static::class)->flush();
        });
    }

    public function menuNodes(): HasMany
    {
        return $this->hasMany(MenuNode::class, 'menu_id');
    }

    public function locations(): HasMany
    {
        return $this->hasMany(MenuLocation::class, 'menu_id');
    }
}
