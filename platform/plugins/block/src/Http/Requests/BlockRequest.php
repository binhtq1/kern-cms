<?php

namespace Kern\Block\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Support\Http\Requests\Request;

class BlockRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:250'],
            'description' => ['nullable', 'string', 'max:400'],
            'alias' => ['required', 'string', 'max:250'],
            'status' => Rule::in(BaseStatusEnum::values()),
            'content' => ['nullable', 'string', 'max:300000'],
            'raw_content' => ['nullable', 'string', 'max:300000'],
        ];
    }
}
