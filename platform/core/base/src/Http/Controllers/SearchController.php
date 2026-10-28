<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Contracts\GlobalSearchableManager;
use Kern\Base\Contracts\GlobalSearchableResult;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\AdminRenderer;
use Symfony\Component\HttpKernel\Exception\HttpException;

class SearchController extends BaseController
{
    public function __invoke(GlobalSearchableManager $manager, Request $request): BaseHttpResponse|Response
    {
        $request->validate(['keyword' => ['nullable', 'string', 'max:1024']]);

        $results = $manager->search((string) $request->input('keyword'));

        if ($request->boolean('admin_json')) {
            return $this->httpResponse()->setData($results
                ->map(fn (GlobalSearchableResult $result): array => $this->resultData($result, $request))
                ->values()->all());
        }

        if ((! $request->expectsJson() || $request->header('X-Inertia'))) {
            return Inertia::render('System/Search', ['keyword' => (string) $request->input('keyword'), 'url' => route('core.global-search'), 'results' => $results->map(fn ($result) => ['title' => strip_tags($result->getTitle()), 'parents' => $result->getParents(), 'url' => $result->getUrl()])->all()]);
        }

        return $this
            ->httpResponse()
            ->setData($results->map(fn (GlobalSearchableResult $result): array => $this->resultData($result, $request))->values()->all());
    }

    /** @return array{title: string, description: string, parents: array, url: string, inertia: bool, shouldOpenNewTab: bool} */
    protected function resultData(GlobalSearchableResult $result, Request $request): array
    {
        $url = $result->getUrl();
        $inertia = false;
        $destination = Request::create(str_starts_with($url, '/') ? $request->getSchemeAndHttpHost().$url : $url);

        if ($destination->getSchemeAndHttpHost() === $request->getSchemeAndHttpHost()) {
            try {
                $route = Route::getRoutes()->match($destination);
                $inertia = app(AdminRenderer::class)->isInertiaRoute($route->getName());
            } catch (HttpException) {
                // Extension destinations without a registered route use normal navigation.
            }
        }

        return [
            'title' => strip_tags($result->getTitle()),
            'description' => strip_tags($result->getDescription()),
            'parents' => array_map(fn (string $parent): string => strip_tags($parent), $result->getParents()),
            'url' => $url,
            'inertia' => $inertia,
            'shouldOpenNewTab' => $result->shouldOpenNewTab(),
        ];
    }
}
