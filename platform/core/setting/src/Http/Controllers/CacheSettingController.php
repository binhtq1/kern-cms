<?php

namespace Kern\Setting\Http\Controllers;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Forms\CacheSettingForm;
use Kern\Setting\Http\Requests\CacheSettingRequest;
use Kern\Setting\Supports\AdminSettingData;

class CacheSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('core/setting::setting.cache.title'));

        return app(AdminSettingData::class)->render(CacheSettingForm::create(), route('settings.cache.update'));
    }

    public function update(CacheSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate($request->validated());
    }
}
