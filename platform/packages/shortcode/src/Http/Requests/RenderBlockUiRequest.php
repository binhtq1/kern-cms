<?php

namespace Kern\Shortcode\Http\Requests;

use Kern\Support\Http\Requests\Request;

class RenderBlockUiRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string'],
            'attributes' => ['nullable', 'array'],
            'attributes.*' => ['nullable', 'string'],
            'content' => ['nullable', 'string', 'max:1000000'],
        ];
    }
}
