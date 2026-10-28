<?php

namespace Kern\Slug\Providers;

use Illuminate\Database\Eloquent\Model;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\MacroableModels;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\Models\BaseModel;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Page\Models\Page;
use Kern\PluginManagement\Events\ActivatedPluginEvent;
use Kern\Setting\PanelSections\SettingCommonPanelSection;
use Kern\Slug\Facades\SlugHelper as SlugHelperFacade;
use Kern\Slug\Models\Slug;
use Kern\Slug\Repositories\Eloquent\SlugRepository;
use Kern\Slug\Repositories\Interfaces\SlugInterface;
use Kern\Slug\SlugCompiler;
use Kern\Slug\SlugHelper;

class SlugServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    protected bool $defer = true;

    /** @var array<class-string, true> */
    protected array $registeredModels = [];

    public function register(): void
    {
        $this->app->bind(SlugInterface::class, function () {
            return new SlugRepository(new Slug);
        });

        $this->app->singleton(SlugHelper::class, function () {
            return new SlugHelper(new SlugCompiler);
        });
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/slug')
            ->loadAndPublishConfigurations(['general'])
            ->loadHelpers()
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->loadRoutes()
            ->loadMigrations()
            ->publishAssets();

        $this->app->register(EventServiceProvider::class);
        $this->app->register(CommandServiceProvider::class);

        PanelSectionManager::default()->beforeRendering(function (): void {
            PanelSectionManager::registerItem(
                SettingCommonPanelSection::class,
                fn () => PanelSectionItem::make('permalink')
                    ->setTitle(trans('packages/slug::slug.permalink_settings'))
                    ->withIcon('ti ti-link')
                    ->withDescription(trans('packages/slug::slug.permalink_settings_description'))
                    ->withPriority(90)
                    ->withRoute('slug.settings')
                    ->withPermission('settings.options')
            );
        });

        $this->app['events']->listen(ActivatedPluginEvent::class, fn () => $this->registerSupportedModels());

        $this->app->booted(function (): void {
            $this->app->register(FormServiceProvider::class);

            $this->registerSupportedModels();

            $this->app->register(HookServiceProvider::class);
        });
    }

    protected function registerSupportedModels(): void
    {
        $supportedModels = array_keys($this->app->make(SlugHelper::class)->supportedModels());

        foreach ($supportedModels as $item) {
            if (! class_exists($item) || isset($this->registeredModels[$item])) {
                continue;
            }

            /**
             * @var BaseModel $item
             */
            $item::resolveRelationUsing('slugable', function ($model) {
                return $model->morphOne(Slug::class, 'reference')->select([
                    'id',
                    'key',
                    'reference_type',
                    'reference_id',
                    'prefix',
                ]);
            });

            if (! method_exists($item, 'getSlugAttribute') && ! method_exists($item, 'slug') && ! property_exists($item, 'slug')) {
                MacroableModels::addMacro($item, 'getSlugAttribute', function () {
                    /**
                     * @var BaseModel $this
                     */
                    return $this->slugable ? $this->slugable->key : '';
                });
            }

            if (! method_exists($item, 'getSlugIdAttribute') && ! method_exists($item, 'slugId') && ! property_exists($item, 'slug_id')) {
                MacroableModels::addMacro($item, 'getSlugIdAttribute', function () {
                    /**
                     * @var BaseModel $this
                     */
                    return $this->slugable ? $this->slugable->getKey() : '';
                });
            }

            if (! method_exists($item, 'getUrlAttribute') && ! method_exists($item, 'url') && ! property_exists($item, 'url')) {
                MacroableModels::addMacro(
                    $item,
                    'getUrlAttribute',
                    function () {
                        /**
                         * @var BaseModel $model
                         */
                        $model = $this;

                        $slug = $model->slugable;

                        if (
                            ! $slug ||
                            ! $slug->key ||
                            ($model instanceof Page && BaseHelper::isHomepage($model->getKey()))
                        ) {
                            return BaseHelper::getHomepageUrl();
                        }

                        $prefix = SlugHelperFacade::getTranslator()->compile(
                            apply_filters(FILTER_SLUG_PREFIX, $slug->prefix),
                            $model
                        );

                        return apply_filters(
                            'slug_filter_url',
                            url(ltrim($prefix.'/'.$slug->key, '/')).SlugHelperFacade::getPublicSingleEndingURL()
                        );
                    }
                );
            }

            $this->registeredModels[$item] = true;

            $this->app['events']->listen('eloquent.deleted: '.$item, function (Model $model): void {
                Slug::query()
                    ->where('reference_type', $model::class)
                    ->where('reference_id', $model->getKey())
                    ->each(fn (Slug $slug) => $slug->delete());
            });
        }
    }

    public function provides(): array
    {
        return [
            SlugHelper::class,
        ];
    }
}
