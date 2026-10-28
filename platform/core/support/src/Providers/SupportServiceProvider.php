<?php

namespace Kern\Support\Providers;

use Kern\Base\Supports\ServiceProvider;

class SupportServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        $this->app['files']->requireOnce(core_path('support/helpers/common.php'));
        $this->app['files']->requireOnce(core_path('support/helpers/plugins.php'));
    }
}
