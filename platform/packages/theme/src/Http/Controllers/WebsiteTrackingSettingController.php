<?php

namespace Kern\Theme\Http\Controllers;

use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;
use Kern\Theme\Forms\Settings\WebsiteTrackingSettingForm;
use Kern\Theme\Http\Requests\WebsiteTrackingSettingRequest;

class WebsiteTrackingSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('packages/theme::theme.settings.website_tracking.title'));

        return app(AdminSettingData::class)->render(WebsiteTrackingSettingForm::create(), route('settings.website-tracking.update'));
    }

    public function update(WebsiteTrackingSettingRequest $request)
    {
        return $this->performUpdate(
            $request->validated()
        )->withUpdatedSuccessMessage();
    }
}
