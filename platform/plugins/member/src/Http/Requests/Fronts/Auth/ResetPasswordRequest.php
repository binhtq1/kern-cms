<?php

namespace Kern\Member\Http\Requests\Fronts\Auth;

use Kern\Base\Rules\EmailRule;
use Kern\Support\Http\Requests\Request;

class ResetPasswordRequest extends Request
{
    public function rules(): array
    {
        return [
            'token' => ['required', 'string'],
            'email' => ['required', new EmailRule],
            'password' => ['required', 'confirmed', 'min:6'],
        ];
    }
}
