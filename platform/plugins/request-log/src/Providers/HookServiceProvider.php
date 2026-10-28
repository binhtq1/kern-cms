<?php

namespace Kern\RequestLog\Providers;

use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Auth;
use Illuminate\Validation\Rule;
use Kern\Base\Forms\FieldOptions\AlertFieldOption;
use Kern\Base\Forms\FieldOptions\SelectFieldOption;
use Kern\Base\Forms\Fields\AlertField;
use Kern\Base\Forms\Fields\SelectField;
use Kern\Base\Supports\ServiceProvider;
use Kern\Dashboard\Events\RenderingDashboardWidgets;
use Kern\Dashboard\Supports\DashboardWidgetInstance;
use Kern\RequestLog\Events\RequestHandlerEvent;
use Kern\Setting\Enums\DataRetentionPeriod;
use Kern\Setting\Forms\GeneralSettingForm;
use Kern\Setting\Http\Requests\GeneralSettingRequest;

class HookServiceProvider extends ServiceProvider
{
    public function boot(): void
    {
        add_action(BASE_ACTION_SITE_ERROR, [$this, 'handleSiteError'], 125);

        $this->app['events']->listen(RenderingDashboardWidgets::class, function (): void {
            add_filter(DASHBOARD_FILTER_ADMIN_LIST, [$this, 'registerDashboardWidgets'], 125, 2);
        });

        GeneralSettingForm::extend(callback: function (GeneralSettingForm $form): void {
            $form
                ->add(
                    'request_log_data_retention_period',
                    SelectField::class,
                    SelectFieldOption::make()
                        ->label(trans('plugins/request-log::request-log.clear_old_data'))
                        ->helperText(trans('plugins/request-log::request-log.clear_old_data_helper'))
                        ->choices(DataRetentionPeriod::labels())
                        ->selected(setting('request_log_data_retention_period', DataRetentionPeriod::ONE_MONTH))
                )
                ->when(! setting('cronjob_last_run_at') && ! $form->has('cronjob_warning'), function (GeneralSettingForm $form): void {
                    $form->add(
                        'cronjob_warning',
                        AlertField::class,
                        AlertFieldOption::make()
                            ->type('warning')
                            ->content(trans('plugins/audit-log::history.cronjob_warning', [
                                'link' => route('system.cronjob'),
                            ])),
                    );
                });
        });

        add_filter('core_request_rules', function (array $rules, $request) {
            if (! $request instanceof GeneralSettingRequest) {
                return $rules;
            }

            return [
                ...$rules,
                'request_log_data_retention_period' => ['required', 'string', Rule::in(DataRetentionPeriod::values())],
            ];
        }, 999, 2);
    }

    public function handleSiteError(int $code): void
    {
        event(new RequestHandlerEvent($code));
    }

    public function registerDashboardWidgets(array $widgets, Collection $widgetSettings): array
    {
        if (! Auth::guard()->user()->hasPermission('request-log.index')) {
            return $widgets;
        }

        return (new DashboardWidgetInstance)
            ->setPermission('request-log.index')
            ->setKey('widget_request_errors')
            ->setTitle(trans('plugins/request-log::request-log.widget_request_errors'))
            ->setRoute(route('request-log.widget.request-errors'))
            ->setColumn('col-md-6 col-sm-6')
            ->init($widgets, $widgetSettings);
    }
}
