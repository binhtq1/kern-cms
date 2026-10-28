<?php

namespace Kern\Api\Http\Requests;

use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class ApiSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'api_enabled' => [new OnOffRule],
        ];
    }
}
