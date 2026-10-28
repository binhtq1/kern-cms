<?php

namespace Kern\Shortcode\Http\Requests;

use Kern\Support\Http\Requests\Request;

class GetShortcodeDataRequest extends Request
{
    public function rules(): array
    {
        return [
            'key' => ['nullable', 'string', 'max:255'],
            'code' => ['nullable', 'string', 'max:1000000'],
        ];
    }
}
