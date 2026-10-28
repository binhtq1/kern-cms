<?php

namespace Kern\Setting\Http\Controllers;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Forms\DataTableSettingForm;
use Kern\Setting\Http\Requests\DataTableSettingRequest;
use Kern\Setting\Supports\AdminSettingData;

class DataTableSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('core/setting::setting.datatable.title'));

        return app(AdminSettingData::class)->render(DataTableSettingForm::create(), route('settings.datatables.update'));
    }

    public function update(DataTableSettingRequest $request): BaseHttpResponse
    {
        if (setting('datatables_default_show_column_visibility')
            != $request->input('datatables_default_show_column_visibility')) {
            setting()->set('datatables_random_hash', hash('sha256', (string) time()));
        }

        return $this->performUpdate($request->validated());
    }
}
