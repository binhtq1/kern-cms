<?php

namespace Kern\Member\Http\Requests\Settings;

use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class MemberSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'member_enabled_login' => [new OnOffRule],
            'member_enabled_registration' => [new OnOffRule],
            'verify_account_email' => [new OnOffRule],
        ];
    }
}
