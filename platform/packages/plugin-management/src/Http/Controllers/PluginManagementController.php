<?php

namespace Kern\PluginManagement\Http\Controllers;

use Exception;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Inertia\Inertia;
use Inertia\Response;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Http\Controllers\BaseController;
use Kern\Base\Http\Responses\BaseHttpResponse;
use Kern\Base\Supports\Breadcrumb;
use Kern\PluginManagement\Enums\PluginFilterStatus;
use Kern\PluginManagement\Events\RenderingPluginListingPage;
use Kern\PluginManagement\Services\PluginService;

class PluginManagementController extends BaseController
{
    public function __construct(protected PluginService $pluginService) {}

    protected function breadcrumb(): Breadcrumb
    {
        return parent::breadcrumb()
            ->add(trans('packages/plugin-management::plugin.plugins'), route('plugins.index'))
            ->add(trans('packages/plugin-management::plugin.installed_plugins'), route('plugins.index'));
    }

    public function index(): View|Response
    {
        $this->pageTitle(trans('packages/plugin-management::plugin.installed_plugins'));

        RenderingPluginListingPage::dispatch();

        if (File::exists(plugin_path('.DS_Store'))) {
            File::delete(plugin_path('.DS_Store'));
        }

        $plugins = collect();

        if (! empty($pluginsPath = BaseHelper::scanFolder(plugin_path()))) {
            $installed = get_active_plugins();

            foreach ($pluginsPath as $path) {
                $pluginPath = plugin_path($path);

                if (File::exists($dsStore = "$pluginPath/.DS_Store")) {
                    File::delete($dsStore);
                }

                if (
                    ! File::isDirectory($pluginPath)
                    || ! File::exists($pluginJson = "$pluginPath/plugin.json")
                ) {
                    continue;
                }

                $manifest = BaseHelper::getFileData($pluginJson);

                if (! empty($manifest)) {
                    $manifest = [
                        ...$manifest,
                        'status' => in_array($path, $installed),
                        'path' => $path,
                        'image' => null,
                    ];

                    $screenshot = "vendor/core/plugins/$path/screenshot.png";

                    if (File::exists(public_path($screenshot))) {
                        $manifest['image'] = asset($screenshot).'?v='.File::lastModified(public_path($screenshot));
                    } elseif (File::exists($pluginPath.'/screenshot.png')) {
                        $manifest['image'] = 'data:image/png;base64,'.base64_encode(File::get($pluginPath.'/screenshot.png'));
                    }

                    $plugins->push((object) $manifest);
                }
            }

            $plugins = collect($plugins)->sortByDesc('status');
        }

        $filterStatuses = PluginFilterStatus::labels();

        return Inertia::render('System/Plugins', [
            'title' => trans('packages/plugin-management::plugin.installed_plugins'),
            'plugins' => $plugins->map(fn (object $plugin): array => [
                'id' => $plugin->path,
                'name' => $plugin->name ?? $plugin->path,
                'description' => strip_tags($plugin->description ?? ''),
                'version' => $plugin->version ?? '',
                'active' => (bool) $plugin->status,
                'image' => $plugin->image,
                'author' => config('packages.plugin-management.general.hide_plugin_author', false) ? null : ($plugin->author ?? null),
                'authorUrl' => isset($plugin->url) && filter_var($plugin->url, FILTER_VALIDATE_URL)
                    && in_array(parse_url($plugin->url, PHP_URL_SCHEME), ['http', 'https']) ? $plugin->url : null,
            ])->values()->all(),
            'permissions' => [
                'edit' => request()->user()->hasPermission('plugins.edit'),
                'remove' => request()->user()->hasPermission('plugins.remove'),
            ],
            'urls' => [
                'requirements' => route('plugins.check-requirement'),
                'status' => route('plugins.change.status'),
                'remove' => route('plugins.remove', '__PLUGIN__'),
            ],
        ]);
    }

    public function update(Request $request): BaseHttpResponse
    {
        $request->validate(['name' => ['required', 'string', 'regex:/\A[a-z0-9][a-z0-9_-]*\z/']]);
        $plugin = $request->string('name')->toString();

        if (! $this->pluginService->validatePlugin($plugin)) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage(trans('packages/plugin-management::plugin.invalid_plugin'));
        }

        try {
            $activatedPlugins = get_active_plugins();

            if ($status = (! in_array($plugin, $activatedPlugins))) {
                $result = $this->pluginService->activate($plugin);
            } else {
                $result = $this->pluginService->deactivate($plugin);
            }

            if ($result['error']) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($result['message']);
            }

            return $this
                ->httpResponse()
                ->setData(['status' => $status ? 'activated' : 'deactivated'])
                ->setMessage(trans('packages/plugin-management::plugin.update_plugin_status_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function destroy(string $plugin): BaseHttpResponse
    {
        try {
            $result = $this->pluginService->remove($plugin);

            if ($result['error']) {
                return $this
                    ->httpResponse()
                    ->setError()
                    ->setMessage($result['message']);
            }

            return $this
                ->httpResponse()
                ->setMessage(trans('packages/plugin-management::plugin.remove_plugin_success'));
        } catch (Exception $exception) {
            return $this
                ->httpResponse()
                ->setError()
                ->setMessage($exception->getMessage());
        }
    }

    public function checkRequirement(Request $request): BaseHttpResponse
    {
        $request->validate(['name' => ['required', 'string', 'regex:/\A[a-z0-9][a-z0-9_-]*\z/']]);
        $requiredPlugins = $this->pluginService->getDependencies($request->string('name')->toString());

        if ($requiredPlugins) {
            return $this->httpResponse()->setError()->setMessage(trans('packages/plugin-management::plugin.missing_required_plugins', ['plugins' => implode(', ', $requiredPlugins)]));
        }

        return $this->httpResponse();
    }
}
