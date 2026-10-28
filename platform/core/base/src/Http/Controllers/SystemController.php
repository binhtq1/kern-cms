<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Services\CleanDatabaseService;
use Kern\Setting\Supports\AdminSettingData;
use Throwable;

class SystemController extends BaseSystemController
{
    public function getIndex(): View|Response
    {
        $this->pageTitle(trans('core/base::base.panel.system'));

        return Inertia::render('System/Index', ['title' => trans('core/base::base.panel.system'), 'sections' => app(AdminSettingData::class)->sections('system')]);
    }

    public function getMenuItemsCount(): BaseHttpResponse
    {
        $data = apply_filters(BASE_FILTER_MENU_ITEMS_COUNT, []);

        return $this
            ->httpResponse()
            ->setData($data);
    }

    public function getCleanup(
        Request $request,
        CleanDatabaseService $cleanDatabaseService
    ) {
        $this->pageTitle(trans('core/base::system.cleanup.title'));

        try {
            $tables = DB::connection()->getSchemaBuilder()->getTableListing();
        } catch (Throwable) {
            $tables = [];
        }

        $disabledTables = [
            'disabled' => $cleanDatabaseService->getIgnoreTables(),
            'checked' => [],
        ];

        if ($request->isMethod('POST')) {
            if (! config('core.base.general.enabled_cleanup_database', false)) {
                return $this
                    ->httpResponse()
                    ->setCode(401)
                    ->setError()
                    ->setMessage(strip_tags(trans('core/base::system.cleanup.not_enabled_yet')));
            }

            $request->validate(['tables' => 'array']);

            $cleanDatabaseService->execute($request->input('tables', []));

            return $this
                ->httpResponse()
                ->setMessage(trans('core/base::system.cleanup.success_message'));
        }

        return Inertia::render('System/Cleanup', [
            'title' => trans('core/base::system.cleanup.title'),
            'warning' => trans('core/base::system.cleanup.backup_alert'),
            'disabledMessage' => strip_tags(trans('core/base::system.cleanup.not_enabled_yet')),
            'enabled' => (bool) config('core.base.general.enabled_cleanup_database', false),
            'submit' => route('system.cleanup.process'),
            'tables' => array_map(fn (string $table): array => [
                'name' => $table,
                'count' => DB::table($table)->count(),
                'protected' => in_array(str($table)->afterLast('.')->toString(), $disabledTables['disabled'], true),
            ], $tables),
        ]);
    }
}
