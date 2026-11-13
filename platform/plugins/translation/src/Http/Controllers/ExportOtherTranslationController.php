<?php

namespace Kern\Translation\Http\Controllers;

use Kern\DataSynchronize\Exporter\Exporter;
use Kern\DataSynchronize\Http\Controllers\ExportController;
use Kern\Translation\Exporters\OtherTranslationExporter;

class ExportOtherTranslationController extends ExportController
{
    protected function getExporter(): Exporter
    {
        return OtherTranslationExporter::make();
    }
}
