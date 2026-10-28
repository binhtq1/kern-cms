<?php

namespace Kern\Translation\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Illuminate\Support\Facades\File;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Theme\Facades\Theme;
use Kern\Translation\Http\Controllers\Concerns\HasMapTranslationsTable;
use Kern\Translation\Manager;
use Kern\Translation\Supports\AdminTranslationData;
use Kern\Translation\Tables\ThemeTranslationTable;

class ThemeTranslationController extends SettingController
{
    use HasMapTranslationsTable;

    public function index(Request $request, ThemeTranslationTable $translationTable)
    {
        return app(AdminTranslationData::class)->index(true);
    }

    public function update(Request $request, Manager $manager)
    {
        app(AdminTranslationData::class)->validateUpdate($request, true);

        if (! File::isDirectory(lang_path())) {
            File::makeDirectory(lang_path());
        }

        if (! File::isWritable(lang_path())) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('plugins/translation::translation.folder_is_not_writeable', ['lang_path' => lang_path()]));
        }

        $locale = $request->input('pk');

        if (! $locale) {
            return $this->updateResponse();
        }

        if (! $request->filled('name') || ! $request->has('value')) {
            return $this->updateResponse();
        }

        $name = $request->input('name');
        $value = $request->input('value');

        $inheritTranslations = $manager->getInheritThemeTranslations($locale);
        $translations = $manager->getThemeTranslations($locale, false);
        $allTranslations = $manager->getThemeTranslations($locale);

        if (! Arr::has($allTranslations, $request->input('name'))) {
            return $this->updateResponse();
        }

        if (Theme::hasInheritTheme()) {
            if (Arr::has($inheritTranslations, $name)) {
                $inheritTranslations[$name] = $value;
            }

            $manager->saveInheritThemeTranslation($locale, $inheritTranslations);
        }

        if (Arr::has($translations, $name)) {
            $translations[$name] = $value;
        }

        $manager->saveThemeTranslations($locale, $translations);

        return $this->updateResponse();
    }

    protected function updateResponse(): BaseHttpResponse
    {
        return $this
            ->httpResponse()
            ->setPreviousRoute('translations.theme-translations')
            ->withUpdatedSuccessMessage();
    }
}
