<?php

namespace Kern\Member\Http\Controllers\Settings;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Member\Forms\Settings\MemberSettingForm;
use Kern\Member\Http\Requests\Settings\MemberSettingRequest;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class MemberSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/member::settings.title'));

        $form = MemberSettingForm::create();

        return app(AdminSettingData::class)->render($form);
    }

    public function update(MemberSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate($request->validated());
    }
}
