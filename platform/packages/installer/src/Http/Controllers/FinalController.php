<?php

namespace Kern\Installer\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Kern\Base\Facades\BaseHelper;
use Kern\Installer\Events\InstallerFinished;

class FinalController
{
    public function index(
        Request $request
    ): View|RedirectResponse {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('installers.requirements.index');
        }

        File::delete(storage_path('installing'));

        BaseHelper::saveFileData(storage_path('installed'), Carbon::now()->toDateTimeString());

        event(new InstallerFinished);

        return view('packages/installer::final');
    }
}
