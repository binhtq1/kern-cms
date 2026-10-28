<?php

namespace Kern\ACL\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\ACL\Models\User;
use Kern\Base\Rules\EmailRule;
use Kern\Support\Http\Requests\Request;

class CreateUserRequest extends Request
{
    public function rules(): array
    {
        return [
            'first_name' => ['required', 'string', 'max:60', 'min:2'],
            'last_name' => ['required', 'string', 'max:60', 'min:2'],
            'email' => [
                'required',
                'min:6',
                'max:60',
                new EmailRule,
                Rule::unique((new User)->getTable(), 'email'),
            ],
            'role_id' => ['nullable', 'integer', Rule::exists('roles', 'id')],
            'password' => ['required', 'string', 'min:6', 'confirmed'],
            'username' => [
                'required',
                'string',
                'alpha_dash',
                'min:4',
                'max:30',
                Rule::unique((new User)->getTable(), 'username'),
            ],
        ];
    }
}
