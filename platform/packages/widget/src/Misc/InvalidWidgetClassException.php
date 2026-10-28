<?php

namespace Kern\Widget\Misc;

use Exception;
use Kern\Widget\AbstractWidget;

class InvalidWidgetClassException extends Exception
{
    protected $message = 'Widget class must extend class '.AbstractWidget::class;
}
