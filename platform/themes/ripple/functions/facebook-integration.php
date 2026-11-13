<?php

use Illuminate\Routing\Events\RouteMatched;
use Kern\Theme\Supports\ThemeSupport;

app('events')->listen(RouteMatched::class, fn () => ThemeSupport::registerFacebookIntegration());
