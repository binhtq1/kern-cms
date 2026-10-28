<?php

namespace Kern\Table\Http\Requests;

use Kern\Support\Http\Requests\Request;

class BulkChangeRequest extends Request
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string'],
            'class' => ['required', 'string'],
        ];
    }
}
