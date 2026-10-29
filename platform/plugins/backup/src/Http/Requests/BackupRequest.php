<?php

namespace Kern\Backup\Http\Requests;

use Kern\Support\Http\Requests\Request;

class BackupRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:250'],
        ];
    }
}
