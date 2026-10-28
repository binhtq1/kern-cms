<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Support\Arr;
use Kern\Base\Facades\AdminAppearance;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Forms\AdminAppearanceSettingForm;
use Kern\Setting\Http\Requests\AdminAppearanceRequest;
use Kern\Setting\Supports\AdminSettingData;

class AdminAppearanceSettingController extends SettingController
{
    public function index()
    {
        $this->pageTitle(trans('core/setting::setting.admin_appearance.title'));

        return app(AdminSettingData::class)->render(AdminAppearanceSettingForm::create(), route('settings.admin-appearance.update'));
    }

    public function update(AdminAppearanceRequest $request): BaseHttpResponse
    {
        $localeDirectionKey = AdminAppearance::getSettingKey('locale_direction');

        $data = Arr::except($request->validated(), [$localeDirectionKey]);

        $isDemoModeEnabled = BaseHelper::hasDemoModeEnabled();

        $adminLocalDirection = $request->input($localeDirectionKey);

        if ($adminLocalDirection != setting($localeDirectionKey)) {
            session()->put('admin_locale_direction', $adminLocalDirection);
        }

        if (! $isDemoModeEnabled) {
            $data[$localeDirectionKey] = $adminLocalDirection;
        }

        $this->forceSaveSettings = ! $isDemoModeEnabled;

        return $this->performUpdate($data);
    }
}
