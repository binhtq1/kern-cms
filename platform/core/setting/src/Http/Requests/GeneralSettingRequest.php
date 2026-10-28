<?php

namespace Kern\Setting\Http\Requests;

use DateTimeZone;
use Illuminate\Validation\Rule;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class GeneralSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'admin_email' => ['nullable', 'array'],
            'admin_email.*' => ['nullable', 'email'],
            'time_zone' => Rule::in(DateTimeZone::listIdentifiers()),
            'enable_send_error_reporting_via_email' => [new OnOffRule],
        ];
    }
}
