<?php

namespace Tests\Feature;

use Database\Factories\ContactFactory;
use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\PluginManagement\Services\PluginService;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminContactExportParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.contacts' => 'inertia']);
        if (! is_plugin_active('contact')) {
            $this->assertFalse(app(PluginService::class)->activate('contact')['error']);
        }
    }

    public function test_contact_exports_use_existing_table_csv_excel_and_current_list_filters(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['contacts.index' => true]]));
        ContactFactory::new()->create(['name' => 'Export match', 'email' => 'matched@example.test', 'status' => 'read']);
        ContactFactory::new()->create(['name' => 'Export excluded unread', 'email' => 'unread@example.test', 'status' => 'unread']);
        ContactFactory::new()->create(['name' => 'Another sender', 'email' => 'other@example.test', 'status' => 'read']);
        foreach (['csv', 'xlsx'] as $format) {
            $response = $this->post(route('contacts.export'), ['format' => $format, 'search' => 'Export', 'status' => 'read'])->assertOk();
            $response->assertHeader('content-disposition');
            $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
            $path = $response->baseResponse->getFile()->getPathname();
            $sheet = IOFactory::load($path)->getActiveSheet()->toArray();
            $text = json_encode($sheet);
            $this->assertStringContainsString('matched@example.test', $text);
            $this->assertStringNotContainsString('unread@example.test', $text);
            $this->assertStringNotContainsString('other@example.test', $text);
        }
    }

    public function test_exports_preserve_permission_boundary_and_validate_format_and_status(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));
        $this->postJson(route('contacts.export'), ['format' => 'csv'])->assertUnauthorized();
        $this->actingAs(UserFactory::new()->create(['permissions' => ['contacts.index' => true]]));
        $this->postJson(route('contacts.export'), ['format' => 'html', 'status' => 'invalid'])->assertUnprocessable()->assertJsonValidationErrors(['format', 'status']);
    }
}
