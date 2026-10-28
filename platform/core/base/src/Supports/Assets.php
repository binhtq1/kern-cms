<?php

namespace Kern\Base\Supports;

use Illuminate\Config\Repository;
use Kern\Assets\Assets as BaseAssets;
use Kern\Assets\HtmlBuilder;
use Kern\Base\Facades\AdminHelper;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;

/**
 * @since 22/07/2015 11:23 PM
 */
class Assets extends BaseAssets
{
    protected bool $hasVueJs = false;

    public function __construct(Repository $config, HtmlBuilder $htmlBuilder)
    {
        parent::__construct($config, $htmlBuilder);

        $this->config = $config->get('core.base.assets');

        if (empty($this->config['version'])) {
            $this->config['version'] = get_cms_version();
        }

        $this->scripts = $this->config['scripts'];

        $this->styles = $this->config['styles'];
    }

    public function setConfig(array $config): void
    {
        $this->config = $config;
    }

    public function renderHeader($lastStyles = []): string
    {
        if ($this->isReactAdminRequest()) {
            return '';
        }

        do_action(BASE_ACTION_ENQUEUE_SCRIPTS);

        if (AdminHelper::isInAdmin(true) && BaseHelper::adminLanguageDirection() === 'rtl') {
            $this->config['resources']['styles']['core']['src']['local'] = '/vendor/core/core/base/css/core.rtl.css';
            $this->config['resources']['styles']['select2']['src']['local'][1] = '/vendor/core/core/base/css/libraries/select2.rtl.css';
        }

        return parent::renderHeader($lastStyles);
    }

    public function renderFooter(): string
    {
        if ($this->isReactAdminRequest()) {
            return '';
        }

        $bodyScripts = $this->getScripts(self::ASSETS_SCRIPT_POSITION_FOOTER);

        return view('assets::footer', compact('bodyScripts'))->render();
    }

    public function usingVueJS(): self
    {
        if ($this->isReactAdminRequest()) {
            return $this;
        }

        $this->addScripts(['vue', 'vue-app']);

        $this->hasVueJs = true;

        return $this;
    }

    public function disableVueJS(): self
    {
        $this->removeScripts(['vue', 'vue-app']);

        $this->hasVueJs = false;

        return $this;
    }

    public function hasVueJs(): bool
    {
        return $this->hasVueJs;
    }

    public function addScripts(array|string $assets): static
    {
        return $this->isReactAdminRequest() ? $this : parent::addScripts($assets);
    }

    public function getScripts(?string $location = null): array
    {
        return $this->isReactAdminRequest() ? [] : parent::getScripts($location);
    }

    public function getStyles(array $lastStyles = []): array
    {
        return $this->isReactAdminRequest() ? [] : parent::getStyles($lastStyles);
    }

    public function scriptToHtml(string $name): ?string
    {
        return $this->isReactAdminRequest() ? null : parent::scriptToHtml($name);
    }

    public function styleToHtml(string $name): ?string
    {
        return $this->isReactAdminRequest() ? null : parent::styleToHtml($name);
    }

    public function addStyles(array|string $assets): static
    {
        return $this->isReactAdminRequest() ? $this : parent::addStyles($assets);
    }

    public function addStylesDirectly(array|string $assets, array $attributes = []): static
    {
        return $this->isReactAdminRequest() ? $this : parent::addStylesDirectly($assets, $attributes);
    }

    public function addScriptsDirectly(array|string $assets, string $location = self::ASSETS_SCRIPT_POSITION_FOOTER, array $attributes = []): static
    {
        return $this->isReactAdminRequest() ? $this : parent::addScriptsDirectly($assets, $location, $attributes);
    }

    protected function isReactAdminRequest(): bool
    {
        foreach (request()->route()?->gatherMiddleware() ?? [] as $middleware) {
            if (is_string($middleware) && is_a(explode(':', $middleware, 2)[0], HandleAdminInertiaRequests::class, true)) {
                return true;
            }
        }

        return false;
    }
}
