<?php

namespace Kern\ACL\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Supports\Language;
use Kern\Support\Http\Requests\Request;

class PreferenceRequest extends Request
{
    public function rules(): array
    {
        return [
            'locale' => ['sometimes', Rule::in(array_keys(Language::getAvailableLocales()))],
            'locale_direction' => ['required', 'string', 'in:ltr,rtl'],
            'theme_mode' => ['required', 'string', 'in:light,dark'],
        ];
    }
}
