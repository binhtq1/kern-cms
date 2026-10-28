<?php

namespace Kern\Installer\Http\Controllers;

use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\URL;
use Illuminate\Validation\ValidationException;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Installer\Events\EnvironmentSaved;
use Kern\Installer\Http\Requests\SaveEnvironmentRequest;
use Kern\Installer\Supports\EnvironmentManager;
use Throwable;

class EnvironmentController extends BaseController
{
    public function index(Request $request): View|RedirectResponse
    {
        if (! URL::hasValidSignature($request)) {
            return redirect()->route('installers.requirements.index');
        }

        return view('packages/installer::environment');
    }

    public function store(
        SaveEnvironmentRequest $request,
        EnvironmentManager $environmentManager
    ): RedirectResponse {
        $driverName = $request->input('database_connection');
        $connectionName = 'database.connections.'.$driverName;
        $databaseName = $request->input('database_name');
        $databaseConfiguration = array_merge(config($connectionName), ['database' => $databaseName]);

        if ($driverName !== 'sqlite') {
            $databaseConfiguration = array_merge($databaseConfiguration, [
                'host' => $request->input('database_hostname'),
                'port' => $request->input('database_port'),
                'username' => $request->input('database_username'),
                'password' => $request->input('database_password'),
            ]);
        }

        config([
            'database.default' => $driverName,
            $connectionName => $databaseConfiguration,
        ]);

        DB::purge($driverName);

        try {
            DB::connection($driverName)->getPdo();
            if (Artisan::call('migrate', ['--force' => true]) !== 0) {
                throw new \RuntimeException(Artisan::output());
            }
            $results = $environmentManager->save($request);
        } catch (Throwable $exception) {
            report($exception);
            throw ValidationException::withMessages(['database_name' => $exception->getMessage()]);
        }

        event(new EnvironmentSaved($request));
        BaseHelper::saveFileData(storage_path(INSTALLING_SESSION_NAME), now()->toDateTimeString());

        return redirect()
            ->to(URL::temporarySignedRoute('installers.themes.index', now()->addMinutes(30)))
            ->with('install_message', $results);
    }
}
