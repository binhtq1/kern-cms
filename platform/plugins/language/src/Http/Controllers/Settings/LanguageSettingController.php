<?php

namespace Kern\Language\Http\Controllers\Settings;

use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Language\Http\Requests\Settings\LanguageSettingRequest;
use Kern\Setting\Http\Controllers\SettingController;

class LanguageSettingController extends SettingController
{
    public function update(LanguageSettingRequest $request): BaseHttpResponse
    {
        return $this->performUpdate([
            ...$request->validated(),
            'language_hide_languages' => $request->input('language_hide_languages', []),
        ]);
    }
}
