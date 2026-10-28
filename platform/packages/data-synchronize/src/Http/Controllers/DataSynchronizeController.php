<?php

namespace Kern\DataSynchronize\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Supports\AdminRenderer;
use Kern\Base\Supports\Breadcrumb;
use Kern\Setting\Supports\AdminSettingData;
use Symfony\Component\HttpKernel\Exception\HttpException;

class DataSynchronizeController extends BaseController
{
    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('core/base::layouts.tools'));
    }

    public function index(Request $request): Response|View
    {
        $this->pageTitle(trans('packages/data-synchronize::data-synchronize.tools.export_import_data'));

        $sections = collect(app(AdminSettingData::class)->sections('data-synchronize'))
            ->filter(fn (array $section): bool => str_starts_with($section['id'], 'data-synchronize-'))
            ->map(function (array $section) use ($request): array {
                $section['items'] = array_map(function (array $item) use ($request): array {
                    $destination = Request::create(str_starts_with($item['url'], '/') ? $request->getSchemeAndHttpHost().$item['url'] : $item['url']);
                    $item['inertia'] = false;

                    if ($destination->getSchemeAndHttpHost() === $request->getSchemeAndHttpHost()) {
                        try {
                            $route = Route::getRoutes()->match($destination);
                            $item['inertia'] = app(AdminRenderer::class)->isInertiaRoute($route->getName());
                        } catch (HttpException) {
                            $item['inertia'] = false;
                        }
                    }

                    return $item;
                }, $section['items']);

                return $section;
            })
            ->values()->all();

        return Inertia::render('Operations/Index', ['title' => trans('packages/data-synchronize::data-synchronize.tools.export_import_data'), 'sections' => $sections]);
    }
}
