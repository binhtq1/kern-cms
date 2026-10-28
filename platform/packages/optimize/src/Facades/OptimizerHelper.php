<?php

namespace Kern\Optimize\Facades;

use Illuminate\Support\Facades\Facade;
use Kern\Optimize\Supports\Optimizer;

/**
 * @method static bool isEnabled()
 * @method static \Kern\Optimize\Supports\Optimizer enable()
 * @method static \Kern\Optimize\Supports\Optimizer disable()
 *
 * @see Optimizer
 */
class OptimizerHelper extends Facade
{
    protected static function getFacadeAccessor(): string
    {
        return Optimizer::class;
    }
}
