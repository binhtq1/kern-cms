<?php

namespace Kern\Blog\Http\Controllers\Settings;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Blog\Forms\Settings\BlogSettingForm;
use Kern\Blog\Http\Requests\Settings\BlogSettingRequest;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class BlogSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/blog::base.settings.title'));

        $form = BlogSettingForm::create();

        return app(AdminSettingData::class)->render($form);
    }

    public function update(BlogSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate($request->validated());
    }
}
