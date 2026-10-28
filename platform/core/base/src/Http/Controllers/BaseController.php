<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Foundation\Auth\Access\AuthorizesRequests;
use Illuminate\Foundation\Bus\DispatchesJobs;
use Illuminate\Foundation\Validation\ValidatesRequests;
use Illuminate\Routing\Controller;
use Kern\Base\Http\Controllers\Concerns\HasBreadcrumb;
use Kern\Base\Http\Controllers\Concerns\HasHttpResponse;
use Kern\Base\Http\Controllers\Concerns\HasPageTitle;

class BaseController extends Controller
{
    use AuthorizesRequests;
    use DispatchesJobs;
    use HasBreadcrumb;
    use HasHttpResponse;
    use HasPageTitle;
    use ValidatesRequests;
}
