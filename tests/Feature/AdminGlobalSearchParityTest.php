<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Collection;
use Kern\Base\Contracts\GlobalSearchableManager;
use Kern\Base\GlobalSearch\GlobalSearchableProvider;
use Kern\Base\GlobalSearch\GlobalSearchableResult;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class AdminGlobalSearchParityTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        $this->withoutVite();
        config(['packages.installer.installer.enabled' => false, 'core.base.admin.modules.system' => 'inertia', 'core.base.admin.modules.settings' => 'inertia']);
    }

    public function test_header_search_includes_settings_system_tools_and_description_matches(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());

        $settings = $this->getJson(route('core.global-search', ['keyword' => trans('core/setting::setting.panel.general'), 'admin_json' => 1]))->assertOk()->assertJsonPath('error', false);
        $general = collect($settings->json('data'))->firstWhere('url', route('settings.general'));
        $this->assertNotNull($general);
        $this->assertNotEmpty($general['parents']);
        $this->assertTrue($general['inertia']);
        $this->assertSame(trans('core/setting::setting.panel.general_description'), $general['description']);
        $tools = $this->getJson(route('core.global-search', ['keyword' => trans('core/base::cache.cache_management_description'), 'admin_json' => 1]))->assertOk();
        $this->assertContains(route('system.cache'), array_column($tools->json('data'), 'url'));
    }

    public function test_search_hides_settings_and_tools_the_user_cannot_access(): void
    {
        $this->actingAs(UserFactory::new()->create(['permissions' => ['settings.cache' => true]]));

        $response = $this->getJson(route('core.global-search', ['keyword' => 'cache', 'admin_json' => 1]))->assertOk();
        $urls = array_column($response->json('data'), 'url');
        $this->assertContains(route('settings.cache'), $urls);
        $this->assertNotContains(route('system.cache'), $urls);
    }

    public function test_ajax_search_keeps_structured_json_when_legacy_module_flags_are_set(): void
    {
        config(['core.base.admin.modules.system' => 'blade', 'core.base.admin.modules.settings' => 'blade']);
        $this->actingAs(UserFactory::new()->superUser()->create());
        $parameters = ['keyword' => trans('core/setting::setting.panel.general')];

        $legacy = $this->getJson(route('core.global-search', $parameters))->assertOk();
        $this->assertIsArray($legacy->json('data'));
        $general = collect($legacy->json('data'))->firstWhere('url', route('settings.general'));
        $this->assertNotNull($general);
        $this->assertTrue($general['inertia']);
        $structured = $this->getJson(route('core.global-search', [...$parameters, 'admin_json' => 1]))->assertOk();
        $structuredGeneral = collect($structured->json('data'))->firstWhere('url', route('settings.general'));
        $this->assertNotNull($structuredGeneral);
        $this->assertTrue($structuredGeneral['inertia']);
    }

    public function test_search_keeps_extension_providers_and_normal_navigation_for_external_destinations(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());
        app(GlobalSearchableManager::class)->registerProvider(HeaderSearchExtensionProvider::class);

        $this->getJson(route('core.global-search', ['keyword' => 'Search extension', 'admin_json' => 1]))->assertOk()
            ->assertJsonPath('data.0.title', 'Search extension')
            ->assertJsonPath('data.0.parents', ['Extension'])
            ->assertJsonPath('data.0.description', 'Extension destination')
            ->assertJsonPath('data.0.url', 'https://example.test/docs')
            ->assertJsonPath('data.0.inertia', false)
            ->assertJsonPath('data.0.shouldOpenNewTab', true);
    }

    public function test_unknown_keyword_returns_an_empty_array_and_invalid_keyword_returns_validation_errors(): void
    {
        $this->actingAs(UserFactory::new()->superUser()->create());

        $this->getJson(route('core.global-search', ['keyword' => 'no-match-93ab576c', 'admin_json' => 1]))->assertOk()->assertJsonPath('data', []);
        $this->getJson(route('core.global-search', ['keyword' => ['invalid'], 'admin_json' => 1]))->assertUnprocessable()->assertJsonValidationErrors('keyword');
    }

    public function test_search_requires_authentication(): void
    {
        $this->getJson(route('core.global-search', ['keyword' => 'general', 'admin_json' => 1]))->assertUnauthorized();
    }
}

class HeaderSearchExtensionProvider extends GlobalSearchableProvider
{
    public function search(string $keyword): Collection
    {
        return collect([new GlobalSearchableResult(title: '<b>Search extension</b>', description: '<p>Extension destination</p>', parents: ['<b>Extension</b>'], url: 'https://example.test/docs', shouldOpenNewTab: true)]);
    }
}
