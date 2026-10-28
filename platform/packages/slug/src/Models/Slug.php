<?php

namespace Kern\Slug\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kern\Base\Models\BaseModel;

class Slug extends BaseModel
{
    protected $table = 'slugs';

    protected $fillable = [
        'key',
        'reference_type',
        'reference_id',
        'prefix',
    ];

    public function reference(): BelongsTo
    {
        return $this->morphTo();
    }
}
