<?php

namespace Tests\Feature;

use Database\Factories\ContactFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\ACL\Models\User;
use Kern\Blog\Models\Category;
use Kern\Blog\Models\Post;
use Kern\Gallery\Models\Gallery;
use Kern\Member\Models\Member;
use Kern\PluginManagement\Services\PluginService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminContentListParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        foreach (['blog', 'gallery', 'member', 'contact'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                app(PluginService::class)->activate($plugin);
            }
        }
        foreach (['posts', 'galleries', 'members', 'contacts'] as $module) {
            config(['core.base.admin.modules.'.$module => 'inertia']);
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => hash_file('sha256', public_path('build/admin/manifest.json'))];
    }

    public function test_posts_restore_image_categories_and_author_columns(): void
    {
        $category = Category::query()->create(['name' => 'Editorial', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $post = Post::query()->create(['name' => 'Story', 'status' => 'published', 'image' => 'story.jpg', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $post->categories()->attach($category);

        $response = $this->get(route('posts.index'), $this->headers())->assertOk()
            ->assertJsonPath('props.items.data.0.author', auth()->user()->name)
            ->assertJsonPath('props.items.data.0.categories.0.name', 'Editorial')
            ->assertJsonPath('props.items.data.0.categories.0.url', route('categories.edit', $category));
        $this->assertSame(['id', 'image', 'name', 'categories', 'author', 'created_at', 'status'], array_column($response->json('props.columns'), 'key'));
        $this->assertStringContainsString('story', $response->json('props.items.data.0.image'));
    }

    public function test_content_tables_share_registered_tools_and_export_the_filtered_dataset(): void
    {
        setting()->set(['datatables_default_show_export_button' => false, 'datatables_default_show_column_visibility' => false])->save();
        $this->get(route('posts.index'), $this->headers())->assertOk()->assertJsonPath('props.tableButtons', ['reload']);
        $this->get(route('posts.index', ['_export' => 'csv']))->assertForbidden();
        setting()->set(['datatables_default_show_export_button' => true, 'datatables_default_show_column_visibility' => true])->save();
        Post::query()->create(['name' => 'Bài viết cần xuất', 'status' => 'published', 'author_id' => auth()->id(), 'author_type' => User::class]);
        Post::query()->create(['name' => 'Excluded draft', 'status' => 'draft', 'author_id' => auth()->id(), 'author_type' => User::class]);
        $this->get(route('posts.index'), $this->headers())->assertOk()->assertJsonPath('props.tableButtons', ['reload', 'export', 'visibility']);
        foreach (['csv', 'xlsx'] as $format) {
            $response = $this->get(route('posts.index', ['_export' => $format, 'status' => 'published']))->assertOk()->assertHeader('content-disposition');
            $text = json_encode(IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray(), JSON_UNESCAPED_UNICODE);
            $this->assertStringContainsString('Bài viết cần xuất', $text);
            $this->assertStringNotContainsString('Excluded draft', $text);
        }
    }

    public function test_gallery_order_and_contact_phone_are_visible_and_sortable(): void
    {
        Gallery::query()->create(['name' => 'Later', 'description' => '', 'status' => 'published', 'order' => 9, 'image' => 'later.jpg']);
        Gallery::query()->create(['name' => 'First', 'description' => '', 'status' => 'published', 'order' => 1, 'image' => 'first.jpg']);
        ContactFactory::new()->create(['name' => 'Caller', 'phone' => '0987654321']);

        $this->get(route('galleries.index', ['sort' => 'order', 'direction' => 'asc']), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.name', 'First')->assertJsonPath('props.items.data.0.order', 1);
        $this->get(route('contacts.index', ['sort' => 'phone']), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.phone', '0987654321');
        $this->get(route('contacts.index', ['sort' => 'order']), $this->headers())->assertRedirect()->assertSessionHasErrors('sort');
    }

    public function test_members_restore_avatar_and_email_verification_only_when_enabled(): void
    {
        $member = Member::query()->create(['first_name' => 'Reader', 'last_name' => 'One', 'email' => 'reader@example.test', 'password' => 'unused']);
        $member->forceFill(['confirmed_at' => now()])->save();
        setting()->set('verify_account_email', false)->save();

        $response = $this->get(route('member.index'), $this->headers())->assertOk()->assertJsonPath('props.items.data.0.confirmed_at', true);
        $this->assertNotEmpty($response->json('props.items.data.0.image'));
        $this->assertSame(['id', 'image', 'name', 'email', 'created_at'], array_column($response->json('props.columns'), 'key'));
        setting()->set('verify_account_email', true)->save();
        $response = $this->get(route('member.index', ['sort' => 'confirmed_at']), $this->headers())->assertOk();
        $this->assertContains('confirmed_at', array_column($response->json('props.columns'), 'key'));
    }

    public function test_member_search_matches_last_name_and_full_name_without_returning_unrelated_members(): void
    {
        $member = Member::query()->create(['first_name' => 'Reader', 'last_name' => 'One', 'email' => 'reader@example.test', 'password' => 'unused']);
        Member::query()->create(['first_name' => 'Another', 'last_name' => 'Person', 'email' => 'another@example.test', 'password' => 'unused']);

        foreach (['One', 'Reader One', 'reader one'] as $search) {
            $this->get(route('member.index', ['search' => $search]), $this->headers())->assertOk()
                ->assertJsonPath('props.items.total', 1)
                ->assertJsonPath('props.items.data.0.id', (string) $member->id);
        }
        $this->get(route('member.index', ['search' => 'Missing reader']), $this->headers())->assertOk()->assertJsonPath('props.items.total', 0);
    }
}
