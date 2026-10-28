<?php

namespace Kern\JsValidation\Providers;

use Illuminate\Contracts\Http\Kernel;
use Kern\Base\Supports\ServiceProvider;
use Kern\Base\Traits\LoadAndPublishDataTrait;
use Kern\JsValidation\Javascript\ValidatorHandler;
use Kern\JsValidation\JsValidatorFactory;
use Kern\JsValidation\RemoteValidationMiddleware;

class JsValidationServiceProvider extends ServiceProvider
{
    use LoadAndPublishDataTrait;

    public function boot(): void
    {
        $this
            ->setNamespace('core/js-validation')
            ->loadAndPublishConfigurations(['js-validation'])
            ->loadAndPublishViews()
            ->publishAssets();

        $this->bootstrapValidator();

        if ($this->app['config']->get('core.js-validation.js-validation.disable_remote_validation') === false) {
            $this->app[Kernel::class]->pushMiddleware(RemoteValidationMiddleware::class);
        }
    }

    protected function bootstrapValidator(): void
    {
        $callback = function () {
            return true;
        };

        $this->app['validator']->extend(ValidatorHandler::JS_VALIDATION_DISABLE, $callback);
    }

    public function register(): void
    {
        $this->app->singleton('js-validator', function ($app) {
            $config = $app['config']->get('core.js-validation.js-validation');

            return new JsValidatorFactory($app, $config);
        });
    }
}
