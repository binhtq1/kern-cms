<?php

use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Routing\Events\RouteMatched;
use Illuminate\Support\Arr;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\FieldOptions\TextFieldOption;
use Kern\Base\Forms\Fields\ColorField;
use Kern\Base\Forms\Fields\NumberField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Forms\Fields\TextField;
use Kern\Base\Models\BaseQueryBuilder;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\Shortcode\Compilers\Shortcode as ShortcodeCompiler;
use Kern\Shortcode\Facades\Shortcode;
use Kern\Shortcode\Forms\ShortcodeForm;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Supports\ThemeSupport;

app('events')->listen(RouteMatched::class, function (): void {
    ThemeSupport::registerGoogleMapsShortcode();
    ThemeSupport::registerYoutubeShortcode();

    if (is_plugin_active('blog')) {
        Shortcode::setPreviewImage('blog-posts', Theme::asset()->url('images/ui-blocks/blog-posts.png'));

        Shortcode::register(
            'featured-posts',
            __('Featured posts'),
            __('Featured posts'),
            function (ShortcodeCompiler $shortcode) {
                $posts = get_featured_posts((int) $shortcode->limit ?: 5, [
                    'author',
                ]);

                if ($posts->isEmpty()) {
                    return null;
                }

                return Theme::partial('shortcodes.featured-posts', compact('posts', 'shortcode'));
            }
        );

        Shortcode::setAdminConfig('featured-posts', function (array $attributes) {
            return ShortcodeForm::createFromArray($attributes)
                ->withLazyLoading()
                ->add(
                    'limit',
                    NumberField::class,
                    TextFieldOption::make()->label(__('Limit'))->defaultValue(5)
                )
                ->add('background_color', ColorField::class, [
                    'label' => __('Background color'),
                    'default_value' => '#ecf0f1',
                ]);
        });

        Shortcode::setPreviewImage('featured-posts', Theme::asset()->url('images/ui-blocks/featured-posts.png'));

        Shortcode::register(
            'recent-posts',
            __('Recent posts'),
            __('Recent posts'),
            function (ShortcodeCompiler $shortcode) {
                $posts = get_latest_posts(7, [], ['slugable']);

                if ($posts->isEmpty()) {
                    return null;
                }

                $withSidebar = ($shortcode->with_sidebar ?: 'yes') === 'yes';

                return Theme::partial('shortcodes.recent-posts', [
                    'title' => $shortcode->title,
                    'withSidebar' => $withSidebar,
                    'posts' => $posts,
                    'shortcode' => $shortcode,
                ]);
            }
        );

        Shortcode::setPreviewImage('recent-posts', Theme::asset()->url('images/ui-blocks/recent-posts.png'));

        Shortcode::setAdminConfig('recent-posts', function (array $attributes) {
            return ShortcodeForm::createFromArray($attributes)
                ->withLazyLoading()
                ->add('title', TextField::class, TextFieldOption::make()->label(__('Title')))
                ->add('background_color', ColorField::class, [
                    'label' => __('Background color'),
                    'default_value' => '#fff',
                ])
                ->add(
                    'with_sidebar',
                    SelectField::class,
                    SelectFieldOption::make()
                        ->label(__('With top sidebar?'))
                        ->choices(['yes' => __('Yes'), 'no' => __('No')])
                );
        });

        Shortcode::register(
            'featured-categories-posts',
            __('Featured categories posts'),
            __('Featured categories posts'),
            function (ShortcodeCompiler $shortcode) {
                $with = [
                    'slugable',
                    'posts' => function (BelongsToMany|BaseQueryBuilder $query): void {
                        $query
                            ->wherePublished()
                            ->orderByDesc('created_at');
                    },
                    'posts.slugable',
                ];

                if (is_plugin_active('language-advanced') && LanguageAdvancedManager::isSupported(Post::class)) {
                    $with[] = 'posts.translations';
                }

                $posts = collect();

                $categoryIds = Shortcode::fields()->getIds('category_id', $shortcode);

                if ($categoryIds) {
                    $categories = Category::query()
                        ->with($with)
                        ->wherePublished()
                        ->whereIn('id', $categoryIds)
                        ->select([
                            'id',
                            'name',
                            'description',
                            'icon',
                        ])
                        ->get();
                } else {
                    $categories = get_featured_categories(2, $with);
                }

                foreach ($categories as $category) {
                    $posts = $posts->merge($category->posts->take(3));
                }

                $posts = $posts->sortByDesc('created_at');

                if ($posts->isEmpty()) {
                    return null;
                }

                $withSidebar = ($shortcode->with_sidebar ?: 'yes') === 'yes';

                return Theme::partial(
                    'shortcodes.featured-categories-posts',
                    [
                        'title' => $shortcode->title,
                        'withSidebar' => $withSidebar,
                        'posts' => $posts,
                        'shortcode' => $shortcode,
                    ]
                );
            }
        );

        Shortcode::setPreviewImage(
            'featured-categories-posts',
            Theme::asset()->url('images/ui-blocks/featured-categories-posts.png')
        );

        Shortcode::setAdminConfig('featured-categories-posts', function (array $attributes) {
            $categories = get_blog_category_choices();
            $categoryIds = Arr::get($attributes, 'category_id');

            if (! is_array($categoryIds)) {
                $categoryIds = $categoryIds ? explode(',', $categoryIds) : null;
            }

            return ShortcodeForm::createFromArray($attributes)
                ->withLazyLoading()
                ->add('title', TextField::class, TextFieldOption::make()->label(__('Title')))
                ->add(
                    'category_id',
                    SelectField::class,
                    SelectFieldOption::make()
                        ->label(__('Choose categories'))
                        ->choices($categories)
                        ->selected($categoryIds)
                        ->searchable()
                        ->multiple(),
                )
                ->add(
                    'with_sidebar',
                    SelectField::class,
                    SelectFieldOption::make()
                        ->label(__('With primary sidebar?'))
                        ->choices(['yes' => __('Yes'), 'no' => __('No')])
                )
                ->add('background_color', ColorField::class, [
                    'label' => __('Background color'),
                    'default_value' => '#ecf0f1',
                ]);
        });
    }

    if (is_plugin_active('contact')) {
        Shortcode::setPreviewImage('contact-form', Theme::asset()->url('images/ui-blocks/contact-form.png'));
    }

    if (is_plugin_active('gallery')) {
        Shortcode::setPreviewImage('gallery', Theme::asset()->url('images/ui-blocks/gallery.png'));

        Shortcode::register(
            'all-galleries',
            __('All galleries'),
            __('All galleries'),
            function (ShortcodeCompiler $shortcode) {
                if (! function_exists('render_galleries')) {
                    return null;
                }

                $galleries = render_galleries((int) $shortcode->limit ?: 8);

                if (! $galleries) {
                    return null;
                }

                return Theme::partial('shortcodes.all-galleries', compact('galleries', 'shortcode'));
            }
        );

        Shortcode::setPreviewImage('all-galleries', Theme::asset()->url('images/ui-blocks/all-galleries.png'));

        Shortcode::setAdminConfig('all-galleries', function (array $attributes) {
            return ShortcodeForm::createFromArray($attributes)
                ->withLazyLoading()
                ->add(
                    'limit',
                    NumberField::class,
                    TextFieldOption::make()->label(__('Limit'))->defaultValue(8)
                )
                ->add('background_color', ColorField::class, [
                    'label' => __('Background color'),
                    'default_value' => '#fff',
                ]);
        });
    }
});
