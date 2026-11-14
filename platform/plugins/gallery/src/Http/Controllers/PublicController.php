<?php

namespace Kern\Gallery\Http\Controllers;

use Kern\Base\Http\Controllers\BaseController;
use Kern\Gallery\Facades\Gallery;
use Kern\Gallery\Models\Gallery as GalleryModel;
use Kern\SeoHelper\Facades\SeoHelper;
use Kern\Theme\Facades\Theme;

class PublicController extends BaseController
{
    public function getGalleries()
    {
        $galleries = GalleryModel::query()
            ->wherePublished()
            ->with(['slugable', 'user'])
            ->orderBy('order')->latest()
            ->get();

        SeoHelper::setTitle(__('Galleries'));

        Theme::breadcrumb()->add(__('Galleries'), Gallery::getGalleriesPageUrl());

        return Theme::scope('galleries', compact('galleries'), 'plugins/gallery::themes.galleries')
            ->render();
    }
}
