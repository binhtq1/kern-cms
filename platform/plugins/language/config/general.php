<?php

use Kern\Menu\Models\Menu;
use Kern\Menu\Models\MenuNode;
use Kern\Page\Models\Page;

return [
    'supported' => [
        Page::class,
        Menu::class,
        MenuNode::class,
    ],
];
