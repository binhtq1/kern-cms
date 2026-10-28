<?php

namespace Kern\Shortcode\View;

use Illuminate\Events\Dispatcher;
use Illuminate\View\Engines\EngineResolver;
use Illuminate\View\Factory as IlluminateViewFactory;
use Illuminate\View\ViewFinderInterface;
use Kern\Shortcode\Compilers\ShortcodeCompiler;

class Factory extends IlluminateViewFactory
{
    public ShortcodeCompiler $shortcode;

    protected array $aliases = [];

    public function __construct(
        EngineResolver $engines,
        ViewFinderInterface $finder,
        Dispatcher $events,
        ShortcodeCompiler $shortcode
    ) {
        parent::__construct($engines, $finder, $events);
        $this->shortcode = $shortcode;
    }

    public function make($view, $data = [], $mergeData = []): View
    {
        if (isset($this->aliases[$view])) {
            $view = $this->aliases[$view];
        }

        $path = $this->finder->find($view);
        $data = array_merge($mergeData, $this->parseData($data));

        $this->callCreator($view = new View(
            $this,
            $this->getEngineFromPath($path),
            $view,
            $path,
            $data,
            $this->shortcode
        ));

        return $view;
    }
}
