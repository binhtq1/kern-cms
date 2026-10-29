<?php

namespace Kern\Gallery\Providers;

use Illuminate\Foundation\AliasLoader;
use Kern\Base\Facades\DashboardMenu;
use Kern\Base\Supports\DashboardMenuItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Gallery\Facades\Gallery as GalleryFacade;
use Kern\Gallery\Models\Gallery;
use Kern\Gallery\Models\GalleryMeta;
use Kern\Gallery\Repositories\Eloquent\GalleryMetaRepository;
use Kern\Gallery\Repositories\Eloquent\GalleryRepository;
use Kern\Gallery\Repositories\Interfaces\GalleryInterface;
use Kern\Gallery\Repositories\Interfaces\GalleryMetaInterface;
use Kern\LanguageAdvanced\Supports\LanguageAdvancedManager;
use Kern\SeoHelper\Facades\SeoHelper;
use Kern\Slug\Facades\SlugHelper;
use Kern\Theme\Events\ThemeRoutingBeforeEvent;
use Kern\Theme\Facades\SiteMapManager;

class GalleryServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->bind(GalleryInterface::class, function () {
            return new GalleryRepository(new Gallery);
        });

        $this->app->bind(GalleryMetaInterface::class, function () {
            return new GalleryMetaRepository(new GalleryMeta);
        });

        AliasLoader::getInstance()->alias('Gallery', GalleryFacade::class);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('plugins/gallery')
            ->loadHelpers()
            ->loadAndPublishConfigurations(['general', 'permissions'])
            ->loadRoutes()
            ->loadAndPublishViews()
            ->loadAndPublishTranslations()
            ->loadMigrations()
            ->publishAssets();

        $this->app->register(EventServiceProvider::class);

        $this->app['events']->listen(ThemeRoutingBeforeEvent::class, function (): void {
            SiteMapManager::registerKey(['galleries']);
        });

        SlugHelper::registering(function (): void {
            SlugHelper::registerModule(Gallery::class, fn () => trans('plugins/gallery::gallery.galleries'));
            SlugHelper::setPrefix(Gallery::class, 'galleries', true);
        });

        DashboardMenu::default()->beforeRetrieving(function (): void {
            DashboardMenu::make()
                ->registerItem(
                    DashboardMenuItem::make()
                        ->id('cms-plugins-gallery')
                        ->priority(5)
                        ->name('plugins/gallery::gallery.menu_name')
                        ->icon('ti ti-camera')
                        ->route('galleries.index')
                );
        });

        if (defined('LANGUAGE_MODULE_SCREEN_NAME') && defined('LANGUAGE_ADVANCED_MODULE_SCREEN_NAME')) {
            LanguageAdvancedManager::registerModule(Gallery::class, [
                'name',
                'description',
            ]);

            LanguageAdvancedManager::registerModule(GalleryMeta::class, [
                'images',
            ]);

            LanguageAdvancedManager::addTranslatableMetaBox('gallery_wrap');

            foreach (GalleryFacade::getSupportedModules() as $item) {
                $translatableColumns = array_merge(LanguageAdvancedManager::getTranslatableColumns($item), ['gallery']);

                LanguageAdvancedManager::registerModule($item, $translatableColumns);
            }
        }

        $this->app->booted(function (): void {
            SeoHelper::registerModule([Gallery::class]);

            $this->app->register(HookServiceProvider::class);
        });
    }
}
