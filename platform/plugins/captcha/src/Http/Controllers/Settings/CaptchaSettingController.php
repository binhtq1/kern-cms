<?php

namespace Kern\Captcha\Http\Controllers\Settings;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Captcha\Forms\CaptchaSettingForm;
use Kern\Captcha\Http\Requests\Settings\CaptchaSettingRequest;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;

class CaptchaSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/captcha::captcha.settings.title'));

        return app(AdminSettingData::class)->render(CaptchaSettingForm::create(), route('captcha.settings.update'));
    }

    public function update(CaptchaSettingRequest $request): BaseHttpResponse
    {
        $request->merge([
            'enable_math_captcha_for_contact_form' => $request->input('enable_math_captcha_kern_contact_forms_fronts_contact_form'),
            'enable_math_captcha_for_newsletter_form' => $request->input('enable_math_captcha_kern_newsletter_forms_fronts_newsletter_form'),
        ]);

        return $this->performUpdate($request->validated());
    }
}
