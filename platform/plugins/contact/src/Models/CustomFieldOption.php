<?php

namespace Kern\Contact\Models;

use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Kern\Base\Models\BaseModel;

class CustomFieldOption extends BaseModel
{
    protected $table = 'contact_custom_field_options';

    protected $fillable = [
        'custom_field_id',
        'label',
        'value',
        'order',
    ];

    protected $casts = [
        'order' => 'int',
    ];

    public function customField(): BelongsTo
    {
        return $this->belongsTo(CustomField::class, 'custom_field_id');
    }
}
