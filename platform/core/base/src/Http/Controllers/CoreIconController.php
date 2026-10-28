<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Cache;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Icon\Facades\Icon;

class CoreIconController extends BaseController
{
    public function index(Request $request): BaseHttpResponse
    {
        $request->validate([
            'q' => ['nullable', 'string'],
            'per_page' => ['nullable', 'integer'],
            'page' => ['nullable', 'integer'],
        ]);

        $icons = Cache::remember(
            'core:icons:list',
            60 * 60 * 24 * 30,
            fn () => apply_filters('core_icons', Icon::all())
        );

        $currentPage = Paginator::resolveCurrentPage();
        $perPage = $request->integer('per_page', 100);
        $collection = collect($icons);

        $icons = $collection
            ->when($request->query('q'), function (Collection $collection, $search) {
                return $collection->filter(fn ($item) => str_contains($item['name'], $search));
            })
            ->slice(($currentPage - 1) * $perPage, $perPage)
            ->mapWithKeys(
                fn ($icon, $key) => [$icon['name'] => BaseHelper::hasIcon($key) ? Icon::render($key) : '<i class="'.$icon['name'].'"></i>']
            )
            ->all();

        return $this
            ->httpResponse()
            ->setData((new LengthAwarePaginator($icons, count($collection), $perPage))
                ->setPath(route('core-icons'))
                ->appends($request->except('page')));
    }
}
