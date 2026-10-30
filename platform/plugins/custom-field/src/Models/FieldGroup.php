<?php

namespace Kern\CustomField\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Kern\Base\Casts\SafeContent;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;

class FieldGroup extends BaseModel
{
    protected $table = 'field_groups';

    protected $fillable = [
        'order',
        'rules',
        'title',
        'status',
        'created_by',
        'updated_by',
    ];

    protected $casts = [
        'status' => BaseStatusEnum::class,
        'title' => SafeContent::class,
    ];

    protected static function booted(): void
    {
        self::deleting(function (FieldGroup $fieldGroup): void {
            $fieldGroup->fieldItems()->each(fn (FieldItem $item) => $item->delete());
        });
    }

    public function fieldItems(): HasMany
    {
        return $this->hasMany(FieldItem::class, 'field_group_id');
    }
}
