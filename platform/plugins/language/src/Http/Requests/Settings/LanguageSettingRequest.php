<?php

namespace Kern\Language\Http\Requests\Settings;

use Kern\Base\Rules\OnOffRule;
use Kern\Support\Http\Requests\Request;

class LanguageSettingRequest extends Request
{
    public function rules(): array
    {
        return [
            'language_hide_default' => $onOffRule = new OnOffRule,
            'language_display' => ['required', 'in:all,flag,name'],
            'language_switcher_display' => ['required', 'in:dropdown,list'],
            'language_hide_languages' => ['nullable', 'array'],
            'language_hide_languages.*' => ['sometimes', 'exists:languages,lang_id'],
            'language_show_default_item_if_current_version_not_existed' => $onOffRule,
            'language_auto_detect_user_language' => $onOffRule,
        ];
    }
}
