<?php

namespace Kern\Gallery\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kern\ACL\Models\User;
use Kern\Base\Casts\SafeContent;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;

class Gallery extends BaseModel
{
    protected $table = 'galleries';

    protected $fillable = [
        'name',
        'description',
        'is_featured',
        'order',
        'image',
        'status',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'name' => SafeContent::class,
        'description' => SafeContent::class,
    ];

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class)->withDefault();
    }
}
