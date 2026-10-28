<?php

namespace Kern\Setting\Http\Controllers\Concerns;

use Illuminate\Support\Arr;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Setting\Facades\Setting;

trait InteractsWithSettings
{
    protected bool $forceSaveSettings = false;

    protected function saveSettings(array $data, string $prefix = ''): void
    {
        foreach (Arr::except($data, ['_token', '_method']) as $settingKey => $settingValue) {
            if (is_array($settingValue)) {
                $settingValue = json_encode(array_filter($settingValue));
            }

            Setting::set($prefix.$settingKey, (string) $settingValue, $this->forceSaveSettings);
        }

        Setting::save();
    }

    protected function performUpdate(array $data, string $prefix = ''): BaseHttpResponse
    {
        $this->saveSettings($data, $prefix);

        if (! method_exists($this, 'httpResponse')) {
            return BaseHttpResponse::make();
        }

        DashboardMenu::clearCaches();

        return $this
            ->httpResponse()
            ->withUpdatedSuccessMessage();
    }
}
