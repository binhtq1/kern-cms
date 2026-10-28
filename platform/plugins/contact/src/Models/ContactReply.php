<?php

namespace Kern\Contact\Models;

use Kern\Base\Casts\SafeContent;
use Kern\Base\Models\BaseModel;

class ContactReply extends BaseModel
{
    protected $table = 'contact_replies';

    protected $fillable = [
        'message',
        'contact_id',
    ];

    protected $casts = [
        'message' => SafeContent::class,
    ];
}
