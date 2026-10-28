<?php

namespace Kern\Page\Http\Controllers;

use Kern\Base\Http\Controllers\BaseController;
use Kern\Page\Models\Page;
use Kern\Page\Services\PageService;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Events\RenderingSingleEvent;
use Kern\Theme\Facades\Theme;

class PublicController extends BaseController
{
    public function getPage(string $slug, PageService $pageService)
    {
        $slug = SlugHelper::getSlug($slug, SlugHelper::getPrefix(Page::class));

        abort_unless($slug, 404);

        $data = $pageService->handleFrontRoutes($slug);

        if (isset($data['slug']) && $data['slug'] !== $slug->key) {
            return redirect()->to(url(SlugHelper::getPrefix(Page::class).'/'.$data['slug']));
        }

        event(new RenderingSingleEvent($slug));

        return Theme::scope($data['view'], $data['data'], $data['default_view'])->render();
    }
}
