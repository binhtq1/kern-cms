<?php

namespace Kern\Api\Http\Controllers;

use Kern\Api\Forms\Settings\ApiSettingForm;
use Kern\Api\Http\Requests\ApiSettingRequest;
use Kern\Api\Tables\SanctumTokenTable;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class ApiController extends SettingController
{
    public function edit(SanctumTokenTable $sanctumTokenTable)
    {
        $this->pageTitle(trans('packages/api::api.settings'));

        $this->breadcrumb()
            ->add(trans('core/setting::setting.title'), route('settings.index'))
            ->add(trans('packages/api::api.settings'));

        $form = ApiSettingForm::create();

        $sanctumTokenTable->setAjaxUrl(route('api.sanctum-token.index'));

        return app(AdminSettingData::class)->render($form, route('api.settings.update'))
            ->with('tokenUrl', route('api.sanctum-token.index'))->with('plainTextToken', session()->get('plainTextToken'));
    }

    public function update(ApiSettingRequest $request)
    {
        return $this->performUpdate($request->validated());
    }
}
