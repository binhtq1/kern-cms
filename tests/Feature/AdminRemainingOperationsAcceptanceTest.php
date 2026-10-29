<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Kern\Setting\Facades\Setting;
use Kern\Slug\Models\Slug;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingOperationsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private string $sourceRoot;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        $this->sourceRoot = base_path();
        $this->fixtureRoot = $this->sourceRoot.'/tmp/acceptance-operations-'.bin2hex(random_bytes(8));
        foreach (['platform/plugins', 'platform/themes', 'public/storage', 'storage/framework/views', 'storage/logs', 'bootstrap/cache', 'lang', 'resources', 'config'] as $directory) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$directory);
        }
        File::copyDirectory(public_path('build'), $this->fixtureRoot.'/public/build');
        $this->app->setBasePath($this->fixtureRoot);
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->useBootstrapPath($this->fixtureRoot.'/bootstrap');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config([
            'packages.installer.installer.enabled' => false,
            'filesystems.default' => 'public',
            'core.media.media.disk' => 'public',
            'filesystems.disks.public.root' => $this->fixtureRoot.'/public/storage',
            'filesystems.disks.local.root' => $this->fixtureRoot.'/storage/private',
            'view.compiled' => $this->fixtureRoot.'/storage/framework/views',
            'purifier.cachePath' => $this->fixtureRoot.'/storage/purifier',
            'packages.plugin-management.general.enable_plugin_list_cache' => false,
        ]);
        Storage::forgetDisk('public');
        Storage::forgetDisk('local');
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->fixtureRoot);
        $this->app->setBasePath($this->sourceRoot);
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    #[TestWith([false])]
    #[TestWith([true])]
    public function test_selected_media_permanent_delete_removes_only_trashed_target_and_nested_children(bool $isFolder): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        $folder = MediaFolder::query()->create(['name' => 'Selected parent', 'slug' => 'selected-parent', 'parent_id' => 0, 'user_id' => $actor->id]);
        $child = MediaFolder::query()->create(['name' => 'Selected child', 'slug' => 'selected-child', 'parent_id' => $folder->id, 'user_id' => $actor->id]);
        $records = [];
        foreach ([['selected.txt', 0], ['selected-parent/parent.txt', $folder->id], ['selected-parent/selected-child/child.txt', $child->id], ['keep-live.txt', 0], ['keep-trashed.txt', 0]] as [$path, $folderId]) {
            $records[] = MediaFile::query()->create(['name' => basename($path), 'url' => $path, 'mime_type' => 'text/plain', 'size' => 12, 'visibility' => 'public', 'folder_id' => $folderId, 'user_id' => $actor->id]);
            Storage::put($path, 'original '.$path);
        }
        $records[4]->delete();
        $selected = [['id' => (string) ($isFolder ? $folder->id : $records[0]->id), 'is_folder' => $isFolder]];
        $this->postJson(route('media.global_actions'), ['action' => 'trash', 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        $this->postJson(route('media.global_actions'), ['action' => 'delete', 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        foreach ($records as $index => $file) {
            $removed = $isFolder ? in_array($index, [1, 2], true) : $index === 0;
            if ($removed) {
                $this->assertDatabaseMissing('media_files', ['id' => $file->id]);
                Storage::assertMissing($file->url);
            } else {
                $this->assertDatabaseHas('media_files', ['id' => $file->id]);
                $this->assertSame('original '.$file->url, Storage::get($file->url));
            }
        }
        $this->assertSoftDeleted('media_files', ['id' => $records[4]->id]);
        $this->assertDatabaseHas('media_files', ['id' => $records[3]->id, 'deleted_at' => null]);
        foreach ([$folder, $child] as $item) {
            if ($isFolder) {
                $this->assertDatabaseMissing('media_folders', ['id' => $item->id]);
            } else {
                $this->assertDatabaseHas('media_folders', ['id' => $item->id, 'deleted_at' => null]);
            }
        }
    }

    #[TestWith(['rename', false, 'files.edit'])]
    #[TestWith(['copy', false, 'files.create'])]
    #[TestWith(['move', false, 'files.edit'])]
    #[TestWith(['rename', true, 'folders.edit'])]
    #[TestWith(['move', true, 'folders.edit'])]
    #[TestWith(['delete', true, 'folders.destroy'])]
    #[TestWith(['delete', false, 'files.destroy'])]
    public function test_media_capability_and_foreign_owner_denials_leave_records_and_files_unchanged(string $action, bool $isFolder, string $permission): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $foreign = UserFactory::new()->create();
        $folder = MediaFolder::query()->create(['name' => 'Owned folder', 'slug' => 'owned-folder', 'parent_id' => 0, 'user_id' => $user->getKey()]);
        $destination = MediaFolder::query()->create(['name' => 'Destination', 'slug' => 'destination', 'parent_id' => 0, 'user_id' => $user->getKey()]);
        $file = MediaFile::query()->create(['name' => 'Owned file', 'url' => 'owned-folder/original.txt', 'mime_type' => 'text/plain', 'size' => 8, 'visibility' => 'public', 'folder_id' => $folder->getKey(), 'user_id' => $user->getKey()]);
        Storage::put($file->url, 'original');
        $this->actingAs($user);
        Setting::set('user_can_only_view_own_media', true)->save();
        $selected = ['id' => (string) ($isFolder ? $folder->getKey() : $file->getKey()), 'is_folder' => $isFolder, 'name' => 'Changed'];
        $payload = ['action' => $action, 'destination_id' => $destination->getKey(), 'selected' => [$selected]];
        $initialFiles = Storage::allFiles();
        $initialFile = $file->fresh()->getAttributes();
        $initialFolder = $folder->fresh()->getAttributes();
        $permissions = RvMedia::getPermissions();
        RvMedia::setPermissions(array_values(array_diff($permissions, [$permission])));

        $this->postJson(route('media.global_actions'), $payload)->assertForbidden();
        $this->assertSame($initialFile, $file->fresh()->getAttributes());
        $this->assertSame($initialFolder, $folder->fresh()->getAttributes());
        $this->assertSame($initialFiles, Storage::allFiles());
        $this->assertSame('original', Storage::get($file->url));

        RvMedia::setPermissions($permissions);
        $target = $isFolder ? $folder : $file;
        $target->update(['user_id' => $foreign->getKey()]);
        $foreignAttributes = $target->fresh()->getAttributes();
        $this->postJson(route('media.global_actions'), $payload)->assertForbidden();
        $this->assertSame($foreignAttributes, $target->fresh()->getAttributes());
        $this->assertDatabaseCount('media_files', 1);
        $this->assertDatabaseCount('media_folders', 2);
        $this->assertSame($initialFiles, Storage::allFiles());
        $this->assertSame('original', Storage::get($file->url));
    }

    private function fixturePlugin(): void
    {
        File::ensureDirectoryExists(plugin_path('acceptance-fixture/src/Providers'));
        File::ensureDirectoryExists(plugin_path('acceptance-fixture/public'));
        File::put(plugin_path('acceptance-fixture/plugin.json'), json_encode([
            'id' => 'test/acceptance-fixture', 'name' => 'Acceptance fixture', 'namespace' => 'Acceptance\\FixturePlugin\\',
            'provider' => 'Acceptance\\FixturePlugin\\Providers\\ServiceProvider', 'version' => '1.0.0', 'description' => 'Isolated fixture', 'author' => 'Test', 'url' => '',
        ]));
        File::put(plugin_path('acceptance-fixture/src/Providers/ServiceProvider.php'), '<?php namespace Acceptance\\FixturePlugin\\Providers; class ServiceProvider extends \\Kern\\Base\\Supports\\ServiceProvider { use \\Kern\\Base\\Traits\\LoadAndPublishDataTrait; public function boot(): void { $this->setNamespace("plugins/acceptance-fixture"); } }');
        File::put(plugin_path('acceptance-fixture/public/fixture.txt'), 'plugin asset');
        File::ensureDirectoryExists(plugin_path('unrelated'));
        File::put(plugin_path('unrelated/sentinel.txt'), 'keep');
        File::put(public_path('sentinel.txt'), 'keep public');
    }

    public function test_plugin_activation_deactivation_and_inactive_removal_are_isolated(): void
    {
        $this->fixturePlugin();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->putJson(route('plugins.change.status'), ['name' => 'acceptance-fixture'])->assertOk()->assertJsonPath('error', false);
        $this->assertContains('acceptance-fixture', get_active_plugins());
        $this->assertSame('plugin asset', File::get(public_path('vendor/core/plugins/acceptance-fixture/fixture.txt')));
        $this->putJson(route('plugins.change.status'), ['name' => 'acceptance-fixture'])->assertOk()->assertJsonPath('error', false);
        $this->assertNotContains('acceptance-fixture', get_active_plugins());
        $this->deleteJson(route('plugins.remove', 'acceptance-fixture'))->assertOk()->assertJsonPath('error', false);
        $this->assertFalse(File::exists(plugin_path('acceptance-fixture')));
        $this->assertFalse(File::exists(public_path('vendor/core/plugins/acceptance-fixture')));
        $this->assertSame('keep', File::get(plugin_path('unrelated/sentinel.txt')));
        $this->assertSame('keep public', File::get(public_path('sentinel.txt')));
    }

    public function test_plugin_permission_and_demo_denials_preserve_inactive_fixture(): void
    {
        $this->fixturePlugin();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['plugins.index' => true]]));
        $this->putJson(route('plugins.change.status'), ['name' => 'acceptance-fixture'])->assertUnauthorized();
        $this->deleteJson(route('plugins.remove', 'acceptance-fixture'))->assertUnauthorized();
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.general.demo_mode_enabled' => true]);
        $this->putJson(route('plugins.change.status'), ['name' => 'acceptance-fixture'])->assertJsonPath('error', true);
        $this->deleteJson(route('plugins.remove', 'acceptance-fixture'))->assertJsonPath('error', true);
        $this->assertTrue(File::exists(plugin_path('acceptance-fixture/plugin.json')));
        $this->assertNotContains('acceptance-fixture', get_active_plugins());
        $this->assertSame('keep', File::get(plugin_path('unrelated/sentinel.txt')));
    }

    public function test_cloned_theme_switch_and_switch_back_preserve_options_content_assets_and_public_rendering(): void
    {
        File::ensureDirectoryExists(package_path('theme/routes'));
        File::copy($this->sourceRoot.'/platform/packages/theme/routes/public.php', package_path('theme/routes/public.php'));
        $firstPath = theme_path('acceptance-first');
        File::ensureDirectoryExists($firstPath.'/public');
        File::ensureDirectoryExists($firstPath.'/layouts');
        File::ensureDirectoryExists($firstPath.'/views');
        File::ensureDirectoryExists($firstPath.'/routes');
        File::put($firstPath.'/routes/web.php', '<?php \\Kern\\Theme\\Facades\\Theme::routes();');
        File::put($firstPath.'/theme.json', json_encode(['id' => 'test/first', 'name' => 'Acceptance first', 'namespace' => 'Theme\\AcceptanceFirst\\', 'version' => '1.0', 'author' => 'Test', 'required_plugins' => []]));
        File::put($firstPath.'/public/fixture.txt', 'first asset');
        File::put($firstPath.'/layouts/default.blade.php', '<!DOCTYPE html><html><body>Fixture theme {!! Theme::content() !!}</body></html>');
        File::put($firstPath.'/views/page.blade.php', '<main>{!! BaseHelper::clean($page->content) !!}</main>');
        File::copyDirectory($firstPath, theme_path('acceptance-clone'));
        File::put(theme_path('acceptance-clone/theme.json'), json_encode(['id' => 'test/clone', 'name' => 'Acceptance clone', 'namespace' => 'Theme\\AcceptanceClone\\', 'version' => '1.0', 'author' => 'Test', 'required_plugins' => []]));
        File::put(theme_path('acceptance-clone/public/fixture.txt'), 'clone asset');
        $this->actingAs(UserFactory::new()->superUser()->create());
        Setting::set(['theme-acceptance-first-primary_color' => '#123456', 'theme-acceptance-clone-primary_color' => '#654321'])->save();
        $page = PageFactory::new()->create(['name' => 'Theme switch content', 'content' => '<p>Keep published content</p>', 'status' => 'published']);
        Slug::query()->create(['key' => 'theme-switch-page', 'prefix' => '', 'reference_type' => $page::class, 'reference_id' => $page->getKey()]);
        foreach (['acceptance-first', 'acceptance-clone', 'acceptance-first'] as $theme) {
            $this->postJson(route('theme.active'), ['theme' => $theme])->assertOk()->assertJsonPath('error', false);
            $this->assertSame($theme, setting('theme'));
            $this->assertSame('<p>Keep published content</p>', $page->fresh()->content);
            $this->assertSame('#123456', setting('theme-acceptance-first-primary_color'));
            $this->assertSame('#654321', setting('theme-acceptance-clone-primary_color'));
            $this->assertSame($theme === 'acceptance-first' ? 'first asset' : 'clone asset', File::get(public_path('themes/'.$theme.'/fixture.txt')));
            $this->app->register(ThemeManagementServiceProvider::class, true);
            $this->app->register(RouteServiceProvider::class, true);
            Route::getRoutes()->refreshNameLookups();
            $this->get('/theme-switch-page')->assertOk()->assertSee('Keep published content')->assertSee('Fixture theme');
        }
        $this->assertSame('first asset', File::get($firstPath.'/public/fixture.txt'));
        $this->assertSame('clone asset', File::get(theme_path('acceptance-clone/public/fixture.txt')));
    }
}
