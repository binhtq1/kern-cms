<?php

namespace Kern\SocialLogin\Http\Requests;

use Kern\Support\Http\Requests\Request;

class FacebookDataDeletionRequestCallbackRequest extends Request
{
    public function rules(): array
    {
        return [
            'signed_request' => ['required', 'string'],
        ];
    }
}
