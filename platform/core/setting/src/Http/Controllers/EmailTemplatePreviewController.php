<?php

namespace Kern\Setting\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Setting\Http\Traits\GetEmailTemplateDataTrait;
use Kern\Setting\Supports\AdminSettingData;

class EmailTemplatePreviewController extends BaseController
{
    use GetEmailTemplateDataTrait;

    public function __invoke(Request $request, string $type, string $module, string $template): View|Response
    {
        [$inputData, $variables] = $this->getData($request, $type, $module, $template);

        if (! empty($inputData)) {
            foreach ($inputData as $key => $value) {
                $inputData[BaseHelper::stringify($key)] = BaseHelper::clean(BaseHelper::stringify($value));
            }
        }

        $routeParams = [$type, $module, $template, 'ref_lang' => request()->input('ref_lang')];

        $backUrl = route('settings.email.template.edit', $routeParams);

        $iframeUrl = route('settings.email.template.iframe', $routeParams);

        return Inertia::render('Settings/EmailPreview', ['emailNavigation' => app(AdminSettingData::class)->emailNavigation(), 'variables' => array_map(fn (string $label): string => strip_tags(trans($label)), $variables), 'values' => $inputData, 'back' => $backUrl, 'iframe' => $iframeUrl]);
    }
}
