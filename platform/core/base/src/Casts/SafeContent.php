<?php

namespace Kern\Base\Casts;

use Illuminate\Contracts\Database\Eloquent\CastsAttributes;
use Kern\Base\Facades\BaseHelper;

class SafeContent implements CastsAttributes
{
    public function set($model, string $key, $value, array $attributes)
    {
        return BaseHelper::clean($value);
    }

    public function get($model, string $key, $value, array $attributes)
    {
        if (! $value) {
            return $value;
        }

        return html_entity_decode(BaseHelper::clean($value));
    }
}
