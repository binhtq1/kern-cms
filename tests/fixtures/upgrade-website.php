<?php

use App\SiteCustomization;
use GuzzleHttp\Client;
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Schema;
use Kern\ACL\Models\User;
use Kern\PluginManagement\Services\PluginService;
use Symfony\Component\Process\Process;

require getcwd().'/vendor/autoload.php';
$app = require getcwd().'/bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
set_exception_handler(function (Throwable $error): never {
    fwrite(STDERR, (string) $error."\n");
    exit(1);
});
function verifyUpgrade(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}
$paths = ['.env', 'storage/installed', 'platform/themes/magazine/layouts/default.blade.php', 'platform/plugins/site-custom/plugin.json', 'platform/plugins/site-custom/src/Providers/SiteServiceProvider.php', 'resources/views/vendor/core/base/site-probe.blade.php', 'config/site.php', 'app/SiteCustomization.php', 'storage/app/public/customer-upload.txt', 'public/themes/magazine/site-custom.css'];
if (($argv[1] ?? '') === 'seed') {
    verifyUpgrade(config('database.default') === 'sqlite' && is_file(storage_path('installed')), 'Use an installed disposable SQLite website.');
    DB::table('pages')->insert(['name' => 'Customer page', 'content' => '<p>Retained content</p>', 'status' => 'published']);
    DB::table('settings')->insert(['key' => 'customer_setting', 'value' => 'preserved']);
    Schema::create('customer_records', function ($table): void {
        $table->id();
        $table->string('value');
    });
    DB::table('customer_records')->insert(['value' => 'customer data']);
    File::append(theme_path('magazine/layouts/default.blade.php'), '<!-- customer theme customization -->');
    foreach (array_slice($paths, 3) as $path) {
        File::ensureDirectoryExists(dirname(base_path($path)));
        File::put(base_path($path), 'customer customization');
    }
    File::put(base_path('platform/plugins/site-custom/plugin.json'), json_encode(['id' => 'customer/site-custom', 'name' => 'Site customization', 'namespace' => 'Customer\\Site\\', 'provider' => 'Customer\\Site\\Providers\\SiteServiceProvider', 'version' => '1.0.0', 'minimum_core_version' => '1.0.0'], JSON_THROW_ON_ERROR));
    File::put(base_path('platform/plugins/site-custom/src/Providers/SiteServiceProvider.php'), '<?php namespace Customer\\Site\\Providers; class SiteServiceProvider extends \\Illuminate\\Support\\ServiceProvider { public function boot(): void { $this->app->instance("site.custom.loaded", true); config(["core.base.admin.supported_plugins.pages" => [...config("core.base.admin.supported_plugins.pages", []), "site-custom"]]); } }');
    File::put(config_path('site.php'), '<?php return ["custom" => "preserved"];');
    File::put(app_path('SiteCustomization.php'), '<?php namespace App; class SiteCustomization { public const VALUE = "preserved"; }');
    app(PluginService::class)->activate('site-custom');
    File::put(base_path('database/migrations/2026_10_28_235959_add_customer_upgrade_probe.php'), '<?php return new class extends \\Illuminate\\Database\\Migrations\\Migration { public function up(): void { \\Illuminate\\Support\\Facades\\Schema::table("customer_records", fn ($table) => $table->string("new_optional")->nullable()); } };');
    User::query()->where('username', 'kernadmin')->update(['password' => password_hash('Upgrade-fixture-password-2026', PASSWORD_BCRYPT)]);
    $hashes = [];
    foreach ($paths as $path) {
        $hashes[$path] = hash_file('sha256', base_path($path));
    }
    File::put(storage_path('upgrade-fixture.json'), json_encode(['hashes' => $hashes, 'user' => array_intersect_key(User::query()->where('username', 'kernadmin')->firstOrFail()->getAttributes(), array_flip(['id', 'username', 'email', 'password', 'super_user', 'permissions'])), 'migrations' => DB::table('migrations')->count()], JSON_THROW_ON_ERROR));
    echo "Seeded a customized 0.1.0 website.\n";
} else {
    $fixture = json_decode(File::get(storage_path('upgrade-fixture.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($fixture['hashes'] as $path => $hash) {
        verifyUpgrade(hash_file('sha256', base_path($path)) === $hash, 'Changed site file: '.$path);
    }
    verifyUpgrade(DB::table('pages')->where('name', 'Customer page')->value('content') === '<p>Retained content</p>', 'Customer page lost.');
    verifyUpgrade(DB::table('settings')->where('key', 'customer_setting')->value('value') === 'preserved', 'Setting lost.');
    verifyUpgrade(DB::table('customer_records')->value('value') === 'customer data', 'Custom table lost.');
    verifyUpgrade(Schema::hasColumn('customer_records', 'new_optional'), 'Pending migration did not run.');
    verifyUpgrade(array_intersect_key(User::query()->where('username', 'kernadmin')->firstOrFail()->getAttributes(), array_flip(['id', 'username', 'email', 'password', 'super_user', 'permissions'])) == $fixture['user'], 'Administrator changed.');
    verifyUpgrade(view('core/base::site-probe')->render() === 'customer customization', 'View override not loaded.');
    verifyUpgrade(config('site.custom') === 'preserved' && SiteCustomization::VALUE === 'preserved', 'Site PHP customization not loaded.');
    verifyUpgrade(app()->bound('site.custom.loaded') && is_plugin_active('site-custom'), 'Custom plugin not loaded.');
    verifyUpgrade(get_cms_version() === '0.2.0' && str_contains(core_path(), '/vendor/binhtq1/kern-core/'), 'Runtime is not loaded from Composer.');
    verifyUpgrade(! app()->isDownForMaintenance(), 'Website is still in maintenance mode.');
    $socket = stream_socket_server('tcp://127.0.0.1:0');
    $address = stream_socket_get_name($socket, false);
    fclose($socket);
    $origin = 'http://'.$address;
    $server = new Process([PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1', '--port='.parse_url($origin, PHP_URL_PORT), '--no-reload', '--no-interaction'], getcwd(), ['APP_URL' => $origin]);
    $server->start();
    try {
        $deadline = microtime(true) + 15;
        do {
            $ready = @stream_socket_client('tcp://'.$address, $code, $message, 0.1);
            if ($ready) {
                fclose($ready);
                break;
            }
            verifyUpgrade($server->isRunning() && microtime(true) < $deadline, 'Upgraded web server did not start.');
            usleep(100000);
        } while (true);
        $client = new Client(['base_uri' => $origin, 'cookies' => true]);
        $homepage = $client->get('/');
        verifyUpgrade(str_contains((string) $homepage->getBody(), 'customer theme customization'), 'Customized theme did not render.');
        $login = $client->get(parse_url(route('access.login'), PHP_URL_PATH));
        $document = new DOMDocument;
        @$document->loadHTML((string) $login->getBody());
        $token = (new DOMXPath($document))->query('//meta[@name="csrf-token"]')->item(0)->getAttribute('content');
        $response = $client->post(parse_url(route('access.login.post'), PHP_URL_PATH), ['form_params' => ['_token' => $token, 'username' => 'kernadmin', 'password' => 'Upgrade-fixture-password-2026'], 'allow_redirects' => false]);
        verifyUpgrade($response->getStatusCode() === 302 && parse_url($response->getHeaderLine('Location'), PHP_URL_PATH) === parse_url(route('dashboard.index'), PHP_URL_PATH), 'Administrator login failed after upgrade.');
        verifyUpgrade($client->get($response->getHeaderLine('Location'))->getStatusCode() === 200, 'Upgraded dashboard failed.');
    } finally {
        $server->stop();
    }
    echo "Upgrade 0.1.0 -> 0.2.0 passed: content, admin, settings, custom table, uploads, APP_KEY, theme, plugin, config and view override preserved.\n";
}
