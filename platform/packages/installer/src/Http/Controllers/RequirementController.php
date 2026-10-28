<?php

namespace Kern\Installer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\URL;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Installer\Supports\RequirementsChecker;

class RequirementController extends BaseController
{
    public function index(Request $request, RequirementsChecker $requirements): View|RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('installers.welcome');
        }

        $phpSupportInfo = $requirements->checkPhpVersion(get_minimum_php_version());
        $requirements = $requirements->check(config('packages.installer.installer.requirements'));

        return view('packages/installer::requirements', compact('requirements', 'phpSupportInfo'));
    }
}
