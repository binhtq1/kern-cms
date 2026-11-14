<?php

namespace Kern\Contact\Models;

use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Models\BaseModel;
use Kern\Contact\Enums\CustomFieldType;

class CustomField extends BaseModel
{
    protected $table = 'contact_custom_fields';

    protected $fillable = [
        'name',
        'required',
        'placeholder',
        'type',
        'status',
        'order',

    ];

    protected $casts = [
        'type' => CustomFieldType::class,
        'required' => 'bool',
        'order' => 'int',
        'status' => BaseStatusEnum::class,
    ];

    protected static function booted(): void
    {
        static::deleting(fn (CustomField $customField) => $customField->options()->eachById(fn (CustomFieldOption $option) => $option->delete()));
    }

    public function options(): HasMany
    {
        return $this->hasMany(CustomFieldOption::class, 'custom_field_id');
    }

    public function saveOptions(array $options): void
    {
        DB::transaction(function () use ($options): void {
            $formattedOptions = [];

            $this
                ->options()
                ->whereNotIn('id', array_column($options, 'id'))
                ->eachById(fn (CustomFieldOption $option) => $option->delete());

            foreach ($options as $item) {
                $option = null;

                if (isset($item['id'])) {
                    $option = $this->options()->find($item['id']);
                    $option->fill($item);
                }

                if (! $option) {
                    $option = new CustomFieldOption($item);
                }

                if ($option->isDirty()) {
                    $formattedOptions[] = $option;
                }
            }

            if (! empty($formattedOptions)) {
                $this->options()->saveMany($formattedOptions);
            }
        });
    }
}
