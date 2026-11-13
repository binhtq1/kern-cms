<?php

namespace Kern\Translation\Http\Controllers;

use Kern\DataSynchronize\Http\Controllers\ImportController;
use Kern\DataSynchronize\Importer\Importer;
use Kern\Translation\Importers\ThemeTranslationImporter;

class ImportThemeTranslationController extends ImportController
{
    protected function getImporter(): Importer
    {
        return ThemeTranslationImporter::make();
    }
}
