<?php

namespace Kern\Base\Events;

use Illuminate\Foundation\Events\Dispatchable;
use Kern\Base\Forms\FormAbstract;

class FormRendering
{
    use Dispatchable;

    public function __construct(public FormAbstract $form) {}
}
