<?php

/**
 * Date: 22/07/2015
 * Time: 8:11 PM
 */

return [
    'offline' => env('ASSETS_OFFLINE', true),
    'enable_version' => env('ASSETS_ENABLE_VERSION', true),
    'version' => env('ASSETS_VERSION'),
    'scripts' => [
        'core-ui',
        'datepicker',
        'core',
        'app',
        'fslightbox',
    ],
    'styles' => [
        'fontawesome',
        'datepicker',
    ],
    'resources' => [
        'scripts' => [
            'core-ui' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/js/core-ui.js',
                ],
            ],
            'core' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/js/core.js',
                ],
            ],
            'app' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => [
                        '/vendor/core/core/base/js/app.js',
                    ],
                ],
            ],
            'vue' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => [
                        '/vendor/core/core/base/libraries/vue.global.min.js',
                    ],
                ],
            ],
            'vue-app' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/js/vue-app.js',
                ],
            ],
            'bootstrap' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => [
                        '/vendor/core/core/base/libraries/bootstrap.bundle.min.js',
                    ],
                ],
            ],
            'dropzone' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/dropzone/dropzone.js',
                ],
            ],
            'fslightbox' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/fslightbox.js',
                    'cdn' => '//fastly.jsdelivr.net/npm/fslightbox@3.4.1/index.min.js',
                ],
            ],
            'raphael' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => [
                        '/vendor/core/core/base/libraries/raphael-min.js',
                    ],
                ],
            ],
            'cropper' => [
                'use_cdn' => true,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/cropper/cropper.min.js',
                    'cdn' => '//cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.js',
                ],
            ],
            'datepicker' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/flatpickr/flatpickr.min.js',
                    'cdn' => '//fastly.jsdelivr.net/npm/flatpickr',
                ],
            ],
            'sortable' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/sortable/sortable.min.js',
                ],
            ],
            'moment' => [
                'use_cdn' => true,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/moment-with-locales.min.js',
                    'cdn' => '//cdnjs.cloudflare.com/ajax/libs/moment.js/2.11.1/moment-with-locales.min.js',
                ],
            ],
            'form-validation' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/js-validation/js/js-validation.js',
                ],
            ],
            'apexchart' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/apexchart/apexcharts.min.js',
                ],
            ],
            'coloris' => [
                'use_cdn' => true,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/coloris/coloris.min.js',
                    'cdn' => '//fastly.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.js',
                ],
            ],
            'tagify' => [
                'use_cdn' => true,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/tagify/tagify.js',
                    'cdn' => '//cdnjs.cloudflare.com/ajax/libs/tagify/4.16.4/tagify.min.js',
                ],
            ],
        ],
        'styles' => [
            'core' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/css/core.css',
                ],
            ],
            'fontawesome' => [
                'use_cdn' => true,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/font-awesome/css/fontawesome.min.css',
                    'cdn' => '//use.fontawesome.com/releases/v6.1.1/css/all.css',
                ],
            ],
            'dropzone' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/dropzone/dropzone.css',
                ],
            ],
            'kendo' => [
                'use_cdn' => false,
                'location' => 'footer',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/kendo/kendo.min.css',
                ],
            ],
            'cropper' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/cropper/cropper.min.css',
                    'cdn' => '//cdnjs.cloudflare.com/ajax/libs/cropperjs/1.6.1/cropper.min.css',
                ],
            ],
            'datepicker' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/flatpickr/flatpickr.min.css',
                    'cdn' => '//fastly.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css',
                ],
            ],
            'apexchart' => [
                'use_cdn' => false,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/apexchart/apexcharts.css',
                ],
            ],
            'coloris' => [
                'use_cdn' => true,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/coloris/coloris.min.css',
                    'cdn' => '//fastly.jsdelivr.net/gh/mdbassit/Coloris@latest/dist/coloris.min.css',
                ],
            ],
            'tagify' => [
                'use_cdn' => true,
                'location' => 'header',
                'src' => [
                    'local' => '/vendor/core/core/base/libraries/tagify/tagify.css',
                    'cdn' => '//cdnjs.cloudflare.com/ajax/libs/tagify/4.16.4/tagify.css',
                ],
            ],
        ],
    ],
];
