<?php

namespace Kern\PluginManagement\Listeners;

use Exception;
use Illuminate\Support\Facades\File;
use Kern\Base\Facades\BaseHelper;
use Kern\PluginManagement\PluginManifest;

class ClearPluginCaches
{
    public function __construct(protected PluginManifest $manifest) {}

    public function handle(): void
    {
        try {
            if (File::isFile($pluginsPath = $this->manifest->getManifestFilePath())) {
                File::delete($pluginsPath);
            }
        } catch (Exception $exception) {
            BaseHelper::logError($exception);
        }
    }
}
