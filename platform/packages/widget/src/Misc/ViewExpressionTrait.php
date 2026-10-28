<?php

namespace Kern\Widget\Misc;

use Illuminate\Support\HtmlString;

trait ViewExpressionTrait
{
    protected function convertToViewExpression(string $html): HtmlString
    {
        return new HtmlString($html);
    }
}
