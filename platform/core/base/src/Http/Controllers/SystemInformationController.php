<?php

namespace Kern\Base\Http\Controllers;

use Illuminate\Support\Arr;
use Inertia\Inertia;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Supports\Helper;
use Kern\Base\Supports\SystemManagement;

class SystemInformationController extends BaseSystemController
{
    public function index()
    {
        $this->pageTitle(trans('core/base::system.info.title'));

        $composerArray = SystemManagement::getComposerArray();
        $packages = SystemManagement::getPackagesAndDependencies($composerArray['require']);

        $systemEnv = SystemManagement::getSystemEnv();
        $serverEnv = SystemManagement::getServerEnv();

        $requiredPhpVersion = Arr::get($composerArray, 'require.php', get_minimum_php_version());
        $requiredPhpVersion = str_replace('^', '', $requiredPhpVersion);
        $requiredPhpVersion = str_replace('~', '', $requiredPhpVersion);

        $matchPHPRequirement = version_compare(phpversion(), $requiredPhpVersion, '>=') > 0;

        $systemFields = [
            'cms_version' => get_cms_version(),
            'framework_version' => $systemEnv['version'],
            'timezone' => $systemEnv['timezone'],
            'server_ip' => Helper::getIpFromThirdParty(timeout: 3),
            'debug_mode_off' => ! $systemEnv['debug_mode'],
            'storage_dir_writable' => $systemEnv['storage_dir_writable'],
            'cache_dir_writable' => $systemEnv['cache_dir_writable'],
            'app_size' => null,
        ];
        $serverFields = [
            'php_version' => $serverEnv['version'],
            'memory_limit' => $serverEnv['memory_limit'],
            'max_execution_time' => $serverEnv['max_execution_time'],
            'server_software' => $serverEnv['server_software'],
            'server_os' => $serverEnv['server_os'],
            'database' => $serverEnv['database_connection_name'],
            'ssl_installed' => $serverEnv['ssl_installed'],
            'cache_driver' => $serverEnv['cache_driver'],
            'queue_connection' => $serverEnv['queue_connection'],
            'session_driver' => $serverEnv['session_driver'],
            'allow_url_fopen_enabled' => (bool) $serverEnv['allow_url_fopen_enabled'],
        ];
        foreach (['mbstring', 'openssl', 'pdo', 'curl', 'exif', 'tokenizer', 'imagick_or_gd'] as $extension) {
            $serverFields[$extension.'_ext'] = $serverEnv[$extension];
        }
        $serverFields['file_info_ext'] = $serverEnv['fileinfo'];
        $serverFields['zip'] = $serverEnv['zip'];
        $serverFields['iconv'] = $serverEnv['iconv'];
        $toRows = fn (array $fields): array => collect($fields)->map(fn ($value, string $key): array => [
            'key' => $key, 'label' => trans('core/base::system.'.$key), 'value' => $value,
        ])->values()->all();

        $props = [
            'title' => trans('core/base::system.info.title'),
            'application' => $toRows($systemFields),
            'server' => $toRows($serverFields),
            'requiredPhpVersion' => $requiredPhpVersion,
            'phpRequirementSatisfied' => $matchPHPRequirement,
            'packages' => array_map(fn (array $package): array => [
                'name' => $package['name'], 'version' => $package['version'],
                'dependencies' => is_array($package['dependencies'])
                    ? collect($package['dependencies'])->map(fn ($version, $name): string => $name.': '.$version)->implode(', ')
                    : $package['dependencies'],
            ], $packages),
            'urls' => [
                'additionalData' => route('system.info.get-addition-data'),
                'disableDebug' => app()->hasDebugModeEnabled() ? route('system.debug-mode.turn-off') : null,
            ],
            'labels' => collect(['report_description', 'get_system_report', 'copy_report', 'system_environment', 'server_environment', 'installed_packages', 'package_name', 'version', 'dependency_name'])
                ->mapWithKeys(fn (string $key): array => [$key => trans('core/base::system.'.$key)])->all(),
        ];

        return request()->expectsJson() && ! request()->header('X-Inertia')
            ? response()->json(['component' => 'System/Information', 'props' => $props])
            : Inertia::render('System/Information', $props);
    }

    public function getAdditionData()
    {
        BaseHelper::maximumExecutionTimeAndMemoryLimit();

        $appSize = BaseHelper::humanFilesize(SystemManagement::getAppSize());

        return $this
            ->httpResponse()
            ->setData(compact('appSize'));
    }
}
