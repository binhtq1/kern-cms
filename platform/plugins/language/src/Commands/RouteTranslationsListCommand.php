<?php

namespace Kern\Language\Commands;

use Illuminate\Contracts\Console\Kernel;
use Illuminate\Contracts\Console\PromptsForMissingInput;
use Illuminate\Foundation\Console\RouteListCommand;
use Illuminate\Routing\Router;
use Kern\Language\LanguageManager;
use Kern\Language\Traits\TranslatedRouteCommandContext;
use Symfony\Component\Console\Input\InputArgument;

class RouteTranslationsListCommand extends RouteListCommand implements PromptsForMissingInput
{
    use TranslatedRouteCommandContext;

    protected $description = 'List all registered routes for specific locales';

    public function __construct(Router $router)
    {
        parent::__construct($router);

        $this->setName('route:trans:list');
    }

    public function handle(): int
    {
        $locale = $this->argument('locale');

        if (! $this->isSupportedLocale($locale)) {
            $this->components->error("Unsupported locale: '$locale'.");

            return self::FAILURE;
        }

        $this->loadFreshApplicationRoutes($locale);

        parent::handle();

        return self::SUCCESS;
    }

    protected function loadFreshApplicationRoutes(string $locale): void
    {
        $app = require $this->getBootstrapPath().'/app.php';

        $key = LanguageManager::ENV_ROUTE_KEY;

        if (function_exists('putenv')) {
            putenv("{$key}={$locale}");
        }

        $app->make(Kernel::class)->bootstrap();

        if (function_exists('putenv')) {
            putenv("{$key}=");
        }

        $this->router = $app['router'];
    }

    protected function configure(): void
    {
        $this->addArgument('locale', InputArgument::REQUIRED, 'The locale to list routes for.');
    }
}
