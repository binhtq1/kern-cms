<?php

namespace Kern\Api\Http\Requests;

use Kern\Support\Http\Requests\Request;

class StoreSanctumTokenRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'abilities' => ['nullable', 'array'],
        ];
    }
}
