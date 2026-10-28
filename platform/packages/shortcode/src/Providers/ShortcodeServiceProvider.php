<?php

namespace Kern\Shortcode\Providers;

use Illuminate\Support\Arr;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\Shortcode\Compilers\ShortcodeCompiler;
use Kern\Shortcode\Shortcode;
use Kern\Shortcode\View\Factory;

class ShortcodeServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function register(): void
    {
        $this->app->singleton('shortcode.compiler', ShortcodeCompiler::class);

        $this->app->singleton('shortcode', function ($app) {
            return new Shortcode($app['shortcode.compiler']);
        });

        $this->app->singleton('view', function ($app) {
            // Next we need to grab the engine resolver instance that will be used by the
            // environment. The resolver will be used by an environment to get each of
            // the various engine implementations such as plain PHP or Blade engine.
            $resolver = $app['view.engine.resolver'];
            $finder = $app['view.finder'];
            $env = new Factory($resolver, $finder, $app['events'], $app['shortcode.compiler']);
            // We will also set the container instance on this view environment since the
            // view composers may be classes registered in the container, which allows
            // for great testable, flexible composers for the application developer.
            $env->setContainer($app);
            $env->share('app', $app);

            return $env;
        });

        $this->app['blade.compiler']->directive('shortcode', function ($expression) {
            return do_shortcode($expression);
        });

        $this->app->instance('shortcode.modal.rendered', false);
    }

    public function boot(): void
    {
        $this
            ->setNamespace('packages/shortcode')
            ->loadRoutes()
            ->loadHelpers()
            ->loadAndPublishTranslations()
            ->loadAndPublishViews()
            ->publishAssets();

        $this->app->booted(function (): void {
            add_filter(BASE_FILTER_FORM_EDITOR_BUTTONS, function (?string $buttons, array $attributes, string $id) {
                if (! $this->hasWithShortcode($attributes)) {
                    return $buttons;
                }

                $buttons = (string) $buttons;

                $buttons .= view('packages/shortcode::partials.shortcode-button', compact('id'))->render();

                return $buttons;
            }, 120, 3);

            add_filter(BASE_FILTER_FORM_EDITOR_BUTTONS_HEADER, function (?string $header, array $attributes) {
                if (! $this->hasWithShortcode($attributes)) {
                    return $header;
                }

                return $header;
            }, 120, 2);

            add_filter(BASE_FILTER_FORM_EDITOR_BUTTONS_FOOTER, function (?string $footer, array $attributes) {
                if (! $this->hasWithShortcode($attributes)) {
                    return $footer;
                }

                $footer = (string) $footer;

                if (! $this->isShortcodeModalRendered()) {
                    $footer .= view('packages/shortcode::partials.shortcode-modal')->render();

                    $this->shortcodeModalRendered();
                }

                return $footer;
            }, 120, 2);
        });
    }

    protected function hasWithShortcode(array $attributes): bool
    {
        return (bool) Arr::get($attributes, 'with-short-code', false);
    }

    protected function isShortcodeModalRendered(): bool
    {
        return $this->app['shortcode.modal.rendered'] === true;
    }

    protected function shortcodeModalRendered(): void
    {
        $this->app->instance('shortcode.modal.rendered', true);
    }
}
