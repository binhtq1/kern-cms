<?php

namespace Kern\JsValidation\Support;

use Closure;

trait AccessProtectedTrait
{
    /**
     * Gets inaccessible property.
     *
     * @return Closure
     */
    protected function getProtected($instance, $property)
    {
        $closure = function ($property) {
            return $this->$property;
        };
        $callback = $closure->bindTo($instance, $instance);

        return $callback($property);
    }

    /**
     * Calls inaccessible method.
     *
     * @param  object|Closure  $instance
     * @return mixed
     */
    protected function callProtected($instance, $method, $args = [])
    {
        if (! ($instance instanceof Closure)) {
            $instance = $this->createProtectedCaller($instance);
        }

        return call_user_func($instance, $method, $args);
    }

    /**
     * Create closure to call inaccessible method.
     *
     * @return Closure
     */
    protected function createProtectedCaller($instance)
    {
        $closure = function ($method, $args) {
            $callable = [$this, $method];

            return call_user_func_array($callable, $args);
        };

        return $closure->bindTo($instance, $instance);
    }
}
