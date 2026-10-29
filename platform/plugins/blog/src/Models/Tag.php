<?php

namespace Kern\Blog\Models;

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Kern\Base\Casts\SafeContent;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;

class Tag extends BaseModel
{
    protected $table = 'tags';

    protected $fillable = [
        'name',
        'description',
        'status',
        'author_id',
        'author_type',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'name' => SafeContent::class,
        'description' => SafeContent::class,
    ];

    protected static function booted(): void
    {
        static::deleted(function (Tag $tag): void {
            $tag->posts()->detach();
        });
    }

    public function posts(): BelongsToMany
    {
        return $this->belongsToMany(Post::class, 'post_tags');
    }
}
