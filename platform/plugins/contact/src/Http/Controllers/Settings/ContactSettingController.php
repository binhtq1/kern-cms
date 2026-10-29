<?php

namespace Kern\Contact\Http\Controllers\Settings;

use Illuminate\Support\Arr;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Contact\Forms\Settings\ContactSettingForm;
use Kern\Contact\Http\Requests\Settings\ContactSettingRequest;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class ContactSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/contact::contact.settings.title'));

        $form = ContactSettingForm::create();

        return app(AdminSettingData::class)->render($form);
    }

    public function update(ContactSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate(Arr::except($request->validated(), [
            'receiver_emails_for_validation',
            'blacklist_keywords_for_validation',
        ]));
    }
}
