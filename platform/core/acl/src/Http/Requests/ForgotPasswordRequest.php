<?php

namespace Kern\ACL\Http\Requests;

use Kern\Base\Rules\EmailRule;
use Kern\Support\Http\Requests\Request;

class ForgotPasswordRequest extends Request
{
    public function rules(): array
    {
        return [
            'email' => ['required', new EmailRule],
        ];
    }
}
