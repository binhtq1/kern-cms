<?php

namespace Tests\Feature;

use Dotenv\Dotenv;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\URL;
use Kern\ACL\Models\User;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use Tests\TestCase;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class InstallerWizardTest extends TestCase
{
    private string $directory;

    private string $databasePath;

    private string $originalPublicPath;

    protected function setUp(): void
    {
        parent::setUp();

        $this->directory = sys_get_temp_dir().'/kern-wizard-with-a-long-installation-directory-'.bin2hex(random_bytes(8));
        $this->databasePath = $this->directory.'/database/database.sqlite';
        $this->originalPublicPath = public_path();
        foreach (['database', 'bootstrap/cache', 'storage/framework/cache/data', 'storage/framework/sessions', 'storage/framework/views', 'storage/logs'] as $path) {
            File::ensureDirectoryExists($this->directory.'/'.$path);
        }
        File::put($this->databasePath, '');
        File::put($this->directory.'/.env', "APP_NAME=\"Kern CMS\"\nAPP_KEY=".config('app.key')."\nDB_CONNECTION=sqlite\n");
        File::copyDirectory($this->originalPublicPath.'/build/admin', $this->directory.'/public/build/admin');

        $this->app->useEnvironmentPath($this->directory);
        $this->app->useStoragePath($this->directory.'/storage');
        $this->app->useBootstrapPath($this->directory.'/bootstrap');
        $this->app->usePublicPath($this->directory.'/public');
        config([
            'packages.installer.installer.enabled' => true,
            'database.default' => 'sqlite',
            'database.connections.sqlite.database' => $this->databasePath,
            'view.compiled' => $this->directory.'/storage/framework/views',
        ]);
        DB::purge('sqlite');
    }

    protected function tearDown(): void
    {
        DB::disconnect('sqlite');
        $this->app->usePublicPath($this->originalPublicPath);
        File::deleteDirectory($this->directory);

        parent::tearDown();
    }

    public function test_the_complete_wizard_installs_sqlite_without_host_credentials_and_creates_a_working_administrator(): void
    {
        $this->assertGreaterThan(60, strlen($this->databasePath));
        $this->assertSame([], DB::connection()->getSchemaBuilder()->getTables());
        $this->get('/')->assertRedirect(route('installers.welcome'));
        $this->get(route('installers.welcome'))->assertOk();

        $requirements = $this->post(route('installers.welcome.next'), ['language' => 'en'])
            ->assertRedirectContains('/install/requirements');
        $this->get($requirements->headers->get('Location'))->assertOk();
        $this->get(URL::temporarySignedRoute('installers.environments.index', now()->addMinutes(30)))
            ->assertOk()->assertSee($this->databasePath);

        $themes = $this->post(URL::temporarySignedRoute('installers.environments.store', now()->addMinutes(30)), [
            'app_name' => 'Kern CMS',
            'app_url' => 'http://localhost',
            'database_connection' => 'sqlite',
            'database_name' => $this->databasePath,
        ])->assertSessionHasNoErrors()->assertRedirectContains('/install/themes');
        $this->assertFileExists(storage_path('installing'));
        $this->get($themes->headers->get('Location'))->assertOk();

        $accounts = $this->post(URL::temporarySignedRoute('installers.themes.store', now()->addMinutes(30)), ['theme' => 'magazine'])
            ->assertSessionHasNoErrors()->assertRedirectContains('/install/accounts');
        $this->get($accounts->headers->get('Location'))->assertOk();
        $finished = $this->post(URL::temporarySignedRoute('installers.accounts.store', now()->addMinutes(30)), [
            'first_name' => 'Kern',
            'last_name' => 'Admin',
            'username' => 'kernadmin',
            'email' => 'admin@example.test',
            'password' => 'password123',
            'password_confirmation' => 'password123',
        ])->assertSessionHasNoErrors()->assertRedirectContains('/install/final');
        $this->get($finished->headers->get('Location'))->assertOk();

        $this->assertFileExists(storage_path('installed'));
        $this->assertFileDoesNotExist(storage_path('installing'));
        $values = Dotenv::createArrayBacked($this->directory)->load();
        $this->assertSame('sqlite', $values['DB_CONNECTION']);
        $this->assertSame($this->databasePath, $values['DB_DATABASE']);
        $this->assertSame(config('app.key'), $values['APP_KEY']);
        $this->assertDatabaseHas('settings', ['key' => 'theme', 'value' => 'magazine']);
        $user = User::query()->where('username', 'kernadmin')->firstOrFail();
        $this->assertTrue($user->isSuperUser());
        $this->assertTrue($user->activated);
        $this->get(route('installers.welcome'))->assertRedirect('/');

        $this->get(route('access.logout'))->assertRedirect(route('access.login'));
        $this->get(route('access.login'))->assertOk();
        $this->post(route('access.login.post'), ['username' => 'kernadmin', 'password' => 'password123'])
            ->assertSessionHasNoErrors()->assertRedirect(route('dashboard.index'));
        $this->assertAuthenticatedAs($user);
        $this->get(route('dashboard.index'))->assertOk();
    }
}
