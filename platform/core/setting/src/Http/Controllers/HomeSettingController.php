<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Setting\Supports\AdminSettingData;

class HomeSettingController extends BaseController
{
    public function index(): View|Response
    {
        $this->pageTitle(trans('core/setting::setting.title'));

        return Inertia::render('Settings/Index', ['title' => trans('core/setting::setting.title'), 'sections' => app(AdminSettingData::class)->sections()]);
    }
}
