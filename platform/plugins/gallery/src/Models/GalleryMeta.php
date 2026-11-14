<?php

namespace Kern\Gallery\Models;

use Kern\Base\Models\BaseModel;

class GalleryMeta extends BaseModel
{
    protected $table = 'gallery_meta';

    protected $casts = [
        'images' => 'json',
    ];
}
