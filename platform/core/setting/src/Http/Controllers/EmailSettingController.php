<?php

namespace Kern\Setting\Http\Controllers;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Forms\EmailSettingForm;
use Kern\Setting\Http\Requests\EmailSettingRequest;
use Kern\Setting\Supports\AdminSettingData;

class EmailSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('core/setting::setting.panel.email'));

        return app(AdminSettingData::class)->render(EmailSettingForm::create())
            ->with('templates', app(AdminSettingData::class)->templates());
    }

    public function update(EmailSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate($request->validated());
    }
}
