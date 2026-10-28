<?php

namespace Kern\Base\Http\Controllers\Concerns;

use Kern\Base\Http\Responses\BaseHttpResponse;

trait HasHttpResponse
{
    public function httpResponse(): BaseHttpResponse
    {
        return BaseHttpResponse::make();
    }
}
