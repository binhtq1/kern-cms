<?php

use Kern\Base\Forms\FieldOptions\MediaImageFieldOption;
use Kern\Base\Forms\Fields\MediaImageField;
use Kern\Base\Forms\FormAbstract;
use Kern\Base\Rules\MediaImageRule;
use Kern\Blog\Models\Post;
use Kern\Media\Facades\RvMedia;
use Kern\Member\Forms\PostForm as MemberPostForm;
use Kern\Page\Models\Page;
use Kern\Theme\Facades\Theme;
use Kern\Theme\Supports\ThemeSupport;
use Kern\Theme\Typography\TypographyItem;
use Kern\Widget\Events\RenderingWidgetSettings;

app()->booted(function (): void {
    RvMedia::addSize('featured', 565, 375)
        ->addSize('medium', 540, 360);

    Theme::typography()
        ->registerFontFamilies([
            new TypographyItem('primary', __('Primary'), theme_option('primary_font', 'Roboto')),
        ])
        ->registerFontSizes([
            new TypographyItem('h1', __('Heading 1'), 28),
            new TypographyItem('h2', __('Heading 2'), 24),
            new TypographyItem('h3', __('Heading 3'), 22),
            new TypographyItem('h4', __('Heading 4'), 20),
            new TypographyItem('h5', __('Heading 5'), 18),
            new TypographyItem('h6', __('Heading 6'), 16),
            new TypographyItem('body', __('Body'), 14),
        ]);

    ThemeSupport::registerSocialLinks();
    ThemeSupport::registerToastNotification();
    ThemeSupport::registerPreloader();
    ThemeSupport::registerSiteCopyright();
    ThemeSupport::registerDateFormatOption();
    ThemeSupport::registerLazyLoadImages();
    ThemeSupport::registerSocialSharing();
    ThemeSupport::registerSiteLogoHeight();

    register_page_template([
        'no-sidebar' => __('No sidebar'),
    ]);

    app('events')->listen(RenderingWidgetSettings::class, function (): void {
        register_sidebar([
            'id' => 'top_sidebar',
            'name' => __('Top sidebar'),
            'description' => __('Area for widgets on the top sidebar'),
        ]);

        register_sidebar([
            'id' => 'footer_sidebar',
            'name' => __('Footer sidebar'),
            'description' => __('Area for footer widgets'),
        ]);
    });

    FormAbstract::extend(function (FormAbstract $form): void {
        $model = $form->getModel();

        if (! $model instanceof Post && ! $model instanceof Page) {
            return;
        }

        $form
            ->addAfter(
                'image',
                'banner_image',
                MediaImageField::class,
                MediaImageFieldOption::make()->label(__('Banner image (1920x170px)'))->metadata()
            );
    }, 124);

    FormAbstract::afterSaving(function (FormAbstract $form): void {
        if (! $form instanceof MemberPostForm) {
            return;
        }

        $request = $form->getRequest();

        $request->validate([
            'banner_image_input' => ['nullable', new MediaImageRule],
        ]);

        /**
         * @var Post $model
         */
        $model = $form->getModel();

        $model->saveMetaDataFromFormRequest('banner_image', $request);
    }, 175);
});
