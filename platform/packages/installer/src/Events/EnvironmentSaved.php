<?php

namespace Kern\Installer\Events;

use Illuminate\Http\Request;
use Kern\Base\Events\Event;

class EnvironmentSaved extends Event
{
    public function __construct(public Request $request) {}
}
