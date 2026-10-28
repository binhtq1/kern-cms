<?php

namespace Kern\ACL\Models;

use Kern\Base\Models\BaseModel;

class Activation extends BaseModel
{
    protected $table = 'activations';

    protected $fillable = [
        'code',
        'completed',
        'completed_at',
    ];

    protected $casts = [
        'completed' => 'bool',
    ];
}
