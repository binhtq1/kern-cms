<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\Facades\Validator;
use Kern\ACL\Models\User;
use Kern\Installer\Http\Requests\SaveEnvironmentRequest;
use Kern\Installer\Supports\EnvironmentManager;
use PHPUnit\Framework\Attributes\TestWith;
use Tests\TestCase;

class InstallerTest extends TestCase
{
    use RefreshDatabase;

    private string $directory;

    protected function setUp(): void
    {
        parent::setUp();
        $this->directory = sys_get_temp_dir().'/kern-install-'.uniqid();
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

    public function test_a_database_with_no_administrator_still_opens_the_installer(): void
    {
        $this->get('/')->assertRedirect(route('installers.welcome'));
        $this->get('/install/welcome')->assertOk()->assertSee('Kern');
        $this->get(URL::temporarySignedRoute('installers.requirements.index', now()->addMinutes(30)))
            ->assertOk();
        $this->get(URL::temporarySignedRoute('installers.environments.index', now()->addMinutes(30)))
            ->assertOk()->assertSee('pgsql')->assertSee('sqlite');
    }

    public function test_an_administrator_can_be_created_and_installation_finished(): void
    {
        File::put($this->directory.'/installing', now()->toDateTimeString());
        $this->get(URL::temporarySignedRoute('installers.accounts.index', now()->addMinutes(30)))
            ->assertOk();
        $response = $this->post(URL::temporarySignedRoute('installers.accounts.store', now()->addMinutes(30)), [
            'first_name' => 'Kern',
            'last_name' => 'Admin',
            'username' => 'kernadmin',
            'email' => 'admin@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ]);
        $response->assertSessionHasNoErrors()->assertRedirect();
        $user = User::query()->where('username', 'kernadmin')->firstOrFail();
        $this->assertTrue($user->activated);
        $this->assertAuthenticatedAs($user);
        $this->get($response->headers->get('Location'))->assertOk();
        $this->assertFileExists($this->directory.'/installed');
        $this->assertFileDoesNotExist($this->directory.'/installing');
        $this->get('/install/welcome')->assertRedirect('/');
    }

    public function test_unsigned_account_creation_is_rejected(): void
    {
        File::put($this->directory.'/installing', now()->toDateTimeString());
        $this->post('/install/accounts', [])->assertForbidden();
        $this->assertDatabaseCount('users', 0);
    }

    public function test_an_expired_installation_session_cannot_create_an_account(): void
    {
        File::put($this->directory.'/installing', now()->subMinutes(31)->toDateTimeString());
        $this->post(URL::temporarySignedRoute('installers.accounts.store', now()->addMinutes(30)), [])
            ->assertRedirect('/');
        $this->assertDatabaseCount('users', 0);
    }

    public function test_environment_save_preserves_existing_keys_and_quotes_credentials(): void
    {
        $this->app->useEnvironmentPath($this->directory);
        File::put($this->directory.'/.env', "APP_KEY=existing-key\nREDIS_HOST=redis\nCUSTOM_SETTING=keep\nAPP_NAME=Old\n");
        $request = Request::create('/', 'POST', [
            'app_name' => 'Kern CMS',
            'app_url' => 'http://localhost:8000',
            'database_connection' => 'pgsql',
            'database_hostname' => 'postgres',
            'database_port' => '5432',
            'database_name' => 'kern',
            'database_username' => 'kern',
            'database_password' => 'quote" dollar${APP_KEY} slash\\ hash#',
        ]);

        app(EnvironmentManager::class)->save($request);
        $values = Dotenv::createArrayBacked($this->directory)->load();
        $this->assertSame('existing-key', $values['APP_KEY']);
        $this->assertSame('redis', $values['REDIS_HOST']);
        $this->assertSame('keep', $values['CUSTOM_SETTING']);
        $this->assertSame('Kern CMS', $values['APP_NAME']);
        $this->assertSame('pgsql', $values['DB_CONNECTION']);
        $this->assertSame('quote" dollar${APP_KEY} slash\\ hash#', $values['DB_PASSWORD']);
    }

    public function test_cli_install_is_repeatable_and_preserves_existing_records(): void
    {
        User::query()->create(['first_name' => 'Existing', 'username' => 'existing', 'email' => 'existing@example.test', 'password' => 'password123']);
        $this->artisan('cms:install', ['--force' => true, '--no-interaction' => true])->assertSuccessful();
        $this->assertDatabaseHas('users', ['username' => 'existing']);
        $this->assertFileExists($this->directory.'/installed');
    }

    #[TestWith(['sqlite', 61, true])]
    #[TestWith(['sqlite', 4096, true])]
    #[TestWith(['sqlite', 4097, false])]
    #[TestWith(['mysql', 60, true])]
    #[TestWith(['mysql', 61, false])]
    #[TestWith(['pgsql', 60, true])]
    #[TestWith(['pgsql', 61, false])]
    public function test_database_name_limits_follow_the_selected_driver(string $driver, int $length, bool $passes): void
    {
        $values = [
            'app_name' => 'Kern CMS',
            'app_url' => 'http://localhost:8000',
            'database_connection' => $driver,
            'database_name' => str_repeat('d', $length),
            'database_hostname' => '127.0.0.1',
            'database_port' => '5432',
            'database_username' => 'kern',
        ];
        $request = SaveEnvironmentRequest::create('/', 'POST', $values);
        $validator = Validator::make($values, $request->rules());

        $this->assertSame($passes, $validator->passes());
        $this->assertSame(! $passes, $validator->errors()->has('database_name'));
    }
}
