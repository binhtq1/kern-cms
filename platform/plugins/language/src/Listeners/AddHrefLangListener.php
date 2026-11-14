<?php

namespace Kern\Language\Listeners;

use Exception;
use Kern\Base\Facades\BaseHelper;
use Kern\Language\Facades\Language;
use Kern\Language\Models\LanguageMeta;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Page\Models\Page;
use Kern\Slug\Models\Slug;
use Kern\Theme\Events\RenderingSingleEvent;

class AddHrefLangListener
{
    public function handle(RenderingSingleEvent $event): void
    {
        try {
            if (! defined('THEME_FRONT_HEADER')) {
                return;
            }

            add_filter(THEME_FRONT_HEADER, function ($header) use ($event) {
                if (
                    ! in_array($event->slug->reference_type, Language::supportedModels()) &&
                    (! is_plugin_active('language-advanced') || ! in_array($event->slug->reference_type, LanguageAdvancedManager::supportedModels()))
                ) {
                    return $header;
                }

                $urls = [];

                $defaultLocale = Language::getDefaultLocale();

                $currentLocaleCode = Language::getCurrentLocaleCode();

                if ($event->slug->reference_type == Page::class && BaseHelper::isHomepage($event->slug->reference_id)) {
                    foreach (Language::getSupportedLocales() as $localeCode => $properties) {
                        $urls[] = [
                            'url' => Language::getLocalizedURL($localeCode, url()->current(), [], false),
                            'lang_code' => $localeCode,
                        ];
                    }
                } else {
                    $languageMeta = LanguageMeta::query()->where(
                        'language_meta.lang_meta_code',
                        '!=',
                        $currentLocaleCode
                    )
                        ->join('language_meta as meta', 'meta.lang_meta_origin', 'language_meta.lang_meta_origin')
                        ->where([
                            'meta.reference_type' => $event->slug->reference_type,
                            'meta.reference_id' => $event->slug->reference_id,
                        ])
                        ->pluck('language_meta.lang_meta_code', 'language_meta.reference_id')->all();

                    $slug = Slug::query()->whereIn('reference_id', array_keys($languageMeta))
                        ->where('reference_type', $event->slug->reference_type)
                        ->select(['key', 'prefix', 'reference_id'])
                        ->get();

                    foreach ($slug as $item) {
                        if (empty($languageMeta[$item->reference_id])) {
                            continue;
                        }

                        $locale = Language::getLocaleByLocaleCode($languageMeta[$item->reference_id]);

                        if ($locale == $defaultLocale && Language::hideDefaultLocaleInURL()) {
                            $locale = null;
                        }

                        $urls[] = [
                            'url' => url($locale.($item->prefix ? '/'.$item->prefix : '').'/'.$item->key),
                            'lang_code' => $languageMeta[$item->reference_id],
                        ];
                    }
                }

                Language::setSwitcherURLs($urls);

                return $header.view('plugins/language::partials.hreflang', compact('urls'))->render();
            }, 55);
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
