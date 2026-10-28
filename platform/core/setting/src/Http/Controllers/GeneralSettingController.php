<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Support\Arr;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\Language;
use Kern\Setting\Forms\GeneralSettingForm;
use Kern\Setting\Http\Requests\GeneralSettingRequest;
use Kern\Setting\Supports\AdminSettingData;

class GeneralSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('core/setting::setting.general_setting'));

        $form = GeneralSettingForm::create();

        return app(AdminSettingData::class)->render($form, route('settings.general.update'));
    }

    public function update(GeneralSettingRequest $request): BaseHttpResponse
    {
        $data = Arr::except($request->input(), [
            'locale',
        ]);

        $locale = $request->input('locale');
        if ($locale && array_key_exists($locale, Language::getAvailableLocales())) {
            session()->put('site-locale', $locale);
        }

        $isDemoModeEnabled = BaseHelper::hasDemoModeEnabled();

        if (! $isDemoModeEnabled) {
            $data['locale'] = $locale;
        }

        return $this->performUpdate($data);
    }
}
