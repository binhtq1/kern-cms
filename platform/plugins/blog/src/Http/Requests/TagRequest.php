<?php

namespace Kern\Blog\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Support\Http\Requests\Request;

class TagRequest extends Request
{
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:120'],
            'description' => ['nullable', 'string', 'max:400'],
            'status' => [Rule::in(BaseStatusEnum::values())],
        ];
    }
}
