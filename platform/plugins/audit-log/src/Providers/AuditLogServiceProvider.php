<?php

namespace Kern\AuditLog\Providers;

use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Database\Console\PruneCommand;
use Kern\AuditLog\Models\AuditHistory;
use Kern\AuditLog\Repositories\Eloquent\AuditLogRepository;
use Kern\AuditLog\Repositories\Interfaces\AuditLogInterface;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\PanelSections\System\SystemPanelSection;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;

/**
 * @since 02/07/2016 09:05 AM
 */
class AuditLogServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(AuditLogInterface::class, function () {
            return new AuditLogRepository(new AuditHistory);
        });
    }

    public function boot(): void
    {
        $this->app->register(EventServiceProvider::class);
        $this->app->register(CommandServiceProvider::class);

        $this
            ->setNamespace('plugins/audit-log')
            ->loadHelpers()
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadAndPublishConfigurations(['permissions'])
            ->loadMigrations()
            ->publishAssets();

        PanelSectionManager::group('system')->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SystemPanelSection::class,
                fn () => PanelSectionItem::make('audit-logs')
                    ->setTitle(trans('plugins/audit-log::history.name'))
                    ->withDescription(trans('plugins/audit-log::history.description'))
                    ->withIcon('ti ti-note')
                    ->withPriority(10)
                    ->withRoute('audit-log.index')
            );
        });

        $this->app->booted(function (): void {
            $this->app->register(HookServiceProvider::class);
        });

        if ($this->app->runningInConsole()) {
            $this->app->afterResolving(Schedule::class, function (Schedule $schedule): void {
                $schedule
                    ->command(PruneCommand::class, ['--model' => AuditHistory::class])
                    ->dailyAt('00:30');
            });
        }
    }
}
