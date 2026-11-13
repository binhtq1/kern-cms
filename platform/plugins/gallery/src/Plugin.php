<?php

namespace Kern\Gallery;

use Illuminate\Support\Facades\Schema;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('galleries');
        Schema::dropIfExists('gallery_meta');
        Schema::dropIfExists('galleries_translations');
        Schema::dropIfExists('gallery_meta_translations');
    }
}
