<?php

namespace Kern\Base\Supports\Builders;

use Closure;
use Kern\Table\Actions\Action;

trait HasUrl
{
    protected Closure|string $url;

    protected bool $openUrlInNewTab = false;

    /**
     * @param  Closure(static): string|string  $url
     */
    public function url(Closure|string $url): static
    {
        $this->url = $url;

        return $this;
    }

    public function hasUrl(): bool
    {
        return isset($this->url);
    }

    public function getUrl(): ?string
    {
        if (! $this->hasUrl()) {
            return null;
        }

        return $this->url instanceof Closure ? call_user_func($this->url, $this) : $this->url;
    }

    public function openUrlInNewTable(bool $openUrlInNewTab = true): static
    {
        $this->openUrlInNewTab = $openUrlInNewTab;

        return $this;
    }

    public function shouldOpenUrlInNewTable(): bool
    {
        return $this->openUrlInNewTab;
    }

    public function route(string $route, array $parameters = [], bool $absolute = true): static
    {
        $this
            ->url(fn (Action $action) => route($route, array_merge($parameters, [$action->getItem()->getKey()]), $absolute))
            ->permission($route);

        return $this;
    }
}
