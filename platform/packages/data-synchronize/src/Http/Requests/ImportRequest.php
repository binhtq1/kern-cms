<?php

namespace Kern\DataSynchronize\Http\Requests;

use Kern\DataSynchronize\Importer\Importer;
use Kern\Support\Http\Requests\Request;

class ImportRequest extends Request
{
    public function rules(): array
    {
        return [
            'file_name' => ['required', 'string'],
            'offset' => ['required', 'integer', 'min:0'],
            'limit' => ['required', 'integer', 'min:1', 'max:'.Importer::MAX_CHUNK_SIZE],
            'total' => ['nullable', 'integer'],
        ];
    }
}
