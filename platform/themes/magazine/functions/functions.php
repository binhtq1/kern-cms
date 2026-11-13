<?php

use Kern\Media\Facades\RvMedia;
use Kern\Theme\Supports\ThemeSupport;

app()->booted(function (): void {
    RvMedia::addSize('magazine-hero', 1440, 810)
        ->addSize('magazine-card', 720, 480)
        ->addSize('magazine-compact', 480, 320);

    ThemeSupport::registerSocialLinks();
    ThemeSupport::registerSiteCopyright();
    ThemeSupport::registerDateFormatOption();
    ThemeSupport::registerLazyLoadImages();
    ThemeSupport::registerSocialSharing();
    ThemeSupport::registerSiteLogoHeight();

});
