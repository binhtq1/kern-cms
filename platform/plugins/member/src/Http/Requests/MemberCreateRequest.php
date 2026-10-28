<?php

namespace Kern\Member\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Enums\BaseStatusEnum;
use Kern\Base\Rules\EmailRule;
use Kern\Base\Rules\MediaImageRule;
use Kern\Base\Rules\PhoneNumberRule;
use Kern\Member\Models\Member;
use Kern\Support\Http\Requests\Request;

class MemberCreateRequest extends Request
{
    public function rules(): array
    {
        return [
            'dob' => ['nullable', 'date'],
            'description' => ['nullable', 'string', 'max:10000'],
            'status' => ['nullable', Rule::in(BaseStatusEnum::values())],
            'avatar_image' => ['nullable', 'string', new MediaImageRule],
            'first_name' => ['required', 'string', 'max:120', 'min:2'],
            'phone' => ['nullable', new PhoneNumberRule],
            'last_name' => ['required', 'string', 'max:120', 'min:2'],
            'email' => [
                'required',
                'max:60',
                'min:6',
                new EmailRule,
                Rule::unique((new Member)->getTable(), 'email'),
            ],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
        ];
    }
}
