<?php

use Kern\Base\Facades\AdminHelper;

AdminHelper::registerRoutes(function (): void {
    require __DIR__.'/web-actions.php';
});
