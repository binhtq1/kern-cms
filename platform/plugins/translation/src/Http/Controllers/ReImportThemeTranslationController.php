<?php

namespace Kern\Translation\Http\Controllers;

use Kern\Base\Http\Controllers\BaseController;
use Kern\Translation\Manager;

class ReImportThemeTranslationController extends BaseController
{
    public function __invoke(Manager $manager)
    {
        $manager->updateThemeTranslations();

        return $this->httpResponse()->setMessage(trans('plugins/translation::translation.import_success_message'));
    }
}
