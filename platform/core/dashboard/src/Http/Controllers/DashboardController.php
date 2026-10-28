<?php

namespace Kern\Dashboard\Http\Controllers;

use Exception;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;
use Inertia\Inertia;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Dashboard\Events\RenderingDashboardWidgets;
use Kern\Dashboard\Models\DashboardWidget;
use Kern\Dashboard\Models\DashboardWidgetSetting;

class DashboardController extends BaseController
{
    public function getDashboard(Request $request)
    {
        $this->pageTitle(trans('core/dashboard::dashboard.title'));

        RenderingDashboardWidgets::dispatch();

        do_action(DASHBOARD_ACTION_REGISTER_SCRIPTS);

        $widgets = DashboardWidget::query()
            ->with([
                'settings' => function (HasMany $query) use ($request): void {
                    $query
                        ->where('user_id', $request->user()->getKey())
                        ->select(['status', 'order', 'settings', 'widget_id'])
                        ->orderBy('order');
                },
            ])
            ->select(['id', 'name'])
            ->get();

        $widgetData = apply_filters(DASHBOARD_FILTER_ADMIN_LIST, [], $widgets);

        $request->attributes->set('admin_dashboard_supported_notices', []);
        $notices = (string) apply_filters(DASHBOARD_FILTER_ADMIN_NOTIFICATIONS, null);
        $topBlocks = (string) apply_filters(DASHBOARD_FILTER_TOP_BLOCKS, null);
        $supportedNotices = $request->attributes->get('admin_dashboard_supported_notices', []);
        $supportsRegions = $notices === implode('', $supportedNotices) && trim($topBlocks) === '';
        if ($supportsRegions && collect($widgetData)->every(fn ($widget) => isset($widget['schema']))) {
            return Inertia::render('Dashboard/Index', [
                'notices' => array_map(fn (string $html): array => ['html' => BaseHelper::clean(str_contains($html, 'resume-setup-wizard') && Route::has('get-started.index') ? str_replace('href="#"', 'href="'.e(route('get-started.index')).'"', $html) : $html)], $supportedNotices),
                'widgets' => collect($widgetData)->where('type', '!=', 'widget')->sortBy('priority')->merge(collect($widgetData)->where('type', 'widget')->sortKeys())->pluck('schema')->values()->all(),
                'updater' => config('core.base.general.enable_system_updater') && $request->user()->isSuperUser() && Route::has('system.check-update') && Route::has('system.updater') ? ['check' => route('system.check-update'), 'url' => route('system.updater')] : null,
                'urls' => ['order' => route('dashboard.update_widget_order'), 'visibility' => route('dashboard.hide_widgets'), 'setting' => route('dashboard.edit_widget_setting_item')],
                'setup' => ! setting('is_completed_get_started') && Route::has('get-started.index') ? route('get-started.index') : null,
            ]);
        }

        return Inertia::render('System/Compatibility', [
            'title' => 'Dashboard integration requires an update',
            'message' => 'A dashboard widget or content region does not provide a React schema.',
        ])->toResponse($request)->setStatusCode(503);
    }

    public function postEditWidgetSettingItem(Request $request)
    {
        try {
            $widget = DashboardWidget::query()->where([
                'name' => $request->input('name'),
            ])->first();

            if (! $widget) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage(trans('core/dashboard::dashboard.widget_not_exists'));
            }

            $widgetSetting = DashboardWidgetSetting::query()->firstOrCreate([
                'widget_id' => $widget->getKey(),
                'user_id' => $request->user()->getKey(),
            ]);

            $widgetSetting->settings = array_merge((array) $widgetSetting->settings, [
                $request->input('setting_name') => $request->input('setting_value'),
            ]);

            $widgetSetting->save();
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }

        return $this->httpResponse();
    }

    public function postUpdateWidgetOrder(Request $request)
    {
        $request->validate(['items' => ['required', 'array'], 'items.*' => ['required', 'string', 'max:120']]);

        foreach ($request->input('items', []) as $key => $item) {
            $widget = DashboardWidget::query()->firstOrCreate([
                'name' => $item,
            ]);

            $widgetSetting = DashboardWidgetSetting::query()->firstOrCreate([
                'widget_id' => $widget->getKey(),
                'user_id' => $request->user()->getKey(),
            ]);

            $widgetSetting->order = $key;
            $widgetSetting->save();
        }

        return $this
            ->httpResponse()
            ->setMessage(trans('core/dashboard::dashboard.update_position_success'));
    }

    public function getHideWidget(Request $request)
    {
        $widget = DashboardWidget::query()->where([
            'name' => $request->input('name'),
        ], ['id'])->first();

        if (! empty($widget)) {
            $widgetSetting = DashboardWidgetSetting::query()->firstOrCreate([
                'widget_id' => $widget->getKey(),
                'user_id' => $request->user()->getKey(),
            ]);

            $maxOrder = DashboardWidgetSetting::query()->max('order');

            $maxOrder = $maxOrder < 125 ? $maxOrder : 10;

            $widgetSetting->status = 0;
            $widgetSetting->order = $maxOrder + 1;
            $widgetSetting->save();
        }

        return $this
            ->httpResponse()
            ->setMessage(trans('core/dashboard::dashboard.hide_success'));
    }

    public function postHideWidgets(Request $request)
    {
        $widgets = DashboardWidget::query()->get();

        foreach ($widgets as $widget) {
            $widgetSetting = DashboardWidgetSetting::query()->firstOrCreate([
                'widget_id' => $widget->getKey(),
                'user_id' => $request->user()->getKey(),
            ]);

            if (
                $request->has('widgets.'.$widget->name) &&
                $request->input('widgets.'.$widget->name) == 1
            ) {
                $widgetSetting->status = 1;
            } else {
                $maxOrder = DashboardWidgetSetting::query()->max('order');

                $maxOrder = $maxOrder < 125 ? $maxOrder : 10;

                $widgetSetting->status = 0;
                $widgetSetting->order = $maxOrder + 1;
            }

            $widgetSetting->save();
        }

        return $this
            ->httpResponse()
            ->setNextRoute('dashboard.index')
            ->setMessage(trans('core/dashboard::dashboard.hide_success'));
    }
}
