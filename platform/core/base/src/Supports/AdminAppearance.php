<?php

namespace Kern\Base\Supports;

use Illuminate\Contracts\Auth\Authenticatable;
use Illuminate\Support\Arr;
use Illuminate\Support\Str;
use Kern\ACL\Contracts\HasPreferences;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\Html;
use Kern\Media\Facades\RvMedia;
use Kern\Setting\Facades\Setting;
use Throwable;

class AdminAppearance
{
    protected string $settingKey = 'admin_appearance';

    protected Authenticatable|HasPreferences $user;

    public function forUser(Authenticatable|HasPreferences $user): static
    {
        $this->user = $user;

        return $this;
    }

    public function forCurrentUser(?string $guard = null): static
    {
        $auth = auth($guard);

        if ($auth->check()) {
            $this->user = $auth->user();
        }

        return $this;
    }

    public function getLocale(): string
    {
        $generalLocale = config('core.base.general.locale', config('app.locale'));

        return $this->getUserSetting('locale', $generalLocale);
    }

    public function getLocaleDirection(): string
    {
        return $this->getUserSetting('locale_direction', setting('admin_locale_direction', 'ltr'));
    }

    public function getCurrentLayout(): string
    {
        return $this->getSetting('layout', array_key_first($this->getLayouts()));
    }

    public function isVerticalLayout(): bool
    {
        return $this->getCurrentLayout() === 'vertical';
    }

    public function isHorizontalLayout(): bool
    {
        return $this->getCurrentLayout() === 'horizontal';
    }

    public function showMenuItemIcon(): bool
    {
        return $this->getSetting('show_menu_item_icon', true);
    }

    public function getLayouts(): array
    {
        return [
            'vertical' => trans('core/setting::setting.admin_appearance.vertical'),
            'horizontal' => trans('core/setting::setting.admin_appearance.horizontal'),
        ];
    }

    public function getContainerWidth(): string
    {
        return $this->getSetting('container_width', array_key_first($this->getContainerWidths()));
    }

    public function getContainerWidths(): array
    {
        return [
            'container-xl' => trans('core/setting::setting.admin_appearance.container_width.default'),
            'container-3xl' => trans('core/setting::setting.admin_appearance.container_width.large'),
            'container-fluid' => trans('core/setting::setting.admin_appearance.container_width.full'),
        ];
    }

    public function getSetting(string $key, mixed $default = null)
    {
        return Setting::get($this->getSettingKey($key), $default);
    }

    public function getUserSetting(string $key, mixed $default = null)
    {
        $setting = null;

        if (isset($this->user) && $this->user->getAuthIdentifier() && $this->user instanceof HasPreferences) {
            $setting = $this->user->getMeta($key);
        }

        return $setting ?: $this->getSetting($key, $default);
    }

    public function getSettingKey(string $key): string
    {
        return "{$this->settingKey}_{$key}";
    }

    public function setSetting(string|array $key, mixed $value = null): void
    {
        $data = is_array($key) ? $key : [$key => $value];

        foreach ($data as $k => $v) {
            Setting::set("{$this->getSettingKey($k)}", $v);
        }

        Setting::save();
    }

    public function reactFallbackReason(string $module): ?string
    {
        if ($this->isHorizontalLayout()) {
            return 'Blade is required by the horizontal admin layout.';
        }
        foreach (['custom_css', 'custom_header_js', 'custom_body_js', 'custom_footer_js'] as $key) {
            if (filled($this->getSetting($key))) {
                return 'Blade is required by custom admin CSS or JavaScript.';
            }
        }
        foreach ($this->reactColorDefaults() as $key => $default) {
            $value = setting($key);
            if (filled($value) && (! is_string($value) || (! preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/iD', $value) && ! ($key === 'admin_heading_color' && $value === 'inherit')))) {
                return 'Blade is required by an unsupported admin color.';
            }
        }
        $font = setting('admin_primary_font');
        if (filled($font) && (! is_string($font) || ! in_array($font, BaseHelper::getFonts(), true))) {
            return 'Blade is required by an unsupported admin font.';
        }
        if (in_array($module, ['pages', 'posts', 'blocks', 'settings', 'theme-options', 'widgets'], true) && setting('rich_editor', config('core.base.general.editor.primary')) !== 'ckeditor') {
            return 'Blade is required by the selected legacy rich editor.';
        }

        return null;
    }

    /** @return array<string, string> */
    public function reactColorDefaults(): array
    {
        return [
            'admin_primary_color' => '#214f7d', 'admin_secondary_color' => '#e9edf2',
            'admin_heading_color' => 'inherit', 'admin_text_color' => '#20252b',
            'admin_link_color' => '#214f7d', 'admin_link_hover_color' => '#193d61',
        ];
    }

    /** @return array<string, mixed> */
    public function reactData(): array
    {
        $colors = [];
        foreach (['primary', 'secondary', 'heading', 'text', 'link', 'linkHover'] as $key) {
            $settingKey = $key === 'linkHover' ? 'admin_link_hover_color' : 'admin_'.$key.'_color';
            $value = setting($settingKey);
            if (is_string($value) && (preg_match('/^#(?:[a-f0-9]{3}|[a-f0-9]{6})$/iD', $value) || ($key === 'heading' && $value === 'inherit'))) {
                $colors[$key] = $value;
            }
        }
        $font = setting('admin_primary_font');
        $font = is_string($font) && in_array($font, BaseHelper::getFonts(), true) ? $font : null;
        $direction = BaseHelper::adminLanguageDirection();
        $container = $this->getSetting('container_width');
        $user = request()->user();
        $themeMode = $user instanceof HasPreferences ? $user->getMeta('theme_mode') : null;

        return [
            'logo' => setting('admin_logo') ? RvMedia::getImageUrl(setting('admin_logo')) : (config('core.base.general.logo') ? url(config('core.base.general.logo')) : null),
            'favicon' => setting('admin_favicon') ? RvMedia::getImageUrl(setting('admin_favicon')) : (config('core.base.general.favicon') ? url(config('core.base.general.favicon')) : null),
            'logoHeight' => max(10, min(300, (int) setting('admin_logo_max_height', 32))),
            'loginBackground' => request()->user() ? null : $this->getLoginPageBackgroundUrl(),
            'containerWidth' => is_string($container) && array_key_exists($container, $this->getContainerWidths()) ? $container : null,
            'showMenuItemIcon' => $this->showMenuItemIcon(),
            'direction' => $direction === 'rtl' ? 'rtl' : 'ltr',
            'themeMode' => in_array($themeMode, ['light', 'dark'], true) ? $themeMode : null,
            'colors' => (object) $colors,
            'font' => $font ? ['family' => $font, 'url' => $this->reactFontStylesheet($font)] : null,
        ];
    }

    protected function reactFontStylesheet(string $font): ?string
    {
        if (! config('core.base.general.google_fonts_enabled', true)) {
            return null;
        }
        $source = 'https://fonts.googleapis.com/css2?family='.urlencode($font).':wght@300;400;500;600;700&display=swap';
        $fallback = str_replace('https://fonts.googleapis.com', BaseHelper::getGoogleFontsURL(), $source);
        $url = $fallback;
        if (config('core.base.general.google_fonts_enabled_cache', true)) {
            try {
                $url = app('core.google-fonts')->load($source)?->url() ?? $fallback;
            } catch (Throwable) {
                $url = $fallback;
            }
        }

        return filter_var($url, FILTER_VALIDATE_URL) && in_array(parse_url($url, PHP_URL_SCHEME), ['http', 'https'], true) ? $url : null;
    }

    public function getLoginPageBackgroundUrl(): ?string
    {
        $backgrounds = config('core.acl.general.backgrounds', []);
        $selected = $backgrounds ? Arr::random($backgrounds) : null;
        $default = $selected && str_starts_with($selected, 'platform/core/acl/resources/js/admin/assets/backgrounds/')
            ? $selected
            : ($selected ? url($selected) : null);
        $images = setting('login_screen_backgrounds', []);
        $images = is_array($images) ? $images : (json_decode((string) $images, true) ?? []);
        $images = is_array($images) ? array_values(array_filter($images, fn (mixed $image): bool => is_string($image) && filled($image))) : [];

        return $images ? RvMedia::getImageUrl(Arr::random($images)) : $default;
    }

    public function getCustomCSS(): string
    {
        $css = $this->getSetting('custom_css');

        if (empty($css)) {
            return '';
        }

        return Html::tag('style', $css);
    }

    public function getCustomJS(string $location): string
    {
        $js = $this->getSetting('custom_'.$location.'_js');

        if (empty($js)) {
            return '';
        }

        if ((! Str::contains($js, '<script') || ! Str::contains($js, '</script>')) && ! Str::contains($js, '<noscript') && ! Str::contains($js, '</noscript>')) {
            $js = Html::tag('script', $js);
        }

        return $js;
    }
}
