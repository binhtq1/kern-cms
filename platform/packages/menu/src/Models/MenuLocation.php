<?php

namespace Kern\Menu\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kern\Base\Models\BaseModel;

class MenuLocation extends BaseModel
{
    protected $table = 'menu_locations';

    protected $fillable = [
        'menu_id',
        'location',
    ];

    public function menu(): BelongsTo
    {
        return $this->belongsTo(Menu::class, 'menu_id')->withDefault();
    }
}
