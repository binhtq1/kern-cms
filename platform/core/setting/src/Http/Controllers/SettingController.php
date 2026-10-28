<?php

namespace Kern\Setting\Http\Controllers;

use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\Breadcrumb;
use Kern\Setting\Http\Controllers\Concerns\InteractsWithSettings;

abstract class SettingController extends BaseController
{
    use InteractsWithSettings;

    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('core/setting::setting.title'), route('settings.index'));
    }
}
