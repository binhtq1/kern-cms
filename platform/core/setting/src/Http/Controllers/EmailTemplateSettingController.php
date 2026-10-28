<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Forms\EmailTemplateSettingForm;
use Kern\Setting\Http\Requests\EmailTemplateSettingRequest;
use Kern\Setting\Supports\AdminSettingData;

class EmailTemplateSettingController extends SettingController
{
    public function index(): View|Response
    {
        $this->pageTitle(trans('core/setting::setting.email.email_templates'));

        $form = EmailTemplateSettingForm::create();

        $adapter = app(AdminSettingData::class);
        if (($fields = $adapter->fields($form)) !== null) {
            return Inertia::render('Settings/Form', ['kind' => 'email-template', 'emailNavigation' => $adapter->emailNavigation(), 'description' => strip_tags($form->getFormOption('section_description', '')), 'title' => trans('core/setting::setting.email.email_templates'), 'fields' => $fields, 'submit' => route('settings.email.template.update-settings'), 'templates' => $adapter->templates(), 'media' => request()->user()->hasPermission('media.index') ? ['list' => route('media.picker'), 'upload' => route('media.picker-upload')] : null]);
        }

        abort(503, 'The email settings integration does not provide supported React fields.');
    }

    public function update(EmailTemplateSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate(
            $request->validated()
        )->withUpdatedSuccessMessage();
    }
}
