<?php

namespace Kern\Translation\Http\Controllers;

use Kern\DataSynchronize\Exporter\Exporter;
use Kern\DataSynchronize\Http\Controllers\ExportController;
use Kern\Translation\Exporters\ThemeTranslationExporter;

class ExportThemeTranslationController extends ExportController
{
    protected function getExporter(): Exporter
    {
        return ThemeTranslationExporter::make();
    }
}
