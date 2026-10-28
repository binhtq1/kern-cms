<?php

namespace Kern\SocialLogin\Http\Controllers\Settings;

use Illuminate\Support\Arr;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Http\Controllers\SettingController;
use Kern\Setting\Supports\AdminSettingData;
use Kern\SocialLogin\Facades\SocialService;
use Kern\SocialLogin\Forms\SocialLoginSettingForm;
use Kern\SocialLogin\Http\Requests\Settings\SocialLoginSettingRequest;

class SocialLoginSettingController extends SettingController
{
    public function edit()
    {
        $this->pageTitle(trans('plugins/social-login::social-login.settings.title'));

        return app(AdminSettingData::class)->render(SocialLoginSettingForm::create(), route('social-login.settings.update'));
    }

    public function update(SocialLoginSettingRequest $request): BaseHttpResponse
    {
        $prefix = 'social_login_';

        $data = [
            "{$prefix}enable" => $request->input("{$prefix}enable"),
            "{$prefix}style" => $request->input("{$prefix}style"),
        ];

        foreach (SocialService::getProviders() as $provider => $item) {
            $prefix = 'social_login_'.$provider.'_';

            $data["{$prefix}enable"] = $request->input("{$prefix}enable");

            foreach ($item['data'] as $input) {
                if (
                    ! in_array(app()->environment(), SocialService::getEnvDisableData()) ||
                    ! in_array($input, Arr::get($item, 'disable', []))
                ) {
                    $data["{$prefix}{$input}"] = $request->input("{$prefix}{$input}");
                }
            }
        }

        return $this->performUpdate($data);
    }
}
