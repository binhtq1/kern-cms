<?php

namespace Database\Seeders;

use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Models\User;
use Kern\Base\Supports\BaseSeeder;
use Kern\Block\Database\Seeders\StaticBlockSeeder;
use Kern\Contact\Database\Seeders\ContactSeeder;
use Kern\Language\Database\Seeders\LanguageSeeder;
use Kern\Language\Models\Language;
use Kern\PluginManagement\Services\PluginService;
use Kern\Setting\Models\Setting;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use RuntimeException;
use Throwable;

class RippleDemoSeeder extends BaseSeeder
{
    public function run(): void
    {
        if (! User::query()->where('super_user', true)->exists()) {
            throw new RuntimeException('Create an administrator with cms:user:create before importing the Ripple demo.');
        }

        foreach (['pages', 'posts', 'categories', 'tags', 'galleries', 'members', 'menus', 'menu_nodes', 'menu_locations', 'widgets', 'media_files', 'media_folders', 'blocks', 'contacts'] as $table) {
            if (Schema::hasTable($table) && DB::table($table)->exists()) {
                throw new RuntimeException('Ripple demo requires an empty content database. Existing data found in '.$table.'.');
            }
        }

        if ((setting('theme') && setting('theme') !== 'ripple')
            || Setting::query()->where('key', 'like', 'theme-ripple-%')->exists()) {
            throw new RuntimeException('Ripple demo cannot replace an existing theme configuration.');
        }
        foreach (['news', 'members', 'general'] as $folder) {
            if (Storage::disk('public')->exists($folder)) {
                throw new RuntimeException('Ripple demo cannot replace existing media in '.$folder.'.');
            }
        }

        if (setting('theme') !== 'ripple') {
            $result = app(ThemeService::class)->activate('ripple');
            if ($result['error']) {
                throw new RuntimeException($result['message']);
            }
        }
        foreach (['blog', 'contact', 'gallery', 'language', 'language-advanced', 'member', 'cookie-consent', 'block'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $result = app(PluginService::class)->activate($plugin);
                if ($result['error']) {
                    throw new RuntimeException($result['message']);
                }
            }
        }
        app()->register(ThemeManagementServiceProvider::class, true);

        try {
            DB::transaction(function (): void {
                if (! Language::query()->exists()) {
                    $this->call(LanguageSeeder::class);
                }
                $this->call([
                    BlogSeeder::class,
                    PageSeeder::class,
                    GallerySeeder::class,
                    MemberSeeder::class,
                    ContactSeeder::class,
                    StaticBlockSeeder::class,
                    MenuSeeder::class,
                    WidgetSeeder::class,
                    ThemeOptionSeeder::class,
                ]);

                $this->finished();
            });
        } catch (Throwable $exception) {
            foreach (['news', 'members', 'general'] as $folder) {
                Storage::disk('public')->deleteDirectory($folder);
            }
            throw $exception;
        }
    }
}
