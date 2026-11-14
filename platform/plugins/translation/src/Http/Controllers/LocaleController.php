<?php

namespace Kern\Translation\Http\Controllers;

use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Kern\Base\Exceptions\FileNotWritableException;
use Kern\Base\Services\DeleteLocaleService;
use Kern\Base\Supports\Breadcrumb;
use Kern\Base\Supports\Language;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Translation\Http\Requests\LocaleRequest;
use Kern\Translation\Services\CreateLocaleService;
use Kern\Translation\Services\DownloadLocaleService;
use Throwable;

class LocaleController extends SettingController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('plugins/translation::translation.locales'), route('translations.locales'));
    }

    public function index()
    {
        $this->pageTitle(trans('plugins/translation::translation.locales'));

        $existingLocales = Language::getAvailableLocales();
        $locales = Language::getLocales();

        return Inertia::render('Locales/Index', ['existing' => collect($existingLocales)->map(fn ($item): array => [...$item, 'is_default' => $item['locale'] === app()->getLocale(), 'delete_url' => route('translations.locales.delete', $item['locale']), 'download_url' => route('translations.locales.download', $item['locale'])])->values()->all(), 'locales' => $locales, 'urls' => ['create' => route('translations.locales.post'), 'index' => route('translations.locales'), 'admin' => route('translations.index'), 'theme' => route('translations.theme-translations'), 'locales' => route('translations.locales')]]);
    }

    public function store(LocaleRequest $request, CreateLocaleService $createLocaleService)
    {
        $locale = $request->input('locale');

        if (! File::isDirectory(lang_path($locale))) {
            $createLocaleService->handle($locale);
        }

        return $this
            ->httpResponse()
            ->withCreatedSuccessMessage();
    }

    public function destroy(string $locale, DeleteLocaleService $deleteLocaleService)
    {
        abort_unless(array_key_exists($locale, Language::getAvailableLocales()), 404);

        try {
            $deleteLocaleService->handle($locale);

            return $this
                ->httpResponse()
                ->withDeletedSuccessMessage();
        } catch (FileNotWritableException $e) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($e->getMessage());
        }
    }

    public function download(string $locale, DownloadLocaleService $downloadLocaleService)
    {
        abort_unless(array_key_exists($locale, Language::getAvailableLocales()), 404);

        try {
            $file = $downloadLocaleService->handle($locale);

            return response()->download($file)->deleteFileAfterSend();
        } catch (Throwable $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }
}
