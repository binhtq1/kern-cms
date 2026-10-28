<?php

namespace Kern\Setting\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class EmailTemplateChangeStatusRequest extends Request
{
    public function rules(): array
    {
        return [
            'key' => ['required', 'string', Rule::in([get_setting_email_status_key((string) $this->route('type'), (string) $this->route('module'), (string) $this->route('template'))])],
            'value' => [new OnOffRule],
        ];
    }
}
