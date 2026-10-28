<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\AuditLog\Models\AuditHistory;
use Kern\Blog\Models\Post;
use Kern\PluginManagement\Services\PluginService;
use Kern\RequestLog\Models\RequestLog;
use Kern\Slug\Models\Slug;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminDashboardWidgetParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        config(['packages.installer.installer.enabled' => false]);
        $this->withoutVite();
        foreach (['blog', 'audit-log', 'request-log'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    public function test_activity_widget_keeps_actor_details_and_local_pagination(): void
    {
        $actor = UserFactory::new()->create(['first_name' => 'Other', 'last_name' => 'Editor']);
        for ($index = 0; $index < 12; $index++) {
            AuditHistory::query()->forceCreate(['user_id' => $actor->id, 'module' => 'post', 'action' => 'created', 'reference_user' => $actor->id, 'reference_id' => $index + 1, 'reference_name' => 'Article '.$index, 'type' => 'info', 'ip_address' => '192.0.2.1', 'created_at' => now()->addSeconds($index)]);
        }
        $response = $this->getJson(route('audit-log.widget.activities', ['admin_json' => 1]))->assertOk()
            ->assertJsonCount(10, 'data.records')->assertJsonPath('data.pagination.total', 12)
            ->assertJsonPath('data.pagination.last_page', 2)->assertJsonPath('data.records.0.user', 'Other Editor')
            ->assertJsonPath('data.records.0.reference_name', 'Article 11')->assertJsonPath('data.records.0.ip_address', '192.0.2.1');
        $this->assertNotEmpty($response->json('data.records.0.avatar'));
        $this->assertNotEmpty($response->json('data.records.0.action'));
        $this->assertNotEmpty($response->json('data.records.0.module'));
        $this->assertNotEmpty($response->json('data.records.0.relative_time'));
        $this->assertArrayNotHasKey('url', $response->json('data.records.0'));
        $this->assertStringContainsString('admin_json=1', $response->json('data.pagination.next'));
        $this->getJson($response->json('data.pagination.next'))->assertOk()->assertJsonCount(2, 'data.records')->assertJsonPath('data.pagination.current_page', 2);
    }

    public function test_request_widget_keeps_direct_links_status_columns_and_pagination(): void
    {
        for ($index = 0; $index < 12; $index++) {
            RequestLog::query()->forceCreate(['url' => 'https://example.test/missing-'.$index, 'status_code' => 404, 'created_at' => now()->addSeconds($index)]);
        }
        $response = $this->getJson(route('request-log.widget.request-errors', ['admin_json' => 1]))->assertOk()
            ->assertJsonCount(10, 'data.records')->assertJsonPath('data.records.0.url', 'https://example.test/missing-11')
            ->assertJsonPath('data.records.0.status_code', 404)->assertJsonPath('data.records.0.target', '_blank')
            ->assertJsonPath('data.columns.2.key', 'status_code')->assertJsonPath('data.pagination.total', 12);
        $this->getJson($response->json('data.pagination.next'))->assertOk()->assertJsonCount(2, 'data.records')->assertJsonPath('data.pagination.current_page', 2);
        RequestLog::query()->forceCreate(['url' => 'javascript:alert(1)', 'status_code' => 404, 'created_at' => now()->addMinutes(1)]);
        $this->getJson(route('request-log.widget.request-errors', ['admin_json' => 1]))->assertOk()->assertJsonPath('data.records.0.url', null);
    }

    public function test_recent_posts_link_public_permalink_only_when_slug_exists(): void
    {
        $post = Post::query()->create(['name' => 'Public article', 'status' => 'published']);
        Slug::query()->create(['key' => 'public-article', 'prefix' => '', 'reference_type' => Post::class, 'reference_id' => $post->id]);
        $withoutSlug = Post::query()->create(['name' => 'No slug article', 'status' => 'draft']);
        $response = $this->getJson(route('posts.widget.recent-posts', ['admin_json' => 1]))->assertOk()->assertJsonCount(2, 'data.records');
        $records = collect($response->json('data.records'))->keyBy('title');
        $this->assertSame($post->fresh()->url, $records['Public article']['url']);
        $this->assertSame('_blank', $records['Public article']['target']);
        $this->assertNull($records[$withoutSlug->name]['url']);
        $this->assertStringNotContainsString('/admin/posts/', $records['Public article']['url']);
    }

    public function test_widget_endpoints_require_permissions(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));
        foreach (['audit-log.widget.activities', 'request-log.widget.request-errors', 'posts.widget.recent-posts'] as $route) {
            $this->getJson(route($route, ['admin_json' => 1]))->assertUnauthorized();
        }
    }
}
