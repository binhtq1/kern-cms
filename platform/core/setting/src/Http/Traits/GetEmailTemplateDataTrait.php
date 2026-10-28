<?php

namespace Kern\Setting\Http\Traits;

use Illuminate\Http\Request;
use Illuminate\Support\Arr;
use Kern\Base\Facades\EmailHandler;

trait GetEmailTemplateDataTrait
{
    public function getData(Request $request, string $type, string $module, string $template): array
    {
        $emailHandler = EmailHandler::setModule($module)
            ->setType($type)
            ->setTemplate($template);

        $variables = $emailHandler->getVariables($type, $module, $template);

        $coreVariables = $emailHandler->getCoreVariables();

        Arr::forget($variables, array_keys($coreVariables));

        $inputData = $request->only(array_keys($variables));

        return [$inputData, $variables, $emailHandler];
    }
}
