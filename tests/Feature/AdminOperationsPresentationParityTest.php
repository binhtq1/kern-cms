<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Route;
use Illuminate\Support\Facades\Storage;
use Kern\ACL\Models\User;
use Kern\AuditLog\Models\AuditHistory;
use Kern\Base\Facades\PanelSectionManager;
use Kern\Base\PanelSections\PanelSectionItem;
use Kern\Blog\Models\Post;
use Kern\DataSynchronize\Importer\ImportColumn;
use Kern\DataSynchronize\PanelSections\ExportPanelSection;
use Kern\PluginManagement\Services\PluginService;
use Kern\RequestLog\Models\RequestLog;
use Kern\Slug\Models\Slug;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PhpOffice\PhpSpreadsheet\Spreadsheet;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminOperationsPresentationParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.operations' => 'inertia', 'core.base.admin.modules.maintenance' => 'inertia']);
        foreach (['blog', 'backup', 'audit-log', 'request-log'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    private function inertiaHeaders(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    public function test_operation_links_use_inertia_only_for_supported_local_destinations(): void
    {
        Route::get('admin/custom-export', fn () => 'Legacy export')->name('extension.custom-export');
        Route::getRoutes()->refreshNameLookups();
        $urls = [
            route('tools.data-synchronize.export.posts.index'),
            route('extension.custom-export'),
            url('admin/missing-export'),
            'https://external.example/export',
        ];
        foreach ($urls as $index => $url) {
            PanelSectionManager::default()->registerItem(
                ExportPanelSection::class,
                fn () => PanelSectionItem::make('navigation-test-'.$index)->setTitle('Navigation test '.$index)->withUrl($url),
            );
        }

        $response = $this->get(route('tools.data-synchronize'), $this->inertiaHeaders())->assertOk();

        $items = collect($response->json('props.sections'))->flatMap(fn (array $section): array => $section['items'])
            ->filter(fn (array $item): bool => str_starts_with($item['title'], 'Navigation test '))->values();
        $this->assertSame([true, false, false, false], $items->pluck('inertia')->all());
        $this->assertSame($urls, $items->pluck('url')->all());
    }

    public function test_plugin_thumbnails_have_a_versioned_asset_url(): void
    {
        $screenshot = public_path('vendor/core/plugins/analytics/screenshot.png');
        $published = File::exists($screenshot);
        if (! $published) {
            File::ensureDirectoryExists(dirname($screenshot));
            File::copy(plugin_path('analytics/screenshot.png'), $screenshot);
        }

        try {
            $response = $this->get(route('plugins.index'), $this->inertiaHeaders())->assertOk();
            $analytics = collect($response->json('props.plugins'))->firstWhere('id', 'analytics');

            $this->assertSame(
                asset('vendor/core/plugins/analytics/screenshot.png').'?v='.filemtime($screenshot),
                $analytics['image'],
            );
        } finally {
            if (! $published) {
                File::delete($screenshot);
            }
        }
    }

    public function test_import_exposes_inline_examples_chunk_bounds_and_raw_rule_fallback(): void
    {
        add_filter('data_synchronize_importer_columns', fn () => [ImportColumn::make('name')->rules(['required', 'string', 'max:250'])]);
        $this->get(route('tools.data-synchronize.import.posts.index'), $this->inertiaHeaders())->assertOk()
            ->assertJsonPath('props.chunkSize', 50)->assertJsonPath('props.maxChunkSize', 10000)
            ->assertJsonPath('props.columns.0.description', 'required, string, max:250')
            ->assertJsonStructure(['props' => ['examples' => [['name', 'status']]]]);
    }

    #[TestWith(['form_before'])]
    #[TestWith(['form_after'])]
    #[TestWith(['page_before'])]
    #[TestWith(['page_after'])]
    #[TestWith(['page_footer'])]
    public function test_import_extension_regions_block_with_compatibility_screen(string $region): void
    {
        add_filter('data_synchronize_import_'.$region, fn () => '<p>Importer extension panel</p>');
        $url = route('tools.data-synchronize.import.posts.index');
        $this->get($url, $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    public function test_import_view_override_is_blocked_with_compatibility_screen(): void
    {
        add_filter('data_synchronize_importer_view', fn () => 'packages/data-synchronize::partials.importer');
        $this->get(route('tools.data-synchronize.import.posts.index'), $this->inertiaHeaders())->assertStatus(503)->assertJsonPath('component', 'System/Compatibility');
    }

    #[TestWith([0])]
    #[TestWith([10001])]
    public function test_import_rejects_unbounded_chunk_sizes_before_reading_any_file(int $limit): void
    {
        $this->postJson(route('tools.data-synchronize.import.posts.validate'), ['file_name' => 'not-uploaded.csv', 'offset' => 0, 'limit' => $limit])->assertUnprocessable()->assertJsonValidationErrors('limit');
    }

    public function test_log_links_labels_and_backup_size_column_match_blade_information(): void
    {
        $log = RequestLog::query()->create(['url' => 'https://example.test/missing', 'status_code' => 404]);
        $response = $this->get(route('request-log.index'), $this->inertiaHeaders())->assertOk();
        $rows = collect($response->json('props.rows'))->keyBy('id');
        $this->assertSame($log->url, $rows[$log->id]['links']['url']);
        $audit = AuditHistory::query()->create(['action' => 'updated', 'module' => 'page', 'reference_name' => 'Example page', 'reference_id' => 1, 'reference_user' => 0, 'type' => 'info', 'ip_address' => '192.0.2.1', 'user_id' => auth()->id()]);
        $response = $this->get(route('audit-log.index'), $this->inertiaHeaders())->assertOk();
        $rows = collect($response->json('props.rows'))->keyBy('id');
        $this->assertSame(trans('plugins/audit-log::history.updated'), $rows[$audit->id]['values']['action']);
        $this->assertSame(trans('plugins/audit-log::history.page'), $rows[$audit->id]['values']['module']);
        $response = $this->get(route('backups.index'), $this->inertiaHeaders())->assertOk();
        $this->assertContains('size', array_column($response->json('props.columns'), 'key'));
    }

    public function test_post_csv_validation_never_creates_taxonomy_and_import_retries_keep_relations_unique(): void
    {
        Storage::fake('local');
        $csv = "name,slug,description,content,tags,categories,status,is_featured,image,format_type\n".
            'Imported acceptance post,imported-acceptance,Summary,Body,"New tag, Second tag, New tag","New category, Second category, New category",published,Yes,,'."\n";
        Storage::disk('local')->put('data-synchronize/acceptance.csv', $csv);
        $payload = ['file_name' => 'acceptance.csv', 'offset' => 0, 'limit' => 50];
        $validation = $this->postJson(route('tools.data-synchronize.import.posts.validate'), $payload)->assertOk();
        $this->assertFalse($validation->json('error'), $validation->getContent());
        $validation->assertJsonPath('data.errors', []);
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('tags', 0);
        $this->assertDatabaseCount('slugs', 0);

        $import = $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk();
        $this->assertFalse($import->json('error'), $import->getContent());
        $import->assertJsonPath('data.total', 1);
        $post = Post::query()->sole();
        $this->assertSame('Imported acceptance post', $post->name);
        $this->assertSame('imported-acceptance', $post->slugable->key);
        $this->assertEqualsCanonicalizing(['New category', 'Second category'], $post->categories->pluck('name')->all());
        $this->assertEqualsCanonicalizing(['New tag', 'Second tag'], $post->tags->pluck('name')->all());
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)
            ->assertOk()->assertJsonPath('error', false)->assertJsonPath('data.total', 0);
        $this->assertDatabaseCount('posts', 1);
        $this->assertDatabaseCount('categories', 2);
        $this->assertDatabaseCount('tags', 2);
        $this->assertSame(2, $post->categories()->count());
        $this->assertSame(2, $post->tags()->count());
    }

    public function test_post_import_cannot_bypass_validation_or_write_categories_for_an_invalid_batch(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('data-synchronize/invalid.csv', "name,categories,status,is_featured\nValid first,Must not create,published,Yes\n,Also not create,published,No\n");
        $payload = ['file_name' => 'invalid.csv', 'offset' => 0, 'limit' => 50];
        $this->postJson(route('tools.data-synchronize.import.posts.validate'), $payload)
            ->assertOk()->assertJsonPath('error', false)->assertJsonCount(1, 'data.errors');
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)
            ->assertOk()->assertJsonPath('error', true);
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('categories', 0);
        $this->assertDatabaseCount('tags', 0);
        $this->assertDatabaseCount('slugs', 0);
    }

    public function test_post_import_accepts_csv_without_optional_image_column(): void
    {
        Storage::fake('local');
        Storage::disk('local')->put('data-synchronize/minimal.csv', "name,status,is_featured\nMinimal CSV post,published,No\n");
        $response = $this->postJson(route('tools.data-synchronize.import.posts.store'), ['file_name' => 'minimal.csv', 'offset' => 0, 'limit' => 50])->assertOk();
        $this->assertFalse($response->json('error'), $response->getContent());
        $post = Post::query()->sole();
        $this->assertSame('Minimal CSV post', $post->name);
        $this->assertSame('', $post->image);
    }

    #[TestWith(['csv'])]
    #[TestWith(['xlsx'])]
    public function test_post_export_download_contains_selected_columns_and_utf8_data(string $format): void
    {
        Http::preventStrayRequests();
        $post = Post::query()->create(['name' => 'Bài viết Việt, kiểm thử', 'description' => 'Mô tả tiếng Việt', 'content' => '<p>Nội dung</p>', 'status' => 'draft', 'author_id' => auth()->id(), 'author_type' => User::class]);
        Slug::query()->create(['key' => 'bai-viet-viet', 'reference_id' => $post->id, 'reference_type' => Post::class, 'prefix' => '']);
        $payload = ['format' => $format, 'columns' => ['name', 'description', 'status']];
        $response = $this->post(route('tools.data-synchronize.export.posts.store'), $payload)->assertOk();
        $response->assertDownload();
        $path = $response->baseResponse->getFile()->getPathname();
        $this->assertGreaterThan(0, filesize($path));
        $reader = IOFactory::createReader(ucfirst($format));
        $workbook = $reader->load($path);
        try {
            $rows = $workbook->getActiveSheet()->toArray();
            $this->assertSame(['Name', 'Description', 'Status'], $rows[0]);
            $this->assertSame([$post->name, $post->description, 'draft'], $rows[1]);
            $this->assertCount(2, $rows);
        } finally {
            $workbook->disconnectWorksheets();
            unlink($path);
        }
        $this->actingAs(UserFactory::new()->create(['permissions' => ['tools.data-synchronize' => true]]));
        $this->postJson(route('tools.data-synchronize.export.posts.store'), $payload)->assertUnauthorized();
        $this->assertDatabaseCount('posts', 1);
        Http::assertNothingSent();
    }

    #[TestWith(['csv'])]
    #[TestWith(['xls'])]
    #[TestWith(['xlsx'])]
    public function test_post_import_uploads_and_imports_each_advertised_format_once(string $format): void
    {
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('data-synchronize');
        $fileName = $this->uploadPostWorkbook($format, [
            ['Name', 'Slug', 'Description', 'Status', 'Is Featured'],
            ['Bài viết '.$format, 'acceptance-'.$format, 'Mô tả tiếng Việt', 'draft', 'No'],
        ]);
        $payload = ['file_name' => $fileName, 'offset' => 0, 'limit' => 1];
        $validation = $this->postJson(route('tools.data-synchronize.import.posts.validate'), $payload)->assertOk();
        $this->assertFalse($validation->json('error'), $validation->getContent());
        $validation->assertJsonPath('data.errors', [])->assertJsonPath('data.total', 1)->assertJsonPath('data.count', 1);
        $this->assertDatabaseCount('posts', 0);
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk()
            ->assertJsonPath('error', false)->assertJsonPath('data.total', 1);
        $post = Post::query()->sole();
        $this->assertSame('Bài viết '.$format, $post->name);
        $this->assertSame('Mô tả tiếng Việt', $post->description);
        $this->assertSame('draft', $post->status->getValue());
        $this->assertSame('acceptance-'.$format, $post->slugable->key);
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk()->assertJsonPath('data.total', 0);
        $this->assertDatabaseCount('posts', 1);
        $this->actingAs(UserFactory::new()->create());
        $this->postJson(route('tools.data-synchronize.import.posts.validate'), $payload)->assertUnauthorized();
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertUnauthorized();
        $this->assertDatabaseCount('posts', 1);
        Http::assertNothingSent();
    }

    #[TestWith(['csv'])]
    #[TestWith(['xls'])]
    #[TestWith(['xlsx'])]
    public function test_uploaded_workbooks_validate_atomically_and_honor_row_offsets(string $format): void
    {
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('data-synchronize');
        $fileName = $this->uploadPostWorkbook($format, [
            ['Name', 'Slug', 'Status', 'Is Featured'],
            ['First draft', 'first-draft', 'draft', 'No'],
            ...($format === 'xls' ? [[]] : []),
            ['', 'invalid-draft', 'draft', 'No'],
            ['Last draft', 'last-draft', 'draft', 'No'],
        ]);
        $payload = ['file_name' => $fileName, 'offset' => 0, 'limit' => 50];
        $this->postJson(route('tools.data-synchronize.import.posts.validate'), $payload)->assertOk()
            ->assertJsonPath('error', false)->assertJsonCount(1, 'data.errors');
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk()->assertJsonPath('error', true);
        $this->assertDatabaseCount('posts', 0);
        $this->assertDatabaseCount('slugs', 0);
        $this->postJson(route('tools.data-synchronize.import.posts.validate'), [...$payload, 'offset' => 2, 'limit' => 1])->assertOk()
            ->assertJsonPath('data.total', 3)->assertJsonPath('data.count', 1)->assertJsonPath('data.errors', []);
        $this->postJson(route('tools.data-synchronize.import.posts.store'), [...$payload, 'offset' => 2, 'limit' => 1])->assertOk()
            ->assertJsonPath('error', false)->assertJsonPath('data.total', 1);
        $this->assertSame('Last draft', Post::query()->sole()->name);
        $this->assertDatabaseCount('slugs', 1);
        Http::assertNothingSent();
    }

    public function test_legacy_excel_formulas_are_not_evaluated_during_validation_or_import(): void
    {
        Http::preventStrayRequests();
        Storage::fake('local');
        Storage::disk('local')->makeDirectory('data-synchronize');
        $fileName = $this->uploadPostWorkbook('xls', [
            ['Name', 'Status'],
            ['="Do not calculate this post name"', 'draft'],
        ]);
        $payload = ['file_name' => $fileName, 'offset' => 0, 'limit' => 50];
        $this->postJson(route('tools.data-synchronize.import.posts.validate'), $payload)->assertOk()
            ->assertJsonPath('error', false)->assertJsonCount(1, 'data.errors');
        $this->postJson(route('tools.data-synchronize.import.posts.store'), $payload)->assertOk()->assertJsonPath('error', true);
        $this->assertDatabaseCount('posts', 0);
        Http::assertNothingSent();
    }

    private function uploadPostWorkbook(string $format, array $rows): string
    {
        $path = Storage::disk('local')->path('fixture.'.$format);
        $workbook = new Spreadsheet;
        $workbook->getActiveSheet()->fromArray($rows);
        IOFactory::createWriter($workbook, ucfirst($format))->setPreCalculateFormulas(false)->save($path);
        $workbook->disconnectWorksheets();
        $upload = $this->postJson(route('data-synchronize.upload'), ['file' => new UploadedFile($path, 'posts.'.$format, null, null, true)])
            ->assertOk()->assertJsonPath('error', false);
        $fileName = $upload->json('data.file_name');
        Storage::disk('local')->assertExists('data-synchronize/'.$fileName);

        return $fileName;
    }
}
