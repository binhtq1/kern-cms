<?php

namespace Kern\Language\Supports;

use Inertia\Inertia;
use Kern\Base\Supports\Language;
use Kern\Language\Models\Language as LanguageModel;

class AdminLanguageData
{
    public function index(): mixed
    {
        $items = LanguageModel::query()->orderBy('lang_order')->get()->map(fn ($item): array => [...$item->toArray(), 'delete_url' => route('languages.destroy', $item->getKey())])->all();
        $settings = [];
        foreach (['language_hide_default' => 1, 'language_display' => 'all', 'language_switcher_display' => 'dropdown', 'language_hide_languages' => '[]', 'language_show_default_item_if_current_version_not_existed' => 1, 'language_auto_detect_user_language' => 0] as $name => $default) {
            $settings[$name] = $name === 'language_hide_languages' ? json_decode(setting($name, $default), true) : setting($name, $default);
        }

        return Inertia::render('Languages/Index', ['languages' => $items, 'presets' => collect(Language::getListLanguages())->map(fn (array $language): array => ['lang_name' => $language[2], 'lang_locale' => $language[0], 'lang_code' => $language[1], 'lang_is_rtl' => $language[3] === 'rtl', 'lang_flag' => $language[4]])->values()->all(), 'codes' => Language::getLanguageCodes(), 'locales' => Language::getLocaleKeys(), 'flags' => Language::getListLanguageFlags(), 'settings' => $settings, 'permissions' => ['create' => request()->user()->hasPermission('languages.create'), 'edit' => request()->user()->hasPermission('languages.edit'), 'delete' => request()->user()->hasPermission('languages.destroy')], 'urls' => ['index' => route('languages.index'), 'create' => route('languages.store'), 'update' => route('languages.edit'), 'default' => route('languages.set.default.update'), 'settings' => route('languages.settings')]]);
    }
}
