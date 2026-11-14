<?php

namespace Kern\Contact\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Contact\Enums\ContactStatusEnum;
use Kern\Support\Http\Requests\Request;

class EditContactRequest extends Request
{
    public function rules(): array
    {
        return [
            'status' => Rule::in(ContactStatusEnum::values()),
        ];
    }
}
