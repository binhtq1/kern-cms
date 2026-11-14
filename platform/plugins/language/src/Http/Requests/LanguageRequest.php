<?php

namespace Kern\Language\Http\Requests;

use Illuminate\Validation\Rule;
use Kern\Base\Supports\Language;
use Kern\Support\Http\Requests\Request;

class LanguageRequest extends Request
{
    public function rules(): array
    {
        return [
            'lang_name' => ['required', 'string', 'max:30', 'min:2'],
            'lang_code' => [
                'required',
                'string',
                Rule::in(Language::getLanguageCodes()),
            ],
            'lang_locale' => [
                'required',
                'string',
                Rule::in(Language::getLocaleKeys()),
            ],
            'lang_flag' => ['required', 'string', Rule::in(array_keys(Language::getListLanguageFlags()))],
            'lang_is_rtl' => ['required', 'boolean'],
            'lang_order' => ['required', 'integer', 'min:0', 'max:10000'],
        ];
    }
}
