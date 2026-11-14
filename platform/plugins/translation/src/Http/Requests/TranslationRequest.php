<?php

namespace Kern\Translation\Http\Requests;

use Kern\Support\Http\Requests\Request;

class TranslationRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:250'],
        ];
    }
}
