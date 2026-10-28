<?php

namespace Kern\Theme\Http\Requests;

use Kern\Support\Http\Requests\Request;

class CustomCssRequest extends Request
{
    public function rules(): array
    {
        return [
            'custom_css' => ['nullable', 'string', 'max:100000'],
        ];
    }
}
