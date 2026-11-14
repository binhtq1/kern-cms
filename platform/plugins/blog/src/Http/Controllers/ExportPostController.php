<?php

namespace Kern\Blog\Http\Controllers;

use Kern\Blog\Exporters\PostExporter;
use Kern\DataSynchronize\Exporter\Exporter;
use Kern\DataSynchronize\Http\Controllers\ExportController;

class ExportPostController extends ExportController
{
    protected function getExporter(): Exporter
    {
        return PostExporter::make();
    }
}
