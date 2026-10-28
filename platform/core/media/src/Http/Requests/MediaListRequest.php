<?php

namespace Kern\Media\Http\Requests;

use Kern\Support\Http\Requests\Request;

class MediaListRequest extends Request
{
    public function rules(): array
    {
        return [
            'folder_id' => ['nullable', 'string'],
        ];
    }
}
