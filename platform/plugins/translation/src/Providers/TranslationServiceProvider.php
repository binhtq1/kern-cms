<?php

namespace Kern\Translation\Providers;

use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\DataSynchronize\PanelSections\ExportPanelSection;
use Kern\DataSynchronize\PanelSections\ImportPanelSection;
use Kern\Translation\Console\AutoTranslateCoreCommand;
use Kern\Translation\Console\AutoTranslateThemeCommand;
use Kern\Translation\Console\DownloadLocaleCommand;
use Kern\Translation\Console\RemoveLocaleCommand;
use Kern\Translation\Console\RemoveUnusedTranslationsCommand;
use Kern\Translation\Console\UpdateThemeTranslationCommand;
use Kern\Translation\PanelSections\LocalizationPanelSection;

class TranslationServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/translation')
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadMigrations()
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->publishAssets();

        PanelSectionManager::beforeRendering(function (): void {
            PanelSectionManager::register(LocalizationPanelSection::class);
        });

        PanelSectionManager::setGroupId('data-synchronize')->beforeRendering(function (): void {
            PanelSectionManager::default()
                ->registerItem(
                    ExportPanelSection::class,
                    fn () => PanelSectionItem::make('export-theme-translations')
                        ->setTitle(trans('plugins/translation::translation.panel.theme-translations.title'))
                        ->withDescription(trans(
                            'plugins/translation::translation.export_description',
                            ['name' => trans('plugins/translation::translation.panel.theme-translations.title')]
                        ))
                        ->withPriority(999)
                        ->withPermission('theme-translations.export')
                        ->withRoute('tools.data-synchronize.export.theme-translations.index')
                )
                ->registerItem(
                    ExportPanelSection::class,
                    fn () => PanelSectionItem::make('other-translations')
                        ->setTitle(trans('plugins/translation::translation.panel.admin-translations.title'))
                        ->withDescription(trans(
                            'plugins/translation::translation.export_description',
                            ['name' => trans('plugins/translation::translation.panel.admin-translations.title')]
                        ))
                        ->withPriority(999)
                        ->withPermission('other-translations.export')
                        ->withRoute('tools.data-synchronize.export.other-translations.index')
                )
                ->registerItem(
                    ImportPanelSection::class,
                    fn () => PanelSectionItem::make('import-theme-translations')
                        ->setTitle(trans('plugins/translation::translation.panel.theme-translations.title'))
                        ->withDescription(trans(
                            'plugins/translation::translation.import_description',
                            ['name' => trans('plugins/translation::translation.panel.theme-translations.title')]
                        ))
                        ->withPriority(999)
                        ->withPermission('theme-translations.import')
                        ->withRoute('tools.data-synchronize.import.theme-translations.index')
                )
                ->registerItem(
                    ImportPanelSection::class,
                    fn () => PanelSectionItem::make('other-translations')
                        ->setTitle(trans('plugins/translation::translation.panel.admin-translations.title'))
                        ->withDescription(trans(
                            'plugins/translation::translation.import_description',
                            ['name' => trans('plugins/translation::translation.panel.admin-translations.title')]
                        ))
                        ->withPriority(999)
                        ->withPermission('other-translations.import')
                        ->withRoute('tools.data-synchronize.import.other-translations.index')
                );
        });

        if ($this->app->runningInConsole()) {
            $this->commands([
                UpdateThemeTranslationCommand::class,
                RemoveUnusedTranslationsCommand::class,
                DownloadLocaleCommand::class,
                RemoveLocaleCommand::class,
                AutoTranslateThemeCommand::class,
                AutoTranslateCoreCommand::class,
            ]);
        }
    }
}
