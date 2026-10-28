<?php

namespace Kern\Analytics\Http\Controllers\Settings;

use Kern\Analytics\Forms\AnalyticsSettingForm;
use Kern\Analytics\Http\Requests\Settings\AnalyticsSettingRequest;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class AnalyticsSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/analytics::analytics.settings.title'));

        return app(AdminSettingData::class)->render(AnalyticsSettingForm::create(), route('analytics.settings.update'));
    }

    public function update(AnalyticsSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate($request->validated());
    }
}
