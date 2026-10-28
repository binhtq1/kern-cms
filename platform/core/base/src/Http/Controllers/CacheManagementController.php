<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Http\Requests\ClearCacheRequest;
use Kern\Base\Services\ClearCacheService;

class CacheManagementController extends BaseSystemController
{
    public function index(): View|Response
    {
        $this->pageTitle(trans('core/base::cache.cache_management'));

        $types = ['clear_cms_cache', 'refresh_compiled_views', 'clear_config_cache', 'clear_route_cache', 'clear_log'];

        return Inertia::render('System/Console', [
            'title' => trans('core/base::cache.cache_management'),
            'columns' => [['key' => 'name', 'label' => __('Name')], ['key' => 'description', 'label' => __('Description')]],
            'rows' => array_map(fn (string $type) => [
                'id' => $type,
                'values' => ['name' => trans('core/base::cache.commands.'.$type.'.title'), 'description' => trans('core/base::cache.commands.'.$type.'.description')],
                'actions' => [['label' => trans('core/base::cache.commands.'.$type.'.title'), 'url' => route('system.cache.clear'),
                    'method' => 'post', 'data' => ['type' => $type], 'confirm' => __('Run this maintenance operation?')]],
            ], $types),
        ]);
    }

    public function destroy(ClearCacheRequest $request, ClearCacheService $clearCacheService)
    {
        switch ($type = $request->input('type')) {
            case 'clear_cms_cache':
                $clearCacheService->clearFrameworkCache();
                $clearCacheService->clearGoogleFontsCache();
                $clearCacheService->clearPurifier();
                $clearCacheService->clearDebugbar();

                break;
            case 'refresh_compiled_views':
                $clearCacheService->clearCompiledViews();

                break;
            case 'clear_config_cache':
                $clearCacheService->clearConfig();

                break;
            case 'clear_route_cache':
                $clearCacheService->clearRoutesCache();

                break;
            case 'clear_log':
                $clearCacheService->clearLogs();

                break;
        }

        return $this
            ->httpResponse()
            ->setMessage(trans("core/base::cache.commands.$type.success_msg"));
    }
}
