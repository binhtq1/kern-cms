<?php

namespace Kern\Setting\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Support\Http\Requests\Request;

class EmailTemplateRestoreRequest extends Request
{
    public function rules(): array
    {
        return [
            'module' => ['required', 'string', 'alpha_dash', Rule::in([$this->route('module')])],
            'template_file' => ['required', 'string', 'alpha_dash', Rule::in([$this->route('template')])],
            'email_subject_key' => ['sometimes', 'string', Rule::in([get_setting_email_subject_key((string) $this->route('type'), (string) $this->route('module'), (string) $this->route('template'))])],
        ];
    }
}
