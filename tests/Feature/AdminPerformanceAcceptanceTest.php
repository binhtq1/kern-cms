<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Route;
use Illuminate\Testing\TestResponse;
use Kern\ACL\Models\User;
use Kern\Base\Supports\Action;
use Kern\Base\Supports\Filter;
use Kern\Blog\Models\Post;
use Kern\Language\LanguageManager;
use Kern\Language\Models\Language;
use Kern\Language\Providers\LanguageServiceProvider;
use Kern\Media\Models\MediaFile;
use Kern\Menu\Models\Menu;
use Kern\Menu\Models\MenuNode;
use Kern\PluginManagement\Services\PluginService;
use Kern\Slug\Models\Slug;
use Kern\Theme\Providers\RouteServiceProvider;
use Kern\Theme\Providers\ThemeManagementServiceProvider;
use Kern\Theme\Services\ThemeService;
use Kern\Widget\Models\Widget;
use Kern\Widget\Widgets\Text;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminPerformanceAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private Action $bootActions;

    private Filter $bootFilters;

    private const SQL_GROWTH_BUDGET = 3;

    private const SHARED_NAVIGATION_ALLOWANCE = 10;

    private const LIST_JSON_BYTES = 262144;

    private const STATIC_JS_GZIP_BYTES = 1048576;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.general.google_fonts_enabled' => false]);
        (new \ReflectionProperty($this->app, 'isRunningInConsole'))->setValue($this->app, false);
        foreach (get_installed_plugins() as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error'], $plugin);
            }
        }
        $this->app->register(LanguageServiceProvider::class, true);
        Language::query()->create(['lang_name' => 'English', 'lang_code' => 'en_US', 'lang_locale' => 'en', 'lang_flag' => 'us', 'lang_order' => 1, 'lang_is_default' => true]);
        Language::query()->create(['lang_name' => 'Vietnamese', 'lang_code' => 'vi', 'lang_locale' => 'vi', 'lang_flag' => 'vn', 'lang_order' => 2, 'lang_is_default' => false]);
        $this->assertFalse(app(ThemeService::class)->activate('ripple')['error']);
        $this->app->register(ThemeManagementServiceProvider::class, true);
        $this->app->register(RouteServiceProvider::class, true);
        Route::getRoutes()->refreshNameLookups();
        $this->actingAs(UserFactory::new()->superUser()->create());
        $this->bootActions = clone \Kern\Base\Facades\Action::getFacadeRoot();
        $this->bootFilters = clone \Kern\Base\Facades\Filter::getFacadeRoot();
    }

    /** Restore request-local hook registrations as a fresh PHP worker would. */
    private function freshRequest(callable $request): TestResponse
    {
        \Kern\Base\Facades\Action::swap(clone $this->bootActions);
        \Kern\Base\Facades\Filter::swap(clone $this->bootFilters);
        \Kern\Language\Facades\Language::swap(new LanguageManager);

        return $request();
    }

    private function environment(): array
    {
        return ['php' => PHP_VERSION, 'laravel' => app()->version(), 'database' => DB::getDriverName().':'.DB::getDatabaseName(), 'active_plugins' => get_active_plugins(), 'theme' => Widget::getThemeName(), 'warmups' => 1, 'samples' => 3, 'request_hook_isolation' => 'boot listener snapshots and fresh language manager restored before each HTTP request'];
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    /** @return array{queries: int, bytes: int, median_ms: float, requests: int, sql_counts: array<string, int>} */
    private function measure(callable $operation): array
    {
        $operation();
        $samples = [];
        DB::enableQueryLog();
        try {
            for ($iteration = 0; $iteration < 3; $iteration++) {
                DB::flushQueryLog();
                $started = hrtime(true);
                $responses = $operation();
                $samples[] = ['queries' => count(DB::getQueryLog()), 'bytes' => array_sum(array_map(fn ($response): int => strlen($response->getContent()), $responses)), 'median_ms' => (hrtime(true) - $started) / 1e6, 'requests' => count($responses), 'sql_counts' => array_count_values(array_column(DB::getQueryLog(), 'query'))];
            }
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        usort($samples, fn (array $a, array $b): int => $a['median_ms'] <=> $b['median_ms']);

        return $samples[1];
    }

    #[TestWith(['pages'])]
    #[TestWith(['posts'])]
    public function test_list_edit_and_search_query_growth_stays_bounded_for_initial_and_inertia_visits(string $module): void
    {
        $metrics = [];
        $first = null;
        foreach ([5, 30] as $size) {
            for ($index = $size === 5 ? 1 : 6; $index <= $size; $index++) {
                $record = $module === 'pages'
                    ? PageFactory::new()->create(['name' => 'Acceptance '.$index, 'content' => '<p>Acceptance content</p>'])
                    : Post::query()->create(['name' => 'Acceptance '.$index, 'content' => '<p>Acceptance content</p>', 'status' => 'draft', 'author_id' => auth()->id(), 'author_type' => User::class]);
                Slug::query()->create(['key' => 'acceptance-'.$index, 'reference_id' => $record->id, 'reference_type' => $record::class, 'prefix' => $module]);
                $first ??= $record;
            }
            foreach (['list', 'search', 'edit'] as $operation) {
                foreach ([true, false] as $legacy) {
                    config(['core.base.admin.legacy_mode' => $legacy]);
                    $metrics[$size][$operation][$legacy ? 'blade' : 'react'] = $this->measure(function () use ($legacy, $operation, $module, $first, $size): array {
                        if ($operation === 'edit') {
                            return [$this->freshRequest(fn () => $this->get(route($module.'.edit', $first), $legacy ? [] : $this->headers()))->assertOk()];
                        }
                        if ($legacy) {
                            $html = $this->freshRequest(fn () => $this->get(route($module.'.index')))->assertOk();
                            $rows = $this->freshRequest(fn () => $this->getJson(route($module.'.index', ['per_page' => 30, 'search' => $operation === 'search' ? 'Acceptance' : '']), $this->headers()))->assertOk()->assertJsonCount($size, $module === 'pages' ? 'props.pages.data' : 'props.items.data');

                            return [$html, $rows];
                        }
                        $response = $this->freshRequest(fn () => $this->get(route($module.'.index', ['per_page' => 30, 'search' => $operation === 'search' ? 'Acceptance' : '']), $this->headers()))->assertOk();
                        $response->assertJsonCount($size, $module === 'pages' ? 'props.pages.data' : 'props.items.data');
                        $this->assertLessThanOrEqual(self::LIST_JSON_BYTES, strlen($response->getContent()));

                        return [$response];
                    });
                }
            }
        }
        file_put_contents(sys_get_temp_dir().'/kern-admin-performance-metrics.jsonl', json_encode(['scope' => $module, 'environment' => $this->environment(), 'metrics' => $metrics])."\n", FILE_APPEND);
        $failures = [];
        foreach (['list', 'search', 'edit'] as $operation) {
            foreach (['blade', 'react'] as $renderer) {
                $growth = $metrics[30][$operation][$renderer]['queries'] - $metrics[5][$operation][$renderer]['queries'];
                if ($growth > self::SQL_GROWTH_BUDGET) {
                    $failures[] = "$module $operation $renderer SQL growth $growth > 3";
                }
            }
            if ($metrics[30][$operation]['react']['queries'] > $metrics[30][$operation]['blade']['queries'] + self::SHARED_NAVIGATION_ALLOWANCE) {
                $failures[] = "$module $operation React equivalent SQL exceeded";
            }
        }
        $this->assertSame([], $failures);

    }

    public function test_media_menu_and_widget_query_growth_stays_bounded_against_blade(): void
    {
        $menu = Menu::query()->create(['name' => 'Acceptance menu', 'status' => 'published']);
        register_widget(Text::class);
        $metrics = [];
        foreach ([5, 30] as $size) {
            for ($index = $size === 5 ? 1 : 6; $index <= $size; $index++) {
                MediaFile::query()->create(['name' => 'Acceptance '.$index, 'url' => 'acceptance-'.$index.'.txt', 'mime_type' => 'text/plain', 'visibility' => 'public', 'user_id' => auth()->id(), 'size' => 10, 'folder_id' => 0]);
                MenuNode::query()->create(['menu_id' => $menu->id, 'parent_id' => 0, 'title' => 'Acceptance '.$index, 'url' => '/acceptance-'.$index, 'position' => $index]);
                Widget::query()->create(['widget_id' => Text::class, 'sidebar_id' => 'primary_sidebar', 'position' => $index, 'theme' => Widget::getThemeName(), 'data' => ['id' => Text::class, 'name' => 'Acceptance '.$index, 'content' => 'Acceptance body']]);
            }
            foreach (['media', 'menu', 'widget'] as $scope) {
                foreach ([true, false] as $legacy) {
                    config(['core.base.admin.legacy_mode' => $legacy]);
                    $metrics[$size][$scope][$legacy ? 'blade' : 'react'] = $this->measure(function () use ($legacy, $scope, $menu, $size): array {
                        $url = match ($scope) {
                            'media' => route('media.index'), 'menu' => route('menus.edit', $menu), 'widget' => route('widgets.index')
                        };
                        $response = $this->freshRequest(fn () => $this->get($url, $legacy ? [] : $this->headers()))->assertOk();
                        if ($scope === 'media') {
                            $data = $this->freshRequest(fn () => $this->getJson(route('media.list', ['view_in' => 'all_media', 'folder_id' => 0, 'posts_per_page' => 30])))->assertOk()->assertJsonCount($size, 'data.files');
                            $this->assertLessThanOrEqual(self::LIST_JSON_BYTES, strlen($data->getContent()));

                            return [$response, $data];
                        }

                        return [$response];
                    });
                }
            }
        }
        file_put_contents(sys_get_temp_dir().'/kern-admin-performance-metrics.jsonl', json_encode(['scope' => 'media-menu-widget', 'environment' => $this->environment(), 'metrics' => $metrics])."\n", FILE_APPEND);
        foreach (['media', 'menu', 'widget'] as $scope) {
            foreach (['blade', 'react'] as $renderer) {
                $this->assertLessThanOrEqual(self::SQL_GROWTH_BUDGET, $metrics[30][$scope][$renderer]['queries'] - $metrics[5][$scope][$renderer]['queries'], "$scope $renderer SQL growth");
            }
            $this->assertLessThanOrEqual($metrics[30][$scope]['blade']['queries'] + self::SHARED_NAVIGATION_ALLOWANCE, $metrics[30][$scope]['react']['queries'], "$scope React equivalent SQL");
        }
    }

    public function test_blade_nested_menu_batches_language_relations_without_changing_public_links(): void
    {
        config(['core.base.admin.legacy_mode' => true]);
        $menu = Menu::query()->create(['name' => 'Nested acceptance menu', 'status' => 'published']);
        $parent = MenuNode::query()->create(['menu_id' => $menu->id, 'parent_id' => 0, 'title' => 'Acceptance parent', 'url' => '/acceptance-parent', 'has_child' => true, 'position' => 1]);
        foreach (range(1, 25) as $index) {
            MenuNode::query()->create(['menu_id' => $menu->id, 'parent_id' => $parent->id, 'title' => 'Acceptance child '.$index, 'url' => '/acceptance-child-'.$index, 'target' => '_blank', 'position' => $index]);
        }
        DB::enableQueryLog();
        try {
            $this->freshRequest(fn () => $this->get(route('menus.edit', $menu)))->assertOk()->assertSee('Acceptance child 25');
            $languageQueries = array_filter(DB::getQueryLog(), fn (array $query): bool => str_contains($query['query'], 'from "language_meta"') && in_array(MenuNode::class, $query['bindings'], true));
        } finally {
            DB::disableQueryLog();
            DB::flushQueryLog();
        }
        $this->assertLessThanOrEqual(2, count($languageQueries), 'Root nodes and child nodes each use at most one language relation query.');

        $this->app->instance('request', Request::create('/acceptance-public-menu'));
        $public = (new \Kern\Menu\Menu(app('config')))->generateMenu(['menu' => $menu->fresh()]);
        $this->assertStringContainsString('Acceptance parent', $public);
        $this->assertStringContainsString('Acceptance child 25', $public);
        $this->assertStringContainsString('href="'.url('/acceptance-child-25').'"', $public);
        $this->assertStringContainsString('target="_blank"', $public);
        $this->assertStringNotContainsString('dd-item', $public);
    }

    public function test_static_entry_javascript_gzip_stays_within_the_declared_budget(): void
    {
        $root = public_path('build/admin');
        $manifest = json_decode(file_get_contents($root.'/manifest.json'), true, flags: JSON_THROW_ON_ERROR);
        $entry = collect($manifest)->first(fn (array $item): bool => $item['isEntry'] ?? false);
        $seen = [];
        $staticBytes = 0;
        $visit = function (array $item) use (&$visit, &$seen, &$staticBytes, $manifest, $root): void {
            if (isset($seen[$item['file']])) {
                return;
            }
            $seen[$item['file']] = true;
            $staticBytes += strlen(gzencode(file_get_contents($root.'/'.$item['file']), 9));
            foreach ($item['imports'] ?? [] as $import) {
                $visit($manifest[$import]);
            }
        };
        $visit($entry);
        $lazy = [];
        foreach ($manifest as $key => $item) {
            if (! isset($seen[$item['file']]) && str_ends_with($item['file'], '.js')) {
                $lazy[$key] = strlen(gzencode(file_get_contents($root.'/'.$item['file']), 9));
            }
        }
        file_put_contents(sys_get_temp_dir().'/kern-admin-performance-metrics.jsonl', json_encode(['scope' => 'bundle', 'environment' => $this->environment(), 'manifest_sha256' => hash_file('sha256', $root.'/manifest.json'), 'static_js_gzip_bytes' => $staticBytes, 'static_files' => array_keys($seen), 'lazy_js_gzip_bytes' => $lazy])."\n", FILE_APPEND);
        $this->assertLessThanOrEqual(self::STATIC_JS_GZIP_BYTES, $staticBytes);
    }
}
