<?php

namespace Kern\Blog\Http\Controllers;

use Kern\Blog\Importers\PostImporter;
use Kern\DataSynchronize\Http\Controllers\ImportController;
use Kern\DataSynchronize\Importer\Importer;

class ImportPostController extends ImportController
{
    protected function getImporter(): Importer
    {
        return PostImporter::make();
    }
}
