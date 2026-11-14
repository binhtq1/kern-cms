<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Kern\Media\Facades\RvMedia;
use Kern\Media\Models\MediaFile;
use Kern\Media\Models\MediaFolder;
use Kern\Media\Models\MediaSetting;
use Kern\PluginManagement\Services\PluginService;
use Kern\Setting\Facades\Setting;
use Kern\Theme\Events\RenderingThemeOptionSettings;
use Kern\Theme\Facades\ThemeOption;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminMediaThemeTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.media' => 'inertia', 'core.base.admin.modules.themes' => 'inertia', 'core.base.admin.modules.theme-options' => 'inertia', 'core.base.admin.modules.theme-assets' => 'inertia']);
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    protected function file(int $userId, string $name = 'Example image'): MediaFile
    {
        return MediaFile::query()->create(['name' => $name, 'url' => $name.'.png', 'mime_type' => 'image/png', 'visibility' => 'public', 'user_id' => $userId, 'size' => 10, 'folder_id' => 0]);
    }

    public function test_crop_updates_actual_image_size_and_dimensions_without_changing_another_original(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public', 'core.media.media.disk' => 'public']);
        $user = UserFactory::new()->superUser()->create();
        $image = UploadedFile::fake()->image('crop.jpg', 600, 400);
        $originalBytes = file_get_contents($image->getPathname());
        Storage::disk('public')->put('crop/source.jpg', $originalBytes);
        Storage::disk('public')->put('crop/copy.jpg', $originalBytes);
        $original = $this->file($user->id, 'Original');
        $original->update(['url' => 'crop/source.jpg', 'mime_type' => 'image/jpeg', 'size' => strlen($originalBytes)]);
        $copy = $this->file($user->id, 'Crop copy');
        $copy->update(['url' => 'crop/copy.jpg?v=old', 'mime_type' => 'image/jpeg', 'size' => strlen($originalBytes)]);

        $this->actingAs($user)->postJson(route('media.global_actions'), ['action' => 'crop', 'imageId' => (string) $copy->getKey(), 'cropData' => json_encode(['x' => 100, 'y' => 100, 'width' => 300, 'height' => 200])])->assertOk()->assertJsonPath('error', false);

        $cropped = Storage::disk('public')->get('crop/copy.jpg');
        $dimensions = getimagesizefromstring($cropped);
        $this->assertSame([300, 200], [$dimensions[0], $dimensions[1]]);
        $this->assertSame(strlen($cropped), (int) $copy->fresh()->size);
        $this->assertSame(Storage::disk('public')->size('crop/copy.jpg'), (int) $copy->fresh()->size);
        $this->assertNotSame(strlen($originalBytes), strlen($cropped));
        $this->assertStringStartsWith('crop/copy.jpg?v=', $copy->fresh()->url);
        $this->assertNotSame('crop/copy.jpg?v=old', $copy->fresh()->url);
        $this->assertSame($originalBytes, Storage::disk('public')->get('crop/source.jpg'));
        $this->assertSame(strlen($originalBytes), (int) $original->fresh()->size);
    }

    public function test_failed_crop_keeps_original_bytes_and_metadata(): void
    {
        Storage::fake('public');
        config(['filesystems.default' => 'public', 'core.media.media.disk' => 'public']);
        $user = UserFactory::new()->superUser()->create();
        Storage::disk('public')->put('broken.jpg', 'invalid-image-bytes');
        $file = $this->file($user->id, 'Broken image');
        $file->update(['url' => 'broken.jpg?v=old', 'mime_type' => 'image/jpeg', 'size' => 19]);
        $before = $file->fresh()->getAttributes();

        $this->actingAs($user)->postJson(route('media.global_actions'), ['action' => 'crop', 'imageId' => (string) $file->getKey(), 'cropData' => json_encode(['x' => 0, 'y' => 0, 'width' => 100, 'height' => 100])])->assertOk()->assertJsonPath('error', true);

        $this->assertSame($before, $file->fresh()->getAttributes());
        $this->assertSame('invalid-image-bytes', Storage::disk('public')->get('broken.jpg'));
        $this->assertSame(['broken.jpg'], Storage::disk('public')->allFiles());
    }

    public function test_folder_color_control_uses_allowed_colors_and_existing_permission_boundary(): void
    {
        $user = UserFactory::new()->superUser()->create();
        $this->actingAs($user);
        $folder = MediaFolder::query()->create(['name' => 'Colored folder', 'slug' => 'colored-folder', 'parent_id' => 0, 'user_id' => $user->id]);
        $colors = RvMedia::getFolderColors();

        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.folderColors', $colors);
        $this->postJson(route('media.global_actions'), ['action' => 'properties', 'selected' => [(string) $folder->id], 'color' => $colors[0]])->assertOk()->assertJsonPath('error', false);
        $this->assertSame($colors[0], $folder->fresh()->color);
        $this->postJson(route('media.global_actions'), ['action' => 'properties', 'selected' => [(string) $folder->id], 'color' => 'not-allowed'])->assertUnprocessable();
        config(['core.media.media.permissions' => array_values(array_diff(config('core.media.media.permissions'), ['folders.edit']))]);
        RvMedia::clearResolvedInstance(\Kern\Media\RvMedia::class);
        $this->postJson(route('media.global_actions'), ['action' => 'properties', 'selected' => [(string) $folder->id], 'color' => $colors[0]])->assertForbidden();
    }

    public function test_media_picker_resolves_exact_image_paths_without_exposing_other_owners(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $own = $this->file($user->getKey(), 'Picker own image');
        $other = $this->file(UserFactory::new()->create()->getKey(), 'Picker private image');
        setting()->set('user_can_only_view_own_media', true)->save();
        $this->actingAs($user);
        $this->getJson(route('media.picker', ['path' => $own->url, 'type' => 'image']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.url', $own->url);
        $this->getJson(route('media.picker', ['path' => $other->url, 'type' => 'image']))->assertOk()->assertJsonCount(0, 'data');
        $this->getJson(route('media.picker', ['path' => str_repeat('x', 2049)]))->assertUnprocessable()->assertJsonValidationErrors('path');
    }

    public function test_media_picker_browses_folders_with_breadcrumbs_and_hides_other_owners(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $other = UserFactory::new()->create();
        $root = MediaFolder::query()->create(['name' => 'Brand', 'slug' => 'brand', 'parent_id' => 0, 'user_id' => $user->id]);
        $child = MediaFolder::query()->create(['name' => 'Banners', 'slug' => 'banners', 'parent_id' => $root->id, 'user_id' => $user->id]);
        $foreign = MediaFolder::query()->create(['name' => 'Other owner', 'slug' => 'other', 'parent_id' => 0, 'user_id' => $other->id]);
        $rootImage = $this->file($user->id, 'Root image');
        $image = $this->file($user->id, 'Banner image');
        $image->update(['folder_id' => $child->id]);
        $foreignImage = $this->file($other->id, 'Foreign image');
        $foreignImage->update(['folder_id' => $child->id]);
        setting()->set('user_can_only_view_own_media', true)->save();
        $this->actingAs($user);
        $this->getJson(route('media.picker', ['folder_id' => 0, 'type' => 'image']))->assertOk()->assertJsonCount(1, 'folders')->assertJsonPath('folders.0.id', (string) $root->id)->assertJsonPath('data.0.url', $rootImage->url)->assertJsonCount(0, 'breadcrumbs');
        $this->getJson(route('media.picker', ['folder_id' => $root->id]))->assertOk()->assertJsonCount(0, 'data')->assertJsonPath('folders.0.id', (string) $child->id);
        $this->getJson(route('media.picker', ['folder_id' => $child->id, 'type' => 'image']))->assertOk()->assertJsonCount(1, 'data')->assertJsonPath('data.0.url', $image->url)->assertJsonPath('breadcrumbs.0.name', 'Brand')->assertJsonPath('breadcrumbs.1.name', 'Banners');
        $this->getJson(route('media.picker', ['folder_id' => $foreign->id]))->assertForbidden();
        $this->getJson(route('media.picker', ['folder_id' => -1]))->assertUnprocessable()->assertJsonValidationErrors('folder_id');
        $this->getJson(route('media.picker', ['folder_id' => 'invalid-id']))->assertUnprocessable()->assertJsonValidationErrors('folder_id');
        $this->getJson(route('media.picker', ['path' => $image->url]))->assertOk()->assertJsonPath('data.0.url', $image->url);
    }

    public function test_picker_upload_uses_the_selected_folder_and_rejects_foreign_destinations(): void
    {
        Storage::fake('public');
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $folder = MediaFolder::query()->create(['name' => 'Upload here', 'slug' => 'upload-here', 'parent_id' => 0, 'user_id' => $user->id]);
        $foreign = MediaFolder::query()->create(['name' => 'Foreign', 'slug' => 'foreign', 'parent_id' => 0, 'user_id' => UserFactory::new()->create()->id]);
        setting()->set('user_can_only_view_own_media', true)->save();
        $this->actingAs($user);
        $response = $this->postJson(route('media.picker-upload'), ['file' => UploadedFile::fake()->image('picker.png'), 'folder_id' => $folder->id])->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.mime_type', 'image/png');
        $this->assertDatabaseHas('media_files', ['id' => $response->json('data.id'), 'folder_id' => $folder->id, 'user_id' => $user->id]);
        $this->postJson(route('media.picker-upload'), ['file' => UploadedFile::fake()->image('blocked.png'), 'folder_id' => $foreign->id])->assertForbidden();
        $this->assertDatabaseCount('media_files', 1);
        $this->postJson(route('media.picker-upload'), ['file' => UploadedFile::fake()->image('invalid.png'), 'folder_id' => -1])->assertUnprocessable()->assertJsonValidationErrors('folder_id');
    }

    public function test_media_requires_acl_and_ignores_legacy_module_flag(): void
    {
        $this->get(route('media.index'), $this->headers())->assertStatus(409);
        $this->actingAs(UserFactory::new()->create());
        $this->get(route('media.index'), $this->headers())->assertForbidden();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Media/Index');
        config(['core.base.admin.modules.media' => 'blade']);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('component', 'Media/Index');
    }

    public function test_media_picker_list_and_mutations_respect_owner_and_capability_boundaries(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $other = UserFactory::new()->create();
        Setting::set('user_can_only_view_own_media', true)->save();
        $owned = $this->file($user->id, 'Owned');
        $foreign = $this->file($other->id, 'Foreign');
        $folder = MediaFolder::query()->create(['name' => 'Foreign folder', 'slug' => 'foreign', 'parent_id' => 0, 'user_id' => $other->id]);
        $this->actingAs($user);
        $this->getJson(route('media.picker'))->assertOk()->assertJsonPath('total', 1)->assertJsonPath('data.0.name', 'Owned');
        $this->getJson(route('media.list', ['view_in' => 'all_media', 'sort_by' => 'name-asc']))->assertOk()->assertJsonCount(1, 'data.files')->assertJsonCount(0, 'data.folders');
        $this->getJson(route('media.list', ['view_in' => 'all_media', 'folder_id' => $folder->id]))->assertForbidden();
        $this->postJson(route('media.global_actions'), ['action' => 'trash', 'selected' => [['id' => (string) $foreign->id, 'is_folder' => false]]])->assertForbidden();
        $this->postJson(route('media.download'), ['selected' => [['id' => (string) $foreign->id, 'is_folder' => false]]])->assertForbidden();
        $ownedFolder = MediaFolder::query()->create(['name' => 'Owned mixed', 'slug' => 'owned-mixed', 'parent_id' => 0, 'user_id' => $user->id]);
        $foreign->update(['folder_id' => $ownedFolder->id]);
        $this->postJson(route('media.download'), ['selected' => [['id' => (string) $ownedFolder->id, 'is_folder' => true]]])->assertForbidden();
        $this->postJson(route('media.folders.create'), ['name' => 'Injected child', 'parent_id' => $folder->id])->assertForbidden();
        config(['core.media.media.permissions' => array_values(array_diff(config('core.media.media.permissions'), ['files.trash']))]);
        RvMedia::clearResolvedInstance(\Kern\Media\RvMedia::class);
        $this->postJson(route('media.global_actions'), ['action' => 'trash', 'selected' => [['id' => (string) $owned->id, 'is_folder' => false]]])->assertForbidden();
        $this->assertDatabaseHas('media_files', ['id' => $owned->id, 'deleted_at' => null]);
    }

    public function test_media_rename_alt_favorite_trash_and_restore_preserve_services(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = UserFactory::new()->superUser()->create();
        $file = $this->file($user->id);
        $this->actingAs($user);
        $selected = [['id' => (string) $file->id, 'is_folder' => false]];
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selected[0], 'name' => 'Renamed']]])->assertOk()->assertJsonPath('error', false);
        $this->postJson(route('media.global_actions'), ['action' => 'alt_text', 'selected' => [[...$selected[0], 'alt' => 'Accessible description']]])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('media_files', ['id' => $file->id, 'name' => 'Renamed', 'alt' => 'Accessible description']);
        $this->postJson(route('media.global_actions'), ['action' => 'favorite', 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        $this->getJson(route('media.list', ['view_in' => 'favorites']))->assertOk()->assertJsonCount(1, 'data.files');
        $this->postJson(route('media.global_actions'), ['action' => 'trash', 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        $this->assertSoftDeleted('media_files', ['id' => $file->id]);
        $this->postJson(route('media.global_actions'), ['action' => 'restore', 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('media_files', ['id' => $file->id, 'deleted_at' => null]);
    }

    public function test_media_physical_rename_is_opt_in_and_moves_file_and_nested_folder_bytes(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['filesystems.default' => 'public', 'core.media.media.disk' => 'public']);
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        $file = MediaFile::query()->create(['name' => 'original.txt', 'url' => 'original.txt', 'mime_type' => 'text/plain', 'visibility' => 'public', 'user_id' => $actor->id, 'size' => 12, 'folder_id' => 0]);
        Storage::put('original.txt', 'original file bytes');
        $selected = ['id' => (string) $file->id, 'is_folder' => false];
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selected, 'name' => 'Display only', 'rename_physical_file' => false]]])->assertOk()->assertJsonPath('error', false);
        $this->assertSame('Display only', $file->fresh()->name);
        $this->assertSame('original.txt', $file->fresh()->url);
        $this->assertSame('original file bytes', Storage::get('original.txt'));
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selected, 'name' => 'Physical.txt', 'rename_physical_file' => true]]])->assertOk()->assertJsonPath('error', false);
        $this->assertSame('Physical.txt', $file->fresh()->url);
        Storage::assertMissing('original.txt');
        $this->assertSame('original file bytes', Storage::get('Physical.txt'));

        $folder = MediaFolder::query()->create(['name' => 'Original folder', 'slug' => 'original_folder', 'parent_id' => 0, 'user_id' => $actor->id]);
        $child = MediaFolder::query()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $folder->id, 'user_id' => $actor->id]);
        $nested = MediaFile::query()->create(['name' => 'nested.txt', 'url' => 'original_folder/child/nested.txt', 'mime_type' => 'text/plain', 'visibility' => 'public', 'user_id' => $actor->id, 'size' => 12, 'folder_id' => $child->id]);
        Storage::put($nested->url, 'nested original bytes');
        $unrelated = MediaFile::query()->create(['name' => 'other.txt', 'url' => 'originalXfolder/other.txt', 'mime_type' => 'text/plain', 'visibility' => 'public', 'user_id' => $actor->id, 'size' => 12, 'folder_id' => 0]);
        $selection = ['id' => (string) $folder->id, 'is_folder' => true];
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selection, 'name' => 'Display folder', 'rename_physical_file' => false]]])->assertOk()->assertJsonPath('error', false);
        $this->assertSame('original_folder', $folder->fresh()->slug);
        $this->assertSame('original_folder/child/nested.txt', $nested->fresh()->url);
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selection, 'name' => 'Physical folder', 'rename_physical_file' => true]]])->assertOk()->assertJsonPath('error', false);
        $this->assertSame('physical-folder', $folder->fresh()->slug);
        $this->assertSame('physical-folder/child/nested.txt', $nested->fresh()->url);
        Storage::assertMissing('original_folder/child/nested.txt');
        $this->assertSame('nested original bytes', Storage::get('physical-folder/child/nested.txt'));
        $this->assertSame('originalXfolder/other.txt', $unrelated->fresh()->url);
        Setting::set('user_can_only_view_own_media', true)->save();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['media.index' => true]]));
        $this->postJson(route('media.global_actions'), ['action' => 'rename', 'selected' => [[...$selected, 'name' => 'Denied.txt', 'rename_physical_file' => true]]])->assertForbidden();
        $this->actingAs($actor);
        $this->assertSame('Physical.txt', $file->fresh()->url);
        $this->assertSame('original file bytes', Storage::get('Physical.txt'));
    }

    public function test_media_navigation_key_is_scoped_to_actor_and_invalidated_by_legacy_application_hash(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        Setting::forceSet('media_random_hash', 'acceptance-hash-one')->save();
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.navigationKey', $actor->id.':acceptance-hash-one');
        Setting::forceSet('media_random_hash', 'acceptance-hash-two')->save();
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.navigationKey', $actor->id.':acceptance-hash-two');
        $other = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $this->actingAs($other);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.navigationKey', $other->id.':acceptance-hash-two');
    }

    public function test_document_preview_urls_follow_public_localhost_and_provider_configuration_boundaries(): void
    {
        $actor = UserFactory::new()->superUser()->create();
        $this->actingAs($actor);
        config(['core.media.media.preview.document.enabled' => true, 'core.media.media.preview.document.default' => 'microsoft', 'core.media.media.preview.document.type' => 'iframe']);
        $files = [];
        foreach (['public-pdf' => ['application/pdf', 'public'], 'private-pdf' => ['application/pdf', 'private'], 'office' => ['application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'public'], 'audio' => ['audio/mpeg', 'public'], 'video' => ['video/mp4', 'public']] as $name => [$mime, $visibility]) {
            $files[$name] = MediaFile::query()->create(['name' => $name, 'url' => $name, 'mime_type' => $mime, 'visibility' => $visibility, 'user_id' => $actor->id, 'size' => 12, 'folder_id' => 0]);
        }
        $url = route('media.list', ['view_in' => 'all_media']);
        $local = collect($this->withServerVariables(['REMOTE_ADDR' => '127.0.0.1'])->getJson($url)->assertOk()->json('data.files'))->keyBy('name');
        $this->assertSame(RvMedia::url('public-pdf'), $local['public-pdf']['preview_url']);
        $this->assertSame('document', $local['public-pdf']['type']);
        $this->assertSame('iframe', $local['public-pdf']['preview_type']);
        $this->assertNull($local['private-pdf']['preview_url']);
        $this->assertNull($local['office']['preview_url']);
        $this->assertNull($local['audio']['preview_url']);
        $this->assertSame(RvMedia::url('video'), $local['video']['preview_url']);
        $remote = collect($this->withServerVariables(['REMOTE_ADDR' => '203.0.113.8'])->getJson($url)->assertOk()->json('data.files'))->keyBy('name');
        $this->assertSame('https://view.officeapps.live.com/op/view.aspx?src='.urlencode(RvMedia::url('office')), $remote['office']['preview_url']);
        config(['core.media.media.preview.document.enabled' => false]);
        $disabled = collect($this->getJson($url)->assertOk()->json('data.files'))->keyBy('name');
        $this->assertNull($disabled['office']['preview_url']);
        $this->assertSame(RvMedia::url('public-pdf'), $disabled['public-pdf']['preview_url']);
        config(['core.media.media.preview.document.enabled' => true, 'core.media.media.preview.document.type' => 'popup']);
        $popup = collect($this->getJson($url)->assertOk()->json('data.files'))->keyBy('name');
        $this->assertSame('popup', $popup['office']['preview_type']);
        $this->assertNull($popup['private-pdf']['preview_url']);
    }

    public function test_media_empty_trash_preserves_live_files_and_requires_global_destroy_access(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = UserFactory::new()->superUser()->create();
        $this->actingAs($user);
        $live = $this->file($user->id, 'Keep live');
        $trashed = $this->file($user->id, 'Remove trashed');
        $trashed->delete();

        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.canEmptyTrash', true);
        $this->postJson(route('media.global_actions'), ['action' => 'empty_trash'])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseMissing('media_files', ['id' => $trashed->id]);
        $this->assertDatabaseHas('media_files', ['id' => $live->id, 'deleted_at' => null]);

        config(['core.media.media.permissions' => array_values(array_diff(config('core.media.media.permissions'), ['folders.destroy']))]);
        RvMedia::clearResolvedInstance(\Kern\Media\RvMedia::class);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.canEmptyTrash', false);
        $this->postJson(route('media.global_actions'), ['action' => 'empty_trash'])->assertForbidden();
    }

    public function test_media_empty_trash_is_hidden_and_rejected_for_owner_only_users(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        setting()->set('user_can_only_view_own_media', true)->save();
        $file = $this->file($user->id, 'Keep owned trash');
        $file->delete();
        $this->actingAs($user);

        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.canEmptyTrash', false);
        $this->postJson(route('media.global_actions'), ['action' => 'empty_trash'])->assertForbidden();
        $this->assertSoftDeleted('media_files', ['id' => $file->id]);
    }

    public function test_media_url_upload_saves_to_selected_folder_and_requires_create_permission(): void
    {
        Storage::fake('public');
        $image = UploadedFile::fake()->image('remote-parity.png');
        Http::preventStrayRequests();
        Http::fake(['https://assets.example.test/remote-parity.png' => Http::response(file_get_contents($image->getPathname()), 200, ['Content-Type' => 'image/png'])]);
        $user = UserFactory::new()->superUser()->create();
        $folder = MediaFolder::query()->create(['name' => 'URL uploads', 'slug' => 'url-uploads', 'parent_id' => 0, 'user_id' => $user->id]);
        $this->actingAs($user);

        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.urls.importUrl', route('media.download_url'))->assertJsonPath('props.admin.capabilities.uploadMedia', true);
        $response = $this->postJson(route('media.download_url'), ['url' => 'https://assets.example.test/remote-parity.png', 'folderId' => $folder->id])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('media_files', ['id' => $response->json('data.id'), 'folder_id' => $folder->id, 'user_id' => $user->id]);
        config(['core.media.media.permissions' => array_values(array_diff(config('core.media.media.permissions'), ['files.create']))]);
        RvMedia::clearResolvedInstance(\Kern\Media\RvMedia::class);
        $this->get(route('media.index'), $this->headers())->assertOk()->assertJsonPath('props.admin.capabilities.uploadMedia', false);
        $this->postJson(route('media.download_url'), ['url' => 'https://assets.example.test/remote-parity.png', 'folderId' => $folder->id])->assertForbidden();
        $this->assertDatabaseCount('media_files', 1);
    }

    public function test_media_upload_and_folder_creation_are_validated(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->postJson(route('media.folders.create'), ['name' => '../invalid'])->assertUnprocessable();
        $this->postJson(route('media.folders.create'), ['name' => 'New folder', 'parent_id' => 0])->assertOk()->assertJsonPath('error', false);
        $this->postJson(route('media.picker-upload'), [])->assertUnprocessable();
        $this->post(route('media.picker-upload'), ['file' => UploadedFile::fake()->create('document.txt', 1, 'text/plain')], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('error', false);
        $this->assertDatabaseHas('media_files', ['name' => 'document']);
    }

    public function test_media_moves_and_copies_to_a_destination_without_breaking_public_urls(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = UserFactory::new()->superUser()->create();
        $destination = MediaFolder::query()->create(['name' => 'Destination', 'slug' => 'destination', 'parent_id' => 0, 'user_id' => $user->id]);
        $file = $this->file($user->id, 'Private original');
        $file->update(['visibility' => 'private']);
        Storage::put($file->url, 'original-content');
        $selected = [['id' => (string) $file->id, 'is_folder' => false]];
        $this->actingAs($user)->postJson(route('media.global_actions'), ['action' => 'copy', 'destination_id' => $destination->id, 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        $copy = MediaFile::query()->where('id', '!=', $file->id)->firstOrFail();
        $this->assertSame($destination->id, $copy->folder_id);
        $this->assertSame('private', $copy->visibility);
        $this->assertSame('private', Storage::getVisibility($copy->url));
        $this->assertSame('original-content', Storage::get($copy->url));
        $this->postJson(route('media.global_actions'), ['action' => 'move', 'destination_id' => $destination->id, 'selected' => $selected])->assertOk()->assertJsonPath('error', false);
        $this->assertSame($destination->id, $file->fresh()->folder_id);
        $this->assertSame($file->url, $file->fresh()->url);
        Storage::assertExists($file->url);
        $response = $this->postJson(route('media.download'), ['selected' => [['id' => (string) $destination->id, 'is_folder' => true]]])->assertOk();
        $archivePath = $response->baseResponse->getFile()->getPathname();
        $archive = new \ZipArchive;
        $archive->open($archivePath);
        $this->assertSame('original-content', $archive->getFromName('destination/'.basename($file->url)));
        $archive->close();
        File::delete($archivePath);
    }

    public function test_media_copy_failures_roll_back_and_folder_moves_reject_cycles(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        $user = UserFactory::new()->superUser()->create();
        $first = $this->file($user->id, 'Existing');
        $second = $this->file($user->id, 'Missing');
        Storage::put($first->url, 'original');
        $this->actingAs($user)->postJson(route('media.global_actions'), ['action' => 'copy', 'destination_id' => 0, 'selected' => [['id' => (string) $first->id, 'is_folder' => false], ['id' => (string) $second->id, 'is_folder' => false]]])->assertStatus(500);
        $this->assertSame(2, MediaFile::query()->count());
        $this->assertSame([$first->url], Storage::allFiles());
        $parent = MediaFolder::query()->create(['name' => 'Parent', 'slug' => 'parent', 'parent_id' => 0, 'user_id' => $user->id]);
        $child = MediaFolder::query()->create(['name' => 'Child', 'slug' => 'child', 'parent_id' => $parent->id, 'user_id' => $user->id]);
        $this->postJson(route('media.global_actions'), ['action' => 'move', 'destination_id' => $child->id, 'selected' => [['id' => (string) $parent->id, 'is_folder' => true]]])->assertUnprocessable();
        $this->assertSame(0, $parent->fresh()->parent_id);
    }

    public function test_media_chunk_upload_assembles_one_validated_file(): void
    {
        Storage::fake('public');
        Storage::fake('local');
        config(['core.media.media.chunk.enabled' => true]);
        RvMedia::clearResolvedInstance(\Kern\Media\RvMedia::class);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $uuid = (string) Str::uuid();
        $this->withCookie(config('session.cookie'), Str::random(40));
        $this->post(route('media.files.upload'), ['file' => UploadedFile::fake()->createWithContent('chunked.txt', 'first-'), 'folder_id' => 0, 'dzuuid' => $uuid, 'dzchunkindex' => 0, 'dztotalchunkcount' => 2], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('status', true);
        $this->assertDatabaseMissing('media_files', ['name' => 'chunked']);
        $this->post(route('media.files.upload'), ['file' => UploadedFile::fake()->createWithContent('chunked.txt', 'second'), 'folder_id' => 0, 'dzuuid' => $uuid, 'dzchunkindex' => 1, 'dztotalchunkcount' => 2], ['Accept' => 'application/json'])->assertOk()->assertJsonPath('error', false);
        $file = MediaFile::query()->where('name', 'chunked')->firstOrFail();
        $this->assertSame('first-second', Storage::get($file->url));
    }

    protected function activateRipple(): void
    {
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
    }

    public function test_theme_options_include_ripple_and_cookie_consent_and_validate_saves(): void
    {
        $this->activateRipple();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $response = $this->get(route('theme.options'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Options');
        $fields = collect($response->json('props.sections'))->flatMap(fn (array $section): array => $section['fields'])->keyBy('name');
        $this->assertArrayHasKey('primary_color', $fields);
        $this->assertArrayHasKey('cookie_consent_enable', $fields);
        $this->post(route('theme.options.post'), ['primary_color' => '#123456', 'cookie_consent_enable' => 'yes'], $this->headers())->assertRedirect();
        $this->assertSame('#123456', ThemeOption::getOption('primary_color'));
        $this->post(route('theme.options.post'), ['injected_unregistered_option' => 'value'], $this->headers())->assertSessionHasErrors('options');
        $this->post(route('theme.options.post'), ['primary_color' => 'invalid-color'], $this->headers())->assertSessionHasErrors('primary_color');
    }

    public function test_unsupported_theme_option_hooks_block_page_and_write(): void
    {
        $this->activateRipple();
        app('events')->listen(RenderingThemeOptionSettings::class, fn () => ThemeOption::setField(['id' => 'legacy_component', 'section_id' => 'opt-text-subsection-general', 'type' => 'unregisteredLegacyWidget', 'label' => 'Legacy', 'attributes' => ['name' => 'legacy_component']]));
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->get(route('theme.options'), $this->headers())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
        $this->post(route('theme.options.post'), ['primary_color' => '#654321'], $this->headers())->assertStatus(503);
    }

    public function test_theme_editors_preserve_validated_storage_and_permissions(): void
    {
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->activateRipple();
        $this->withSession(['_token' => 'theme-editor-acceptance'])->withHeader('X-CSRF-TOKEN', 'theme-editor-acceptance');
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['packages.theme.general.enable_custom_js' => true, 'packages.theme.general.enable_custom_html' => true]);
        $this->get(route('theme.custom-css'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Editor');
        $this->get(route('theme.custom-js'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Editor');
        $this->get(route('theme.custom-html'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Editor');
        $this->post(route('theme.custom-js.post'), ['custom_header_js' => 'window.example = true;'], $this->headers())->assertRedirect();
        $this->assertSame('window.example = true;', setting('custom_header_js'));
        $html = [
            'custom_header_html' => '<p>Acceptance local Header HTML</p>',
            'custom_body_html' => '<p>Acceptance local Body HTML</p>',
            'custom_footer_html' => '<p>Acceptance local Footer HTML</p>',
        ];
        $this->post(route('theme.custom-html.post'), $html, $this->headers())->assertRedirect();
        $reopened = $this->get(route('theme.custom-html'), $this->headers())->assertOk();
        foreach ($html as $key => $value) {
            $this->assertSame($value, setting($key));
            $this->assertDatabaseHas('settings', ['key' => $key, 'value' => $value]);
            $this->assertSame($value, collect($reopened->json('props.fields'))->keyBy('name')[$key]['value']);
        }
        config(['core.base.admin.legacy_mode' => true]);
        $this->get(route('theme.custom-html'), $this->headers())->assertOk()->assertJsonPath('component', 'Theme/Editor');
        config(['core.base.admin.legacy_mode' => false]);
        $this->post(route('theme.custom-html.post'), ['custom_header_html' => str_repeat('x', 2501)], $this->headers())->assertSessionHasErrors('custom_header_html');
        foreach ($html as $key => $value) {
            $this->assertDatabaseHas('settings', ['key' => $key, 'value' => $value]);
        }
        $this->post(route('theme.custom-js.post'), ['custom_header_js' => str_repeat('x', 10001)], $this->headers())->assertSessionHasErrors('custom_header_js');
        $this->actingAs(UserFactory::new()->create(['permissions' => ['theme.index' => true]]));
        $this->post(route('theme.active'), ['theme' => 'ripple'], $this->headers())->assertForbidden();
        $this->post(route('theme.remove'), ['theme' => 'ripple'], $this->headers())->assertForbidden();
    }

    public function test_media_recent_includes_nested_files_without_bypassing_owner_trash_or_requested_ids(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $other = UserFactory::new()->create();
        setting()->set('user_can_only_view_own_media', '1')->save();
        $folder = MediaFolder::query()->create(['name' => 'Recent parent', 'slug' => 'recent-parent', 'parent_id' => 0, 'user_id' => $user->id]);
        $root = $this->file($user->id, 'Root recent');
        $nested = $this->file($user->id, 'Nested recent');
        $nested->update(['folder_id' => $folder->id]);
        $unrequested = $this->file($user->id, 'Unrequested recent');
        $unrequested->update(['folder_id' => $folder->id]);
        $private = $this->file($other->id, 'Other owner recent');
        $private->update(['folder_id' => $folder->id]);
        $trashed = $this->file($user->id, 'Trashed recent');
        $trashed->update(['folder_id' => $folder->id]);
        $trashed->delete();
        $this->actingAs($user);
        $recent = $this->getJson(route('media.list', ['view_in' => 'recent', 'recent_items' => [$nested->id, $root->id, $private->id, $trashed->id], 'sort_by' => 'name-asc']))->assertOk()->assertJsonPath('error', false)->assertJsonCount(0, 'data.folders');
        $this->assertSame([$nested->id, $root->id], array_column($recent->json('data.files'), 'id'));
        $this->getJson(route('media.list', ['view_in' => 'recent', 'recent_items' => []]))->assertOk()->assertJsonCount(0, 'data.files');
        $normal = $this->getJson(route('media.list', ['view_in' => 'all_media', 'folder_id' => 0]))->assertOk();
        $this->assertSame([$root->id], array_column($normal->json('data.files'), 'id'));
    }

    public function test_nested_recent_files_keep_search_type_sort_and_pagination_filters(): void
    {
        $user = UserFactory::new()->superUser()->create();
        $folder = MediaFolder::query()->create(['name' => 'Recent images', 'slug' => 'recent-images', 'parent_id' => 0, 'user_id' => $user->id]);
        $images = [];
        foreach (['A Image', 'B Image', 'C Image'] as $name) {
            $image = $this->file($user->id, $name);
            $image->update(['folder_id' => $folder->id]);
            $images[] = $image;
        }
        $video = $this->file($user->id, 'D Image video');
        $video->update(['folder_id' => $folder->id, 'mime_type' => 'video/mp4']);
        $excluded = $this->file($user->id, 'Other name');
        $excluded->update(['folder_id' => $folder->id]);
        $this->actingAs($user);
        $query = ['view_in' => 'recent', 'recent_items' => array_map(fn ($file): int => $file->id, [...$images, $video, $excluded]), 'search' => 'Image', 'filter' => 'image', 'posts_per_page' => 1, 'sort_by' => 'name-asc'];
        $this->getJson(route('media.list', [...$query, 'paged' => 1]))->assertOk()->assertJsonCount(1, 'data.files')->assertJsonPath('data.files.0.id', $images[0]->id);
        $this->getJson(route('media.list', [...$query, 'paged' => 2]))->assertOk()->assertJsonCount(1, 'data.files')->assertJsonPath('data.files.0.id', $images[1]->id);
        $this->getJson(route('media.list', [...$query, 'posts_per_page' => 30]))->assertOk()->assertJsonCount(3, 'data.files');
    }

    public function test_media_favorites_include_nested_files_and_folders_without_bypassing_owner_or_normal_folder_scope(): void
    {
        $user = UserFactory::new()->create(['permissions' => ['media.index' => true]]);
        $other = UserFactory::new()->create();
        setting()->set('user_can_only_view_own_media', '1')->save();
        $folder = MediaFolder::query()->create(['name' => 'Favorite parent', 'slug' => 'favorite-parent', 'parent_id' => 0, 'user_id' => $user->id]);
        $child = MediaFolder::query()->create(['name' => 'Favorite nested folder', 'slug' => 'favorite-nested-folder', 'parent_id' => $folder->id, 'user_id' => $user->id]);
        $otherFolder = MediaFolder::query()->create(['name' => 'Private folder', 'slug' => 'private-folder', 'parent_id' => 0, 'user_id' => $other->id]);
        $root = $this->file($user->id, 'Root favorite');
        $nested = $this->file($user->id, 'Nested favorite');
        $nested->update(['folder_id' => $folder->id]);
        $private = $this->file($other->id, 'Private favorite');
        MediaSetting::query()->create(['key' => 'favorites', 'user_id' => $user->id, 'value' => [
            ['id' => $root->id, 'is_folder' => false], ['id' => $nested->id, 'is_folder' => false], ['id' => $private->id, 'is_folder' => false],
            ['id' => $folder->id, 'is_folder' => true], ['id' => $child->id, 'is_folder' => true], ['id' => $otherFolder->id, 'is_folder' => true],
        ]]);
        $this->actingAs($user);
        $favorites = $this->getJson(route('media.list', ['view_in' => 'favorites']))->assertOk()->assertJsonPath('error', false);
        $this->assertEqualsCanonicalizing([$root->id, $nested->id], array_column($favorites->json('data.files'), 'id'));
        $this->assertEqualsCanonicalizing([$folder->id, $child->id], array_column($favorites->json('data.folders'), 'id'));
        $normal = $this->getJson(route('media.list', ['view_in' => 'all_media', 'folder_id' => 0]))->assertOk();
        $this->assertSame([$root->id], array_column($normal->json('data.files'), 'id'));
        $this->assertSame([$folder->id], array_column($normal->json('data.folders'), 'id'));
    }

    public function test_nested_media_favorites_keep_search_type_pagination_and_trash_filters(): void
    {
        $user = UserFactory::new()->superUser()->create();
        $folder = MediaFolder::query()->create(['name' => 'Nested images', 'slug' => 'nested-images', 'parent_id' => 0, 'user_id' => $user->id]);
        $images = [];
        foreach (['A Image', 'B Image', 'C Image'] as $name) {
            $image = $this->file($user->id, $name);
            $image->update(['folder_id' => $folder->id]);
            $images[] = $image;
        }
        $video = $this->file($user->id, 'D Image video');
        $video->update(['folder_id' => $folder->id, 'mime_type' => 'video/mp4']);
        $trashed = $this->file($user->id, 'E Image trashed');
        $trashed->update(['folder_id' => $folder->id]);
        $trashed->delete();
        MediaSetting::query()->create(['key' => 'favorites', 'user_id' => $user->id, 'value' => array_map(fn ($file): array => ['id' => $file->id, 'is_folder' => false], [...$images, $video, $trashed])]);
        $this->actingAs($user);
        $query = ['view_in' => 'favorites', 'search' => 'Image', 'filter' => 'image', 'posts_per_page' => 1, 'sort_by' => 'name-asc'];
        $this->getJson(route('media.list', [...$query, 'paged' => 1]))->assertOk()->assertJsonCount(1, 'data.files')->assertJsonPath('data.files.0.id', $images[0]->id);
        $this->getJson(route('media.list', [...$query, 'paged' => 2]))->assertOk()->assertJsonCount(1, 'data.files')->assertJsonPath('data.files.0.id', $images[1]->id);
        $this->getJson(route('media.list', [...$query, 'posts_per_page' => 30]))->assertOk()->assertJsonCount(3, 'data.files');
    }
}
