<?php

namespace Kern\ACL\Http\Requests;

use Kern\Media\Facades\RvMedia;
use Kern\Support\Http\Requests\Request;

class AvatarRequest extends Request
{
    public function rules(): array
    {
        return [
            'avatar_file' => RvMedia::imageValidationRule(),
        ];
    }
}
