<?php

use Illuminate\Routing\Events\RouteMatched;
use Kern\Menu\Facades\Menu;

app('events')->listen(RouteMatched::class, fn () => Menu::useMenuItemIconImage());
