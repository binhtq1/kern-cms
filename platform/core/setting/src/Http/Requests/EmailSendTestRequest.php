<?php

namespace Kern\Setting\Http\Requests;

use Kern\Support\Http\Requests\Request;

class EmailSendTestRequest extends Request
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'email'],
        ];
    }
}
