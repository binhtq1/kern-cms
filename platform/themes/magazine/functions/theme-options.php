<?php

use Kern\Theme\Events\RenderingThemeOptionSettings;

app('events')->listen(RenderingThemeOptionSettings::class, function (): void {
    theme_option()
        ->setField([
            'id' => 'homepage_featured_limit',
            'section_id' => 'opt-text-subsection-general',
            'type' => 'number',
            'label' => __('Featured stories on homepage'),
            'attributes' => [
                'name' => 'homepage_featured_limit',
                'value' => 3,
                'options' => ['min' => 1, 'max' => 4],
            ],
        ])
        ->setField([
            'id' => 'homepage_latest_limit',
            'section_id' => 'opt-text-subsection-general',
            'type' => 'number',
            'label' => __('Latest stories on homepage'),
            'attributes' => [
                'name' => 'homepage_latest_limit',
                'value' => 6,
                'options' => ['min' => 3, 'max' => 12],
            ],
        ])
        ->setField([
            'id' => 'homepage_category_posts_limit',
            'section_id' => 'opt-text-subsection-general',
            'type' => 'number',
            'label' => __('Stories per featured category'),
            'attributes' => [
                'name' => 'homepage_category_posts_limit',
                'value' => 3,
                'options' => ['min' => 1, 'max' => 6],
            ],
        ]);
});
