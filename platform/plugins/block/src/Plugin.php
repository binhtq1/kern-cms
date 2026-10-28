<?php

namespace Kern\Block;

use Illuminate\Support\Facades\Schema;
use Kern\PluginManagement\Abstracts\PluginOperationAbstract;

class Plugin extends PluginOperationAbstract
{
    public static function remove(): void
    {
        Schema::dropIfExists('blocks');
        Schema::dropIfExists('blocks_translations');
    }
}
