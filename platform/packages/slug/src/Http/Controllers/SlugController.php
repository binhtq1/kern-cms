<?php

namespace Kern\Slug\Http\Controllers;

use Illuminate\Support\Str;
use Kern\Menu\Facades\Menu;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;
use Kern\Setting\Supports\SettingStore;
use Kern\Slug\Events\UpdatedPermalinkSettings;
use Kern\Slug\Forms\SlugSettingForm;
use Kern\Slug\Http\Requests\SlugRequest;
use Kern\Slug\Http\Requests\SlugSettingsRequest;
use Kern\Slug\Models\Slug;
use Kern\Slug\Services\SlugService;

class SlugController extends SettingController
{
    public function store(SlugRequest $request, SlugService $slugService)
    {
        return $slugService->create(
            $request->input('value'),
            $request->input('slug_id'),
            $request->input('model')
        );
    }

    public function edit()
    {
        $this->pageTitle(trans('packages/slug::slug.settings.title'));

        $form = SlugSettingForm::create();

        return app(AdminSettingData::class)->render($form);
    }

    public function update(SlugSettingsRequest $request, SettingStore $settingStore)
    {
        $hasChangedEndingUrl = false;

        foreach ($request->except(['_token', 'ref_lang']) as $settingKey => $settingValue) {
            if (Str::contains($settingKey, '-model-key')) {
                continue;
            }

            if (Str::startsWith($settingKey, 'public_single_ending_url')) {
                if ($settingValue) {
                    $settingValue = ltrim($settingValue, '.');
                }

                if ($settingStore->get($settingKey) !== $settingValue) {
                    $hasChangedEndingUrl = true;
                }
            }

            $prefix = (string) $settingValue;
            $reference = $request->input($settingKey.'-model-key');

            if ($reference && $settingStore->get($settingKey) !== $prefix) {
                if (! $request->filled('ref_lang')) {
                    Slug::query()
                        ->where('reference_type', $reference)
                        ->update(['prefix' => $prefix]);
                }

                event(new UpdatedPermalinkSettings($reference, $prefix, $request));

                Menu::clearCacheMenuItems();
            }

            $settingStore->set($settingKey, $prefix);
        }

        $settingStore->save();

        if ($hasChangedEndingUrl) {
            Menu::clearCacheMenuItems();
        }

        return $this
            ->httpResponse()
            ->setPreviousRoute('slug.settings')
            ->withUpdatedSuccessMessage();
    }
}
