<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Password;
use Kern\ACL\Notifications\ResetPasswordNotification;
use Kern\AuditLog\Models\AuditHistory;
use Kern\PluginManagement\Services\PluginService;
use Kern\RequestLog\Models\RequestLog;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminRemainingAuthLogAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    private string $fixtureRoot;

    private array $originalPaths;

    protected function setUp(): void
    {
        parent::setUp();
        $this->assertUsesTestingDatabase();
        $this->originalPaths = ['storage' => storage_path(), 'public' => public_path(), 'lang' => lang_path()];
        $this->fixtureRoot = sys_get_temp_dir().'/kern-auth-log-'.bin2hex(random_bytes(8));
        foreach (['storage/framework/views', 'storage/framework/cache', 'storage/logs', 'public/build', 'lang/en', 'lang/vi'] as $folder) {
            File::ensureDirectoryExists($this->fixtureRoot.'/'.$folder);
        }
        File::copyDirectory(public_path('build/admin'), $this->fixtureRoot.'/public/build/admin');
        $this->app->useStoragePath($this->fixtureRoot.'/storage');
        $this->app->usePublicPath($this->fixtureRoot.'/public');
        $this->app->useLangPath($this->fixtureRoot.'/lang');
        config(['packages.installer.installer.enabled' => false, 'view.compiled' => storage_path('framework/views')]);
        $this->withoutVite();
        foreach (['audit-log', 'request-log'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
    }

    protected function tearDown(): void
    {
        if (isset($this->fixtureRoot)) {
            $this->app->useStoragePath($this->originalPaths['storage']);
            $this->app->usePublicPath($this->originalPaths['public']);
            $this->app->useLangPath($this->originalPaths['lang']);
            File::deleteDirectory($this->fixtureRoot);
        }
        parent::tearDown();
    }

    private function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    public function test_reset_link_throttle_preserves_token_and_notification_count_until_retry_window(): void
    {
        Notification::fake();
        $user = UserFactory::new()->superUser()->create(['email' => 'reset-throttle@example.test']);
        $url = route('access.password.request');
        $this->withCookie(config('session.cookie'), session()->getId());
        $this->from($url)->post(route('access.password.email'), ['email' => $user->email], $this->headers())
            ->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success_msg');
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
        $table = config('auth.passwords.users.table');
        $before = (array) DB::table($table)->where('email', $user->email)->sole();
        $this->from($url)->post(route('access.password.email'), ['email' => $user->email], $this->headers())
            ->assertRedirect($url)->assertSessionHasErrors(['email' => trans(Password::RESET_THROTTLED)]);
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 1);
        $this->assertSame($before, (array) DB::table($table)->where('email', $user->email)->sole());
        $this->assertSame($user->email, session('_old_input.email'));
        $page = $this->get($url, $this->headers())->assertOk();
        $page->assertJsonPath('props.errors.email', trans(Password::RESET_THROTTLED));
        $this->travel(config('auth.passwords.users.throttle') + 1)->seconds();
        $this->from($url)->post(route('access.password.email'), ['email' => $user->email], $this->headers())
            ->assertRedirect($url)->assertSessionHasNoErrors()->assertSessionHas('success_msg');
        Notification::assertSentToTimes($user, ResetPasswordNotification::class, 2);
        $this->assertNotSame($before['token'], DB::table($table)->where('email', $user->email)->value('token'));
        $this->assertGuest();
    }

    private function logFixture(string $module): AuditHistory|RequestLog
    {
        return $module === 'audit-log'
            ? AuditHistory::query()->create(['action' => 'updated', 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'Sensitive reference fixture', 'ip_address' => '192.0.2.213', 'user_agent' => 'Sensitive user-agent fixture', 'user_id' => UserFactory::new()->create()->id])
            : RequestLog::query()->create(['url' => 'https://example.test/private-request-fixture?token=fixture-secret', 'status_code' => 404, 'count' => 7]);
    }

    #[TestWith(['audit-log'])]
    #[TestWith(['request-log'])]
    public function test_non_demo_single_log_deletion_binds_and_removes_only_selected_record(string $module): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        config(['core.base.general.demo_mode_enabled' => false]);
        $first = $this->logFixture($module);
        $second = $this->logFixture($module);
        $response = $this->deleteJson(route($module.'.destroy', $first->id))->assertOk();
        $this->assertModelMissing($first);
        $this->assertModelExists($second);
        $response->assertJsonPath('error', false);
    }

    #[TestWith(['audit-log', 'destroy'])]
    #[TestWith(['audit-log', 'bulk-destroy'])]
    #[TestWith(['audit-log', 'empty'])]
    #[TestWith(['request-log', 'destroy'])]
    #[TestWith(['request-log', 'bulk-destroy'])]
    #[TestWith(['request-log', 'empty'])]
    public function test_demo_mode_denies_all_log_deletion_branches_without_removing_records(string $module, string $action): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $first = $this->logFixture($module);
        $second = $this->logFixture($module);
        $baseline = $first->newQuery()->whereKey([$first->id, $second->id])->orderBy('id')->get()->toArray();
        config(['core.base.general.demo_mode_enabled' => true]);
        $response = $this->deleteJson(route($module.'.'.$action, $action === 'destroy' ? $first->id : []), $action === 'bulk-destroy' ? ['ids' => [$first->id]] : []);
        $this->assertSame($baseline, $first->newQuery()->whereKey([$first->id, $second->id])->orderBy('id')->get()->toArray(), $module.'.'.$action.' modified logs in demo mode.');
        $response->assertStatus(403)->assertJsonPath('error', true)->assertJsonPath('message', 'Bad request.');
        $this->assertModelExists($first);
        $this->assertModelExists($second);
    }

    #[TestWith(['audit-log', 'audit-log.widget.activities'])]
    #[TestWith(['request-log', 'request-log.widget.request-errors'])]
    public function test_log_sensitive_rows_require_index_permission_for_lists_and_widget_feeds(string $module, string $widget): void
    {
        $record = $this->logFixture($module);
        $sensitive = $module === 'audit-log' ? ['Sensitive reference fixture', '192.0.2.213', 'Sensitive user-agent fixture'] : ['private-request-fixture', 'fixture-secret'];
        foreach ([$module.'.index', $widget] as $route) {
            $guest = $this->getJson(route($route, ['admin_json' => 1]))->assertUnauthorized();
            foreach ($sensitive as $value) {
                $guest->assertDontSee($value);
            }
        }
        $this->actingAs(UserFactory::new()->create(['permissions' => ['pages.index' => true]]));
        foreach ([$module.'.index', $widget] as $route) {
            $restricted = $this->getJson(route($route, ['admin_json' => 1]))->assertUnauthorized();
            foreach ($sensitive as $value) {
                $restricted->assertDontSee($value);
            }
        }
        $this->actingAs(UserFactory::new()->create(['permissions' => [$module.'.index' => true]]));
        $this->get(route($module.'.index'), $this->headers())->assertOk()->assertJsonPath('props.rows.0.id', $record->id);
        $feed = $this->getJson(route($widget, ['admin_json' => 1]))->assertOk()->assertJsonPath('error', false);
        $this->assertNotEmpty($feed->json('data.records'));
    }

    #[TestWith(['audit-log'])]
    #[TestWith(['request-log'])]
    public function test_log_tables_sort_only_legacy_columns_and_preserve_filtered_pagination(string $module): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        $ids = [];
        for ($index = 0; $index < 3; $index++) {
            $record = $module === 'audit-log'
                ? AuditHistory::query()->create(['action' => ['updated', 'created', 'created'][$index], 'module' => 'page', 'type' => 'info', 'reference_user' => 0, 'reference_id' => 1, 'reference_name' => 'Acceptance sortable', 'ip_address' => '192.0.2.1', 'user_agent' => 'Fixture', 'user_id' => auth()->id()])
                : RequestLog::query()->create(['url' => 'https://example.test/Acceptance sortable/'.['c', 'a', 'b'][$index], 'status_code' => [500, 404, 404][$index], 'count' => [2, 3, 1][$index]]);
            if ($module === 'request-log') {
                $record->forceFill(['count' => [2, 3, 1][$index]])->save();
            }
            $ids[] = $record->id;
        }
        $orders = $module === 'audit-log'
            ? ['id' => [[0, 1, 2], [2, 1, 0]], 'action' => [[2, 1, 0], [0, 2, 1]]]
            : ['id' => [[0, 1, 2], [2, 1, 0]], 'url' => [[1, 2, 0], [0, 2, 1]], 'status_code' => [[2, 1, 0], [0, 2, 1]], 'count' => [[2, 0, 1], [1, 0, 2]]];
        foreach ($orders as $sort => $directions) {
            foreach (['asc', 'desc'] as $index => $direction) {
                $response = $this->get(route($module.'.index', ['sort' => $sort, 'direction' => $direction, 'search' => 'Acceptance sortable', 'per_page' => 2]), $this->headers())->assertOk();
                $this->assertSame(array_map(fn (int $offset): int => $ids[$offset], array_slice($directions[$index], 0, 2)), array_column($response->json('props.rows'), 'id'));
                $response->assertJsonPath('props.tableFilters.sort', $sort)->assertJsonPath('props.tableFilters.direction', $direction)->assertJsonPath('props.pagination.total', 3);
                $next = $response->json('props.pagination.next');
                parse_str(parse_url($next, PHP_URL_QUERY), $parameters);
                $this->assertSame(['sort' => $sort, 'direction' => $direction, 'search' => 'Acceptance sortable', 'per_page' => '2', 'page' => '2'], $parameters);
                $this->get($next, $this->headers())->assertOk()->assertJsonPath('props.rows.0.id', $ids[$directions[$index][2]]);
                $sortable = collect($response->json('props.columns'))->filter(fn (array $column): bool => $column['sortable'] ?? false)->pluck('key')->all();
                $this->assertSame(array_keys($orders), $sortable);
            }
        }
        foreach ([['sort' => 'id desc; drop table users'], ['sort' => 'created_at'], ['direction' => 'sideways'], ['per_page' => 101], ['page' => 0]] as $invalid) {
            $this->get(route($module.'.index', $invalid), $this->headers())->assertSessionHasErrors(array_key_first($invalid));
        }
        $this->assertDatabaseCount($module === 'audit-log' ? 'audit_histories' : 'request_logs', 3);
    }
}
