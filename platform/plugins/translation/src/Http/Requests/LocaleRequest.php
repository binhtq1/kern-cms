<?php

namespace Kern\Translation\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Supports\Language;
use Kern\Support\Http\Requests\Request;

class LocaleRequest extends Request
{
    public function rules(): array
    {
        return [
            'locale' => ['required', Rule::in(Language::getLocaleKeys())],
        ];
    }
}
