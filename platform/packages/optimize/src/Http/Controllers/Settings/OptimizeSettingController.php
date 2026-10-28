<?php

namespace Kern\Optimize\Http\Controllers\Settings;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Optimize\Forms\Settings\OptimizeSettingForm;
use Kern\Optimize\Http\Requests\OptimizeSettingRequest;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class OptimizeSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('packages/optimize::optimize.settings.title'));

        return app(AdminSettingData::class)->render(OptimizeSettingForm::create(), route('optimize.settings.update'));
    }

    public function update(OptimizeSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate($request->validated());
    }
}
