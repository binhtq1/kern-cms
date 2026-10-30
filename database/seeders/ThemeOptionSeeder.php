<?php

namespace Database\Seeders;

use Kern\Base\Supports\BaseSeeder;
use Kern\Page\Models\Page;
use Kern\Theme\Database\Traits\HasThemeOptionSeeder;
use Kern\Theme\Supports\ThemeSupport;

class ThemeOptionSeeder extends BaseSeeder
{
    use HasThemeOptionSeeder;

    public function run(): void
    {
        $allowedTypes = config('core.media.media.allowed_mime_types');
        try {
            config(['core.media.media.allowed_mime_types' => $allowedTypes.',svg']);
            $this->uploadFiles('general');
        } finally {
            config(['core.media.media.allowed_mime_types' => $allowedTypes]);
        }

        $this->createThemeOptions([
            'site_title' => 'Kern CMS',
            'seo_description' => 'Built with Kern CMS.',
            'copyright' => '©%Y Your Company. All rights reserved.',
            'favicon' => $this->filePath('general/favicon.svg'),
            'logo' => $this->filePath('general/logo.svg'),
            'website' => url('/'),
            'contact_email' => 'demo@example.test',
            'site_description' => 'Built with Kern CMS.',
            'phone' => '+(123) 345-6789',
            'address' => '214 West Arnold St. New York, NY 10002',
            'cookie_consent_message' => 'Your experience on this site will be improved by allowing cookies ',
            'cookie_consent_learn_more_url' => '/cookie-policy',
            'cookie_consent_learn_more_text' => 'Cookie Policy',
            'homepage_id' => Page::query()->value('id'),
            'blog_page_id' => Page::query()->skip(1)->value('id'),
            'primary_color' => '#AF0F26',
            'primary_font' => 'Roboto',
            'social_links' => ThemeSupport::getDefaultSocialLinksData(),
            'lazy_load_images' => 1,
            'lazy_load_placeholder_image' => $this->filePath('general/preloader.gif'),
        ]);
    }
}
