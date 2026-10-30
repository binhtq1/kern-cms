<?php

namespace Database\Seeders;

use Kern\Base\Supports\BaseSeeder;
use Kern\Widget\Database\Traits\HasWidgetSeeder;
use Kern\Widget\Widgets\CoreSimpleMenu;

class WidgetSeeder extends BaseSeeder
{
    use HasWidgetSeeder;

    public function run(): void
    {
        $data = [
            [
                'widget_id' => 'RecentPostsWidget',
                'sidebar_id' => 'footer_sidebar',
                'position' => 0,
                'data' => [
                    'id' => 'RecentPostsWidget',
                    'name' => 'Recent Posts',
                    'number_display' => 5,
                ],
            ],
            [
                'widget_id' => 'RecentPostsWidget',
                'sidebar_id' => 'top_sidebar',
                'position' => 0,
                'data' => [
                    'id' => 'RecentPostsWidget',
                    'name' => 'Recent Posts',
                    'number_display' => 5,
                ],
            ],
            [
                'widget_id' => 'TagsWidget',
                'sidebar_id' => 'primary_sidebar',
                'position' => 0,
                'data' => [
                    'id' => 'TagsWidget',
                    'name' => 'Tags',
                    'number_display' => 5,
                ],
            ],
            [
                'widget_id' => 'BlogCategoriesWidget',
                'sidebar_id' => 'primary_sidebar',
                'position' => 1,
                'data' => [
                    'id' => 'BlogCategoriesWidget',
                    'name' => 'Categories',
                    'display_posts_count' => 'yes',
                ],
            ],
            [
                'widget_id' => 'CustomMenuWidget',
                'sidebar_id' => 'primary_sidebar',
                'position' => 2,
                'data' => [
                    'id' => 'CustomMenuWidget',
                    'name' => 'Social',
                    'menu_id' => 'social',
                ],
            ],
            [
                'widget_id' => CoreSimpleMenu::class,
                'sidebar_id' => 'footer_sidebar',
                'position' => 1,
                'data' => [
                    'id' => CoreSimpleMenu::class,
                    'name' => 'Favorite Websites',
                    'items' => [
                        [
                            [
                                'key' => 'label',
                                'value' => 'Speckyboy Magazine',
                            ],
                            [
                                'key' => 'url',
                                'value' => 'https://speckyboy.com',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '1',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Tympanus-Codrops',
                            ],
                            [
                                'key' => 'url',
                                'value' => 'https://tympanus.com',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '1',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Kern Blog',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/blog',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '1',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Kern CMS source',
                            ],
                            [
                                'key' => 'url',
                                'value' => 'https://github.com/binhtq1/kern-cms',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '1',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'CreativeBlog',
                            ],
                            [
                                'key' => 'url',
                                'value' => 'https://www.creativebloq.com',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '1',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Kern CMS',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '1',
                            ],
                        ],
                    ],
                ],
            ],
            [
                'widget_id' => CoreSimpleMenu::class,
                'sidebar_id' => 'footer_sidebar',
                'position' => 2,
                'data' => [
                    'id' => CoreSimpleMenu::class,
                    'name' => 'My Links',
                    'items' => [
                        [
                            [
                                'key' => 'label',
                                'value' => 'Home Page',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '0',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Contact',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/contact',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '0',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Green Technology',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/green-technology',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '0',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Augmented Reality (AR) ',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/augmented-reality-ar',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '0',
                            ],
                        ],
                        [
                            [
                                'key' => 'label',
                                'value' => 'Galleries',
                            ],
                            [
                                'key' => 'url',
                                'value' => '/galleries',
                            ],
                            [
                                'key' => 'attributes',
                                'value' => '',
                            ],
                            [
                                'key' => 'is_open_new_tab',
                                'value' => '0',
                            ],
                        ],
                    ],
                ],
            ],
        ];

        $this->createWidgets($data);
    }
}
