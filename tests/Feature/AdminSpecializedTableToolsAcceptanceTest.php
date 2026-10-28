<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Kern\Base\Supports\AdminRegisteredTableData;
use Kern\CustomField\Models\FieldGroup;
use Kern\PluginManagement\Services\PluginService;
use Kern\RequestLog\Models\RequestLog;
use PhpOffice\PhpSpreadsheet\IOFactory;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\Attributes\TestWith;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminSpecializedTableToolsAcceptanceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false]);
        foreach (['audit-log', 'request-log', 'custom-field'] as $plugin) {
            if (! is_plugin_active($plugin)) {
                $this->assertFalse(app(PluginService::class)->activate($plugin)['error']);
            }
        }
        $this->actingAs(UserFactory::new()->superUser()->create());
    }

    protected function headers(): array
    {
        return ['X-Inertia' => 'true', 'X-Requested-With' => 'XMLHttpRequest', 'Accept' => 'text/html', 'X-Inertia-Version' => is_file(public_path('build/admin/manifest.json')) ? hash_file('sha256', public_path('build/admin/manifest.json')) : ''];
    }

    #[TestWith(['users.index', 'users'])]
    #[TestWith(['roles.index', 'roles'])]
    #[TestWith(['menus.index', 'menus'])]
    #[TestWith(['custom-fields.index', 'field-groups'])]
    #[TestWith(['api.sanctum-token.index', 'api-tokens'])]
    #[TestWith(['audit-log.index', 'audit-logs'])]
    #[TestWith(['request-log.index', 'request-logs'])]
    public function test_specialized_lists_follow_registered_table_buttons_and_saved_visibility(string $route, string $module): void
    {
        setting()->set(['datatables_default_show_export_button' => '0', 'datatables_default_show_column_visibility' => '0'])->save();
        $this->get(route($route), $this->headers())->assertOk()->assertJsonPath('props.tableButtons', ['reload']);
        $this->getJson(route($route, ['_export' => 'csv']))->assertForbidden();
        $this->getJson(route($route, ['_export' => 'csv', 'search' => ['invalid']]))->assertUnprocessable()->assertJsonValidationErrors('search');
        setting()->set(['datatables_default_show_export_button' => '1', 'datatables_default_show_column_visibility' => '1'])->save();
        $table = app(AdminRegisteredTableData::class)->table($module)::class;
        $this->putJson(route('table.update-columns-visibility'), ['table' => $table, 'columns_visibility' => ['created_at' => 0]])->assertOk()->assertJsonPath('error', false);
        $this->actingAs(auth()->user()->fresh());
        $this->get(route($route), $this->headers())->assertOk()
            ->assertJsonPath('props.tableButtons', ['reload', 'export', 'visibility'])
            ->assertJsonPath('props.tableVisibility.table', $table)
            ->assertJsonPath('props.tableVisibility.columns.created_at', 0);
    }

    public function test_user_and_request_log_exports_keep_search_scope_and_never_export_credentials(): void
    {
        setting()->set('datatables_default_show_export_button', '1')->save();
        UserFactory::new()->create(['username' => 'table-match', 'email' => 'matched@example.test']);
        UserFactory::new()->create(['username' => 'table-excluded', 'email' => 'excluded@example.test']);
        RequestLog::query()->create(['url' => 'https://example.test/matched-page', 'status_code' => 404, 'count' => 2]);
        RequestLog::query()->create(['url' => 'https://example.test/excluded-page', 'status_code' => 500, 'count' => 1]);
        foreach ([['users.index', 'table-match', 'matched@example.test', 'excluded@example.test'], ['request-log.index', 'matched-page', 'matched-page', 'excluded-page']] as [$route, $search, $included, $excluded]) {
            foreach (['csv', 'xlsx'] as $format) {
                $response = $this->getJson(route($route, ['search' => $search, '_export' => $format]))->assertOk()->assertHeader('content-disposition');
                $this->assertInstanceOf(BinaryFileResponse::class, $response->baseResponse);
                $text = json_encode(IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray());
                $this->assertStringContainsString($included, $text);
                $this->assertStringNotContainsString($excluded, $text);
                $this->assertStringNotContainsString(auth()->user()->password, $text);
            }
        }
    }

    public function test_field_groups_expose_per_row_json_export_independently_from_table_export(): void
    {
        $first = FieldGroup::query()->create(['title' => 'First group', 'status' => 'published', 'order' => 0, 'rules' => '[]']);
        FieldGroup::query()->create(['title' => 'Second group', 'status' => 'published', 'order' => 1, 'rules' => '[]']);
        $response = $this->get(route('custom-fields.index', ['search' => 'First']), $this->headers())->assertOk();
        $response->assertJsonPath('props.items.data.0.export_url', route('custom-fields.export', $first));
        $this->actingAs(UserFactory::new()->create(['permissions' => ['custom-fields.index' => true]]));
        $this->get(route('custom-fields.index', ['search' => 'First']), $this->headers())->assertOk()->assertJsonPath('props.permissions.edit', false)->assertJsonPath('props.items.data.0.export_url', route('custom-fields.export', $first));
        $export = $this->get(route('custom-fields.export', $first))->assertOk()->assertHeader('content-disposition');
        $this->assertStringContainsString('First group', $export->getContent());
        $this->assertStringNotContainsString('Second group', $export->getContent());
        setting()->set('datatables_default_show_export_button', '1')->save();
        $this->getJson(route('custom-fields.index', ['search' => 'First', '_export' => 'csv']))->assertOk()->assertHeader('content-disposition');
    }

    public function test_token_export_contains_metadata_only_and_export_stays_permission_scoped(): void
    {
        setting()->set('datatables_default_show_export_button', '1')->save();
        $token = auth()->user()->createToken('Acceptance metadata token');
        $response = $this->getJson(route('api.sanctum-token.index', ['search' => 'Acceptance metadata', '_export' => 'csv']))->assertOk()->assertHeader('content-disposition');
        $text = json_encode(IOFactory::load($response->baseResponse->getFile()->getPathname())->getActiveSheet()->toArray());
        $this->assertStringContainsString('Acceptance metadata token', $text);
        $this->assertStringNotContainsString($token->plainTextToken, $text);
        $this->assertStringNotContainsString($token->accessToken->token, $text);
        $this->actingAs(UserFactory::new()->create(['permissions' => []]));
        $this->getJson(route('api.sanctum-token.index', ['_export' => 'csv']))->assertUnauthorized();
        $this->getJson(route('users.index', ['_export' => 'csv']))->assertUnauthorized();
    }
}
