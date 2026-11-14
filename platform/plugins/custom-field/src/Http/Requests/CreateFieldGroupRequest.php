<?php

namespace Kern\CustomField\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Support\Http\Requests\Request;

class CreateFieldGroupRequest extends Request
{
    public function rules(): array
    {
        return [
            'order' => ['required', 'integer', 'min:0', 'max:127'],
            'rules' => ['json', 'required'],
            'title' => ['required', 'string', 'max:255'],
            'status' => ['required', 'string', Rule::in(BaseStatusEnum::values())],
        ];
    }
}
