<?php

namespace Kern\Base\Supports;

use Illuminate\Support\Facades\Route;
use Kern\ACL\Forms\PasswordForm;
use Kern\ACL\Forms\PreferenceForm;
use Kern\ACL\Forms\ProfileForm;
use Kern\ACL\Forms\RoleForm;
use Kern\ACL\Forms\UserForm;
use Kern\ACL\Models\Role;
use Kern\ACL\Models\User;
use Kern\ACL\Supports\AdminAuthData;
use Kern\Base\Http\Middleware\HandleAdminInertiaRequests;
use Kern\CustomField\Models\FieldItem;
use Kern\CustomField\Support\AdminFieldGroupData;
use Kern\DataSynchronize\Http\Controllers\ImportController;
use Kern\Menu\Supports\AdminMenuData;
use Kern\Page\Forms\PageForm;
use Kern\Page\Models\Page;
use Kern\Page\Supports\AdminPageData;
use Kern\Setting\Supports\AdminSettingData;
use Kern\Theme\Supports\AdminThemeData;
use Kern\Translation\Supports\AdminTranslationData;
use Kern\Widget\Supports\AdminWidgetData;

class AdminRenderer
{
    public function routeModule(?string $routeName = null): ?string
    {
        $routeName ??= request()->route()?->getName();
        $routes = config('core.base.admin.route_modules', []);
        uksort($routes, fn (string $left, string $right): int => strlen($right) <=> strlen($left));
        foreach ($routes as $prefix => $module) {
            if ($routeName === $prefix || str_starts_with($routeName ?? '', $prefix.'.')) {
                return $module;
            }
        }

        return null;
    }

    public function isInertiaRoute(?string $routeName = null): bool
    {
        $route = Route::getRoutes()->getByName($routeName ?? request()->route()?->getName());
        $middleware = $route?->gatherMiddleware() ?? [];
        $inertiaMiddleware = HandleAdminInertiaRequests::class;

        foreach ($middleware as $entry) {
            if ($entry === $inertiaMiddleware || str_starts_with($entry, $inertiaMiddleware.':')) {
                return true;
            }
        }

        return false;
    }

    public function fallbackReason(string $module): ?string
    {
        if ($module === 'auth') {
            return app(AdminAuthData::class)->fallbackReason();
        }

        $supported = config("core.base.admin.supported_plugins.$module", config('core.base.admin.supported_plugins.pages', []));
        $unsupported = array_filter(array_diff(get_active_plugins(), $supported), fn (string $plugin): bool => ! app(AdminExtensionRegistry::class)->supports($plugin, $module));

        if ($unsupported && ! request()->routeIs('plugins.*')) {
            return 'Blade is required by extensions: '.implode(', ', $unsupported).'.';
        }

        if ($module === 'pages' && is_plugin_active('language') && (! is_plugin_active('language-advanced') || ! config('plugins.language-advanced.general.page_use_language_v2'))) {
            return 'Blade is required by legacy Page language mode.';
        }

        if ($module === 'pages' && is_plugin_active('custom-field')) {
            $unsupportedTypes = FieldItem::query()
                ->whereNotIn('type', ['text', 'number', 'email', 'password', 'textarea', 'select', 'radio', 'checkbox', 'date', 'time', 'color', 'image', 'file', 'wysiwyg', 'repeater'])
                ->distinct()->pluck('type')->all();
            if ($unsupportedTypes) {
                return 'Blade is required by custom field types: '.implode(', ', $unsupportedTypes).'.';
            }
        }

        if ($module === 'pages') {
            $model = request()->route('page');
            $form = $model instanceof Page ? PageForm::createFromModel($model) : PageForm::create();
            $supportedFields = [
                'name' => 'TextField', 'description' => 'TextareaField', 'content' => 'EditorField',
                'status' => 'SelectField', 'template' => 'SelectField', 'image' => 'MediaImageField',
                'banner_image' => 'MediaImageField', 'slug' => 'PermalinkField',
                'model' => 'InputType', 'form' => 'InputType',
            ];
            $form->prepareForSchema();
            if ($form->hasSchemaRenderingOutput()) {
                return 'Blade is required by Page rendering extensions.';
            }
            foreach ($form->getFields() as $name => $field) {
                if (($supportedFields[$name] ?? null) !== class_basename($field)) {
                    return 'Blade is required by Page form field: '.$name.'.';
                }
            }

            $unsupportedBoxes = array_diff(array_keys($form->getMetaBoxes()), ['seo_wrap', 'gallery_wrap', 'custom_fields', 'language_wrap', 'revisions']);
            if ($unsupportedBoxes) {
                return 'Blade is required by Page meta box: '.reset($unsupportedBoxes).'.';
            }

            foreach (app(AdminPageData::class)->shortcodes() as $shortcode) {
                if (! $shortcode['supported']) {
                    return 'Blade is required by shortcode configuration: '.$shortcode['key'].'.';
                }
            }
        }

        if (in_array($module, ['users', 'roles'], true)) {
            $model = request()->route($module === 'users' ? 'user' : 'role');
            if ($module === 'roles') {
                $forms = [[$model instanceof Role ? RoleForm::createFromModel($model) : RoleForm::create(), [
                    'name' => 'TextField', 'description' => 'TextareaField', 'is_default' => 'OnOffField',
                ]]];
            } elseif ($model instanceof User) {
                $forms = [
                    [ProfileForm::createFromModel($model), ['first_name' => 'TextField', 'last_name' => 'TextField', 'username' => 'TextField', 'email' => 'TextField']],
                    [PasswordForm::createFromModel($model), ['old_password' => 'InputType', 'password' => 'InputType', 'password_confirmation' => 'InputType']],
                    [PreferenceForm::createFromModel($model), ['locale' => 'SelectField', 'locale_direction' => 'RadioField', 'theme_mode' => 'RadioField']],
                ];
            } else {
                $forms = [[UserForm::create(), ['first_name' => 'TextField', 'last_name' => 'TextField', 'username' => 'TextField', 'email' => 'TextField', 'password' => 'InputType', 'password_confirmation' => 'InputType', 'role_id' => 'SelectField']]];
            }
            foreach ($forms as [$form, $fields]) {
                $form->prepareForSchema();
                if ($form->hasSchemaRenderingOutput()) {
                    return 'Blade is required by ACL rendering extensions.';
                }
                foreach ($form->getFields() as $name => $field) {
                    if (($fields[$name] ?? null) !== class_basename($field)) {
                        return 'Blade is required by ACL form field: '.$name.'.';
                    }
                }
                if (array_diff(array_keys($form->getMetaBoxes()), $module === 'roles' ? ['permissions'] : [])) {
                    return 'Blade is required by ACL form extensions.';
                }
            }
        }

        if (in_array($module, ['themes', 'theme-options', 'theme-assets'], true)) {
            return app(AdminThemeData::class)->fallbackReason($module);
        }
        if ($module === 'field-groups' && class_exists(AdminFieldGroupData::class)) {
            return app(AdminFieldGroupData::class)->fallbackReason();
        }
        if ($module === 'menus' && class_exists(AdminMenuData::class)) {
            return app(AdminMenuData::class)->fallbackReason();
        }
        if ($module === 'widgets' && class_exists(AdminWidgetData::class)) {
            return app(AdminWidgetData::class)->fallbackReason();
        }

        if ($module === 'operations' && request()->route()?->getActionMethod() === 'index' && ($controller = request()->route()->getController()) instanceof ImportController) {
            return $controller->reactFallbackReason();
        }

        if ($module === 'translations' && class_exists(AdminTranslationData::class)) {
            return app(AdminTranslationData::class)->fallbackReason();
        }

        if ($module === 'settings' && class_exists(AdminSettingData::class)) {
            return app(AdminSettingData::class)->fallbackReason();
        }

        if (class_exists(AdminContentResource::class) && array_key_exists($module, app(AdminContentResource::class)->modules())) {
            return app(AdminContentResource::class)->fallbackReason($module);
        }

        return null;
    }

    public function assetFallbackReason(): ?string
    {
        $hotFile = public_path('admin.hot');
        if (is_file($hotFile)) {
            $url = trim(file_get_contents($hotFile));
            if (filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true)) {
                return null;
            }

            return 'Blade is required because the admin development server URL is invalid.';
        }

        $root = public_path('build/admin');
        $manifestPath = $root.'/manifest.json';
        $manifest = is_file($manifestPath) ? json_decode(file_get_contents($manifestPath), true) : null;
        if (! is_array($manifest) || ! is_array($manifest['resources/js/admin.tsx'] ?? null) || empty($manifest['resources/js/admin.tsx']['isEntry'])) {
            return 'Blade is required because the admin build manifest is missing or invalid.';
        }

        foreach ($manifest as $chunk) {
            if (! is_array($chunk) || ! is_string($chunk['file'] ?? null)) {
                return 'Blade is required because the admin build manifest is invalid.';
            }
            $files = [$chunk['file']];
            foreach (['css', 'assets'] as $key) {
                if (isset($chunk[$key]) && ! is_array($chunk[$key])) {
                    return 'Blade is required because the admin build manifest is invalid.';
                }
                $files = [...$files, ...($chunk[$key] ?? [])];
            }
            foreach ($files as $file) {
                if (! is_string($file) || ! preg_match('/^[A-Za-z0-9_-][A-Za-z0-9_.-]*(?:\/[A-Za-z0-9_.-]+)*$/D', $file) || str_contains($file, '..') || ! is_file($root.'/'.$file)) {
                    return 'Blade is required because admin build assets are missing or invalid.';
                }
            }
            foreach (['imports', 'dynamicImports'] as $key) {
                if (isset($chunk[$key]) && ! is_array($chunk[$key])) {
                    return 'Blade is required because the admin build manifest is invalid.';
                }
                foreach ($chunk[$key] ?? [] as $import) {
                    if (! is_string($import) || ! isset($manifest[$import])) {
                        return 'Blade is required because admin build chunks are missing.';
                    }
                }
            }
        }

        return null;
    }

    public function usesInertia(string $module): bool
    {
        return true;
    }
}
