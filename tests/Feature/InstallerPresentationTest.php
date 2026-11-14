<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InstallerPresentationTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kern-installer-ui-'.uniqid();
        File::ensureDirectoryExists($this->directory.'/framework/cache/data');
        File::ensureDirectoryExists($this->directory.'/logs');
        $this->app->useStoragePath($this->directory);
        config(['packages.installer.installer.enabled' => true]);
    }

    protected function tearDown(): void
    {
        File::deleteDirectory($this->directory);
        parent::tearDown();
    }

    public function test_welcome_has_an_accessible_stepper_and_the_original_language_submission(): void
    {
        $this->get('/install/welcome')
            ->assertOk()
            ->assertSee('aria-current="step"', false)
            ->assertSee('Skip to content')
            ->assertSee('Step 1 of 6')
            ->assertSee('name="language"', false)
            ->assertSee('name="_token"', false)
            ->assertSee(route('installers.welcome.next'), false)
            ->assertSee('data-pending-message', false)
            ->assertDontSee('jquery', false);
    }

    public function test_environment_keeps_all_fields_and_a_signed_action_without_exposing_a_password(): void
    {
        config(['database.connections.sqlite.password' => 'private-config-password']);
        $response = $this->get(URL::temporarySignedRoute('installers.environments.index', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('Step 3 of 6')
            ->assertSee('name="_token"', false)
            ->assertDontSee('private-config-password');

        foreach (['app_name', 'app_url', 'database_connection', 'database_hostname', 'database_port', 'database_name', 'database_username', 'database_password'] as $field) {
            $response->assertSee('name="'.$field.'"', false);
        }

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $form = $document->getElementById('environment-form');
        $request = Request::create($form->getAttribute('action'));
        $this->assertTrue(URL::hasValidSignature($request));
        $this->assertSame('/install/environments', $request->getPathInfo());
        $this->assertSame('post', $form->getAttribute('method'));
    }

    #[TestWith(['sqlite', 4096])]
    #[TestWith(['mysql', 60])]
    public function test_database_field_limit_matches_the_selected_driver(string $driver, int $maximum): void
    {
        $response = $this->withSession(['_old_input' => ['database_connection' => $driver]])
            ->get(URL::temporarySignedRoute('installers.environments.index', now()->addMinutes(30)))
            ->assertOk();

        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $this->assertSame((string) $maximum, $document->getElementById('database_name')->getAttribute('maxlength'));
        $connection = $document->getElementById('database_connection');
        $this->assertSame('60', $connection->getAttribute('data-database-name-maxlength'));
        $this->assertSame('4096', $connection->getAttribute('data-sqlite-path-maxlength'));
    }

    public function test_invalid_account_values_have_linked_inline_errors_and_passwords_are_not_repopulated(): void
    {
        File::put($this->directory.'/installing', now()->toDateTimeString());
        $url = URL::temporarySignedRoute('installers.accounts.index', now()->addMinutes(30));
        $this->from($url)->post(URL::temporarySignedRoute('installers.accounts.store', now()->addMinutes(30)), [
            'first_name' => 'Existing name',
            'last_name' => 'Admin',
            'username' => 'websiteadmin',
            'email' => 'invalid-email',
            'password' => 'old-private-password',
            'password_confirmation' => 'old-private-password',
        ])->assertSessionHasErrors('email');
        $this->withCookie(config('session.cookie'), session()->getId())->get($url)
            ->assertOk()
            ->assertSee('data-error-summary', false)
            ->assertSee('href="#email"', false)
            ->assertSee('id="email-error"', false)
            ->assertSee('aria-invalid="true"', false)
            ->assertSee('value="Existing name"', false)
            ->assertSee('autocomplete="new-password"', false)
            ->assertSee('data-password-toggle="password"', false)
            ->assertDontSee('old-private-password');
    }

    public function test_theme_choices_remain_native_radio_buttons_with_the_same_default(): void
    {
        File::put($this->directory.'/installing', now()->toDateTimeString());
        $this->get(URL::temporarySignedRoute('installers.themes.index', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('id="choose-theme-form"', false)
            ->assertSee('type="radio" name="theme"', false)
            ->assertSee('checked', false)
            ->assertSee('sample content')
            ->assertSee('data-pending-message', false);
    }

    public function test_failed_requirements_cannot_expose_a_continue_link(): void
    {
        config(['packages.installer.installer.requirements.php' => ['kern_missing_test_extension']]);
        $this->get(URL::temporarySignedRoute('installers.requirements.index', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('Missing')
            ->assertSee('Check again')
            ->assertSee('type="button" disabled', false)
            ->assertDontSee('/install/environments');
    }

    public function test_new_interface_copy_is_available_in_vietnamese(): void
    {
        app()->setLocale('vi');
        $this->get('/install/welcome')
            ->assertOk()
            ->assertSee('Chào mừng đến với Kern CMS')
            ->assertSee('Bước 1 / 6')
            ->assertSee('Bắt đầu thiết lập');
    }

    public function test_form_steps_have_an_explicit_signed_back_action_and_unique_password_labels(): void
    {
        File::put($this->directory.'/installing', now()->toDateTimeString());
        $response = $this->get(URL::temporarySignedRoute('installers.accounts.index', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('Show password for Password')
            ->assertSee('Show password for Confirm password')
            ->assertSee('data-field-label-text', false);
        $document = new \DOMDocument;
        @$document->loadHTML($response->getContent());
        $back = (new \DOMXPath($document))->query('//a[contains(@class, "is-back")]')->item(0);
        $this->assertNotNull($back);
        $request = Request::create($back->getAttribute('href'));
        $this->assertSame('/install/themes', $request->getPathInfo());
        $this->assertTrue(URL::hasValidSignature($request));
    }

    public function test_requirements_show_group_totals_and_pending_feedback_for_rechecking(): void
    {
        config(['packages.installer.installer.requirements' => ['php' => ['pdo']]]);
        $this->get(URL::temporarySignedRoute('installers.requirements.index', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('2 of 2 passed')
            ->assertSee('data-navigation-message', false)
            ->assertSee('Checking your server requirements.');
    }

    public function test_vietnamese_account_fields_and_completion_are_fully_localized(): void
    {
        app()->setLocale('vi');
        File::put($this->directory.'/installing', now()->toDateTimeString());
        $this->get(URL::temporarySignedRoute('installers.accounts.index', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('Địa chỉ email')
            ->assertSee('Xác nhận mật khẩu')
            ->assertSee('Hiện mật khẩu cho Mật khẩu')
            ->assertSee('Quay lại')
            ->assertDontSee('packages/installer::ui.');
        $this->get(URL::temporarySignedRoute('installers.final', now()->addMinutes(30)))
            ->assertOk()
            ->assertSee('Thiết lập hoàn tất')
            ->assertSee('Mở trang quản trị')
            ->assertDontSee('class="installer-button is-back"', false);
    }
}
