<?php

namespace Kern\Widget\Factories;

use Exception;
use Illuminate\Contracts\Foundation\Application;
use Illuminate\Support\Str;
use Kern\Widget\AbstractWidget;
use Kern\Widget\Misc\InvalidWidgetClassException;
use Kern\Widget\Misc\ViewExpressionTrait;
use Kern\Widget\WidgetId;

abstract class AbstractWidgetFactory
{
    use ViewExpressionTrait;

    protected AbstractWidget $widget;

    protected array $widgetConfig;

    public string $widgetName;

    public array $widgetParams;

    public array $widgetFullParams;

    public function __construct(protected Application $app) {}

    public function __call(string $widgetName, array $params = [])
    {
        array_unshift($params, $widgetName);

        return call_user_func_array([$this, 'run'], $params);
    }

    protected function instantiateWidget(array $params = []): void
    {
        WidgetId::increment();

        $this->widgetName = $this->parseFullWidgetNameFromString(array_shift($params));
        $this->widgetFullParams = $params;
        $this->widgetConfig = (array) array_shift($params);
        $this->widgetParams = $params;

        $widgetClass = $this->widgetName;

        if (! class_exists($widgetClass, false)) {
            throw new Exception(sprintf('Widget "%s" does not exists.', $widgetClass));
        }

        if (! is_subclass_of($widgetClass, AbstractWidget::class)) {
            throw new InvalidWidgetClassException(sprintf('Class "%s" must extend "%s" class', $widgetClass, AbstractWidget::class));
        }

        $this->widget = new $widgetClass($this->widgetConfig);
    }

    protected function parseFullWidgetNameFromString(string $widgetName): string
    {
        return Str::studly(str_replace('.', '\\', $widgetName));
    }
}
