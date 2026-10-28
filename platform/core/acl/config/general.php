<?php

return [
    /*
    |--------------------------------------------------------------------------
    | Activations
    |--------------------------------------------------------------------------
    |
    | Here you may specify the activations model used and the time (in seconds)
    | which activation codes expire. By default, activation codes expire after
    | three days. The lottery is used for garbage collection, expired
    | codes will be cleared automatically based on the provided odds.
    |
    */

    'activations' => [

        'expires' => 259200,

        'lottery' => [2, 100],

    ],

    'backgrounds' => [
        'platform/core/acl/resources/js/admin/assets/backgrounds/1.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/2.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/3.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/4.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/5.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/6.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/7.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/8.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/9.jpg',
        'platform/core/acl/resources/js/admin/assets/backgrounds/10.jpg',
    ],
];
