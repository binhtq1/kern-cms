<?php

namespace Kern\Language\Supports;

use Illuminate\Validation\ValidationException;
use Kern\Language\Facades\Language;

class AdminAppearanceLanguages
{
    /** @return array{code:string,defaultCode:string,isDefault:bool,query:array<string,string>}|null */
    public function context(): ?array
    {
        $routeName = request()->route()?->getName();
        if ($routeName && ! str_starts_with($routeName, 'widgets.') && ! str_starts_with($routeName, 'theme.options')) {
            return null;
        }
        if (! is_plugin_active('language') || Language::getActiveLanguage()->isEmpty()) {
            return null;
        }
        $languages = Language::getActiveLanguage()->keyBy('lang_code');
        $default = $languages->first(fn ($language): bool => (bool) $language->lang_is_default) ?: $languages->first();
        $code = request()->input('ref_lang') ?: $default->lang_code;
        if (! is_string($code) || ! $languages->has($code)) {
            throw ValidationException::withMessages(['ref_lang' => __('The selected language is unavailable.')]);
        }
        $isDefault = $code === $default->lang_code;
        Language::setCurrentAdminLocale($code);
        if ($isDefault) {
            request()->query->remove('ref_lang');
            request()->request->remove('ref_lang');
            if (request()->isJson()) {
                request()->json()->remove('ref_lang');
            }
        }

        return ['code' => $code, 'defaultCode' => $default->lang_code, 'isDefault' => $isDefault, 'query' => $isDefault ? [] : ['ref_lang' => $code]];
    }

    /** @return array{language:string|null,languages:array<int,array{code:string,name:string,flag:string,active:bool,url:string}>} */
    public function props(string $route, array $parameters = []): array
    {
        $context = $this->context();
        if (! $context) {
            return ['language' => null, 'languages' => []];
        }
        unset($parameters['ref_lang'], $parameters['ref_from']);
        $languages = Language::getActiveLanguage()->map(fn ($language): array => ['code' => $language->lang_code, 'name' => $language->lang_name, 'flag' => $language->lang_flag, 'active' => $language->lang_code === $context['code'], 'url' => route($route, [...$parameters, ...($language->lang_code === $context['defaultCode'] ? [] : ['ref_lang' => $language->lang_code])])])->values()->all();

        return ['language' => $context['code'], 'languages' => $languages];
    }
}
