<?php

namespace Kern\Member\Http\Requests\Fronts\Auth;

use Kern\Base\Rules\EmailRule;
use Kern\Support\Http\Requests\Request;

class LoginRequest extends Request
{
    public function rules(): array
    {
        return [
            'email' => ['required', 'string', new EmailRule],
            'password' => ['required', 'string'],
        ];
    }
}
