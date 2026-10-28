<?php

namespace Kern\Base\Http\Controllers;

use Carbon\Carbon;
use Illuminate\Contracts\View\View;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Facades\BaseHelper;
use Kern\Setting\Facades\Setting;
use Symfony\Component\Process\PhpExecutableFinder;

class CronjobSettingController extends BaseSystemController
{
    public function index(): View|Response
    {
        $this->pageTitle(trans('core/setting::setting.cronjob.name'));

        $command = sprintf(
            '* * * * * %s %s/artisan schedule:run >> /dev/null 2>&1',
            (new PhpExecutableFinder)->find(false),
            BaseHelper::hasDemoModeEnabled() ? 'path-to-your-project' : base_path()
        );

        $lastRunAt = Setting::get('cronjob_last_run_at');

        if ($lastRunAt) {
            $lastRunAt = Carbon::parse($lastRunAt);
        }

        $status = ! $lastRunAt ? 'unconfigured' : (Carbon::now()->diffInMinutes($lastRunAt, absolute: true) <= 10 ? 'healthy' : 'stale');

        return Inertia::render('System/Cronjob', [
            'title' => trans('core/setting::setting.cronjob.name'),
            'description' => trans('core/setting::setting.cronjob.description'),
            'command' => $command,
            'lastRunAt' => $lastRunAt?->toIso8601String(),
            'status' => $status,
            'statusMessage' => trans('core/setting::setting.cronjob.'.match ($status) {
                'healthy' => 'is_working', 'stale' => 'is_not_working', default => 'is_not_ready',
            }),
            'setupTitle' => trans('core/setting::setting.cronjob.setup.name'),
            'steps' => collect(['connect_to_server', 'open_crontab', 'add_cronjob', 'done'])
                ->map(fn (string $step): string => trans('core/setting::setting.cronjob.setup.'.$step))->all(),
        ]);
    }
}
