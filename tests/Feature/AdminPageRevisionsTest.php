<?php

namespace Tests\Feature;

use Database\Factories\PageFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Page\Supports\AdminPageRevisionData;
use Tests\TestCase;

class AdminPageRevisionsTest extends TestCase
{
    use RefreshDatabase;

    public function test_history_returns_the_changed_values_and_author_for_only_the_requested_page(): void
    {
        $user = UserFactory::new()->create(['first_name' => 'Review', 'last_name' => 'Author']);
        $this->actingAs($user);
        $page = PageFactory::new()->create(['name' => 'Original page']);
        $otherPage = PageFactory::new()->create(['name' => 'Other page']);
        $page->update(['name' => 'Updated page']);
        $otherPage->update(['name' => 'Unrelated change']);

        $history = app(AdminPageRevisionData::class)->forPage($page);

        $this->assertCount(1, $history);
        $this->assertSame('name', $history[0]['field']);
        $this->assertSame('Name', $history[0]['label']);
        $this->assertSame('Original page', $history[0]['old_value']);
        $this->assertSame('Updated page', $history[0]['new_value']);
        $this->assertSame('Review Author', $history[0]['author']);
        $this->assertIsString($history[0]['id']);
        $this->assertIsString($history[0]['created_at']);
    }

    public function test_history_remains_readable_after_the_author_is_deleted(): void
    {
        $user = UserFactory::new()->create();
        $this->actingAs($user);
        $page = PageFactory::new()->create(['name' => 'Original page']);
        $page->update(['name' => 'Updated page']);
        $user->delete();

        $history = app(AdminPageRevisionData::class)->forPage($page);

        $this->assertCount(1, $history);
        $this->assertNull($history[0]['author']);
        $this->assertSame('Updated page', $history[0]['new_value']);
    }

    public function test_disabling_revision_display_hides_existing_history(): void
    {
        $page = PageFactory::new()->create(['name' => 'Original page']);
        $page->update(['name' => 'Updated page']);
        config(['packages.revision.general.supported' => []]);

        $this->assertSame([], app(AdminPageRevisionData::class)->forPage($page));
    }
}
