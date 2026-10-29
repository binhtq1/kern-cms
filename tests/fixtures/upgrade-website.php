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
function verifyLocalScripts(Client $client, string $html): void
{
    verifyUpgrade(! preg_match('/<script[^>]+src=[\'\"][^\'\"]*jquery/i', $html), 'The upgraded page still loads the jQuery runtime.');
    $document = new DOMDocument;
    @$document->loadHTML($html);
    foreach ((new DOMXPath($document))->query('//script[@src]') as $script) {
        $path = parse_url($script->getAttribute('src'), PHP_URL_PATH);
        if (! is_string($path) || ! preg_match('~^/(?:vendor/core/|themes/|build/)~', $path)) {
            continue;
        }
        $response = $client->get($path);
        verifyUpgrade($response->getStatusCode() === 200, 'Upgraded script is missing: '.$path);
        verifyUpgrade(! preg_match('/jQuery (?:JavaScript Library|v\d)|jquery\.org\/license/', (string) $response->getBody()), 'The upgraded page downloaded the jQuery library.');
    }
}
$paths = ['.env', 'storage/installed', 'platform/themes/magazine/layouts/default.blade.php', 'platform/plugins/site-custom/plugin.json', 'platform/plugins/site-custom/src/Providers/SiteServiceProvider.php', 'resources/views/vendor/core/base/site-probe.blade.php', 'config/site.php', 'app/SiteCustomization.php', 'storage/app/public/customer-upload.txt', 'public/themes/magazine/site-custom.css'];
$mode = $argv[1] ?? '';
$expectedVersion = $argv[2] ?? '0.2.0';
if ($mode === 'seed') {
    verifyUpgrade(config('database.default') === 'sqlite' && is_file(storage_path('installed')), 'Use an installed disposable SQLite website.');
    verifyUpgrade(is_plugin_active('contact'), 'Activate the legacy contact plugin before capturing and seeding the upgrade fixture.');
    verifyUpgrade((bool) preg_match('/\$\s*\(|jQuery\s*\(/', File::get(plugin_path('contact/resources/js/contact-public.js'))), 'The upgrade fixture must include an old frontend module that actually requires jQuery.');
    DB::table('pages')->insert(['name' => 'Customer page', 'content' => '<p>Retained content</p>', 'status' => 'published']);
    DB::table('settings')->insert(['key' => 'customer_setting', 'value' => 'preserved']);
    Schema::create('customer_records', function ($table): void {
        $table->id();
        $table->string('value');
    });
    DB::table('customer_records')->insert(['value' => 'customer data']);
    $memberId = DB::table('members')->insertGetId(['first_name' => 'Existing', 'last_name' => 'Member', 'email' => 'existing-member@example.test', 'password' => password_hash('Existing-member-fixture-2026', PASSWORD_BCRYPT), 'confirmed_at' => now(), 'description' => 'Customer member profile']);
    $memberPostId = DB::table('posts')->insertGetId(['name' => 'Existing member post', 'content' => '<p>Customer member content</p>', 'author_id' => $memberId, 'author_type' => 'Kern\\Member\\Models\\Member', 'status' => 'draft']);
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
    File::put(storage_path('upgrade-fixture.json'), json_encode(['hashes' => $hashes, 'runtime_path' => core_path(), 'runtime_version' => get_cms_version(), 'member' => (array) DB::table('members')->where('id', $memberId)->first(), 'member_post' => (array) DB::table('posts')->where('id', $memberPostId)->first(), 'user' => array_intersect_key(User::query()->where('username', 'kernadmin')->firstOrFail()->getAttributes(), array_flip(['id', 'username', 'email', 'password', 'super_user', 'permissions'])), 'migrations' => DB::table('migrations')->count()], JSON_THROW_ON_ERROR));
    echo "Seeded a customized 0.1.0 website.\n";
} else {
    $fixture = json_decode(File::get(storage_path('upgrade-fixture.json')), true, flags: JSON_THROW_ON_ERROR);
    foreach ($fixture['hashes'] as $path => $hash) {
        verifyUpgrade(hash_file('sha256', base_path($path)) === $hash, 'Changed site file: '.$path);
    }
    verifyUpgrade(DB::table('pages')->where('name', 'Customer page')->value('content') === '<p>Retained content</p>', 'Customer page lost.');
    verifyUpgrade(DB::table('settings')->where('key', 'customer_setting')->value('value') === 'preserved', 'Setting lost.');
    verifyUpgrade(DB::table('customer_records')->value('value') === 'customer data', 'Custom table lost.');
    $member = (array) DB::table('members')->where('id', $fixture['member']['id'])->first();
    $memberPost = (array) DB::table('posts')->where('id', $fixture['member_post']['id'])->first();
    verifyUpgrade(array_intersect_key($member, $fixture['member']) == $fixture['member'], 'Existing member account or profile changed.');
    verifyUpgrade(array_intersect_key($memberPost, $fixture['member_post']) == $fixture['member_post'], 'Existing member post or ownership changed.');
    verifyUpgrade(Schema::hasColumn('customer_records', 'new_optional') === (! in_array($mode, ['verify-blocked', 'verify-original'], true)), 'Pending migration state did not match the upgrade stage.');
    verifyUpgrade(array_intersect_key(User::query()->where('username', 'kernadmin')->firstOrFail()->getAttributes(), array_flip(['id', 'username', 'email', 'password', 'super_user', 'permissions'])) == $fixture['user'], 'Administrator changed.');
    verifyUpgrade(view('core/base::site-probe')->render() === 'customer customization', 'View override not loaded.');
    verifyUpgrade(config('site.custom') === 'preserved' && SiteCustomization::VALUE === 'preserved', 'Site PHP customization not loaded.');
    verifyUpgrade(app()->bound('site.custom.loaded') && is_plugin_active('site-custom'), 'Custom plugin not loaded.');
    verifyUpgrade(! app()->isDownForMaintenance(), 'Website is still in maintenance mode.');
    if ($mode === 'verify-original') {
        verifyUpgrade(get_cms_version() === $fixture['runtime_version'] && core_path() === $fixture['runtime_path'], 'Preflight changed the legacy runtime.');
        verifyUpgrade(DB::table('migrations')->count() === $fixture['migrations'], 'Preflight changed migrations.');
        verifyUpgrade(! is_dir(storage_path('kern-upgrader')), 'Preflight wrote a website backup.');
        echo "Standalone --check retained the original code, data and customizations.\n";
        exit(0);
    }
    verifyUpgrade(get_cms_version() === $expectedVersion && str_contains(core_path(), '/vendor/binhtq1/kern-core/'), 'Runtime is not loaded from Composer.');
    if ($mode === 'verify-tool') {
        $backups = glob(storage_path('kern-upgrader/*'), GLOB_ONLYDIR);
        verifyUpgrade(count($backups) === 1, 'The standalone upgrader must retain one backup.');
        $backup = $backups[0];
        $status = json_decode(File::get($backup.'/status.json'), true, flags: JSON_THROW_ON_ERROR);
        verifyUpgrade($status['status'] === 'completed' && $status['backup_complete'] === true, 'The standalone backup was not completed.');
        $zip = new ZipArchive;
        verifyUpgrade($zip->open($backup.'/website.zip') === true, 'The original website backup is missing.');
        foreach ($fixture['hashes'] as $path => $hash) {
            verifyUpgrade(hash('sha256', $zip->getFromName($path)) === $hash, 'Original backup lost a site file: '.$path);
        }
        $originalComposer = json_decode($zip->getFromName('composer.json'), true, flags: JSON_THROW_ON_ERROR);
        verifyUpgrade(isset($originalComposer['require']['kern/platform']) && ! isset($originalComposer['require']['binhtq1/kern-core']), 'The backup must contain the pre-conversion Composer configuration.');
        $zip->close();
        $database = new PDO('sqlite:'.$backup.'/database.sqlite');
        verifyUpgrade($database->query('SELECT value FROM customer_records')->fetchColumn() === 'customer data', 'The original database snapshot lost customer data.');
        verifyUpgrade($database->query("SELECT email FROM members WHERE email = 'existing-member@example.test'")->fetchColumn() === 'existing-member@example.test', 'The original snapshot lost the member account.');
        $columns = $database->query('PRAGMA table_info(customer_records)')->fetchAll(PDO::FETCH_ASSOC);
        verifyUpgrade(! in_array('new_optional', array_column($columns, 'name'), true), 'The database snapshot must precede migrations.');
        verifyUpgrade((int) $database->query('SELECT COUNT(*) FROM migrations')->fetchColumn() === $fixture['migrations'], 'The database snapshot must preserve the old migration state.');
        echo "Verified the standalone code backup and pre-migration database snapshot.\n";
    }
    if ($mode === 'verify-blocked') {
        verifyUpgrade(DB::table('migrations')->count() === $fixture['migrations'], 'A blocked upgrade changed migration state.');
        verifyUpgrade(! is_dir(storage_path('kern-upgrades')), 'A blocked upgrade created a snapshot.');
        verifyUpgrade(! is_file(storage_path('app/kern/upgrade.json')), 'A blocked upgrade changed runtime upgrade state.');
        echo "Legacy frontend guard retained all data and customizations before upgrade.\n";
        exit(0);
    }
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
        verifyLocalScripts($client, (string) $homepage->getBody());
        $login = $client->get(parse_url(route('access.login'), PHP_URL_PATH));
        $document = new DOMDocument;
        verifyLocalScripts($client, (string) $login->getBody());
        @$document->loadHTML((string) $login->getBody());
        $token = (new DOMXPath($document))->query('//meta[@name="csrf-token"]')->item(0)->getAttribute('content');
        $response = $client->post(parse_url(route('access.login.post'), PHP_URL_PATH), ['form_params' => ['_token' => $token, 'username' => 'kernadmin', 'password' => 'Upgrade-fixture-password-2026'], 'allow_redirects' => false]);
        verifyUpgrade($response->getStatusCode() === 302 && parse_url($response->getHeaderLine('Location'), PHP_URL_PATH) === parse_url(route('dashboard.index'), PHP_URL_PATH), 'Administrator login failed after upgrade.');
        $dashboard = $client->get($response->getHeaderLine('Location'));
        verifyUpgrade($dashboard->getStatusCode() === 200, 'Upgraded dashboard failed.');
        verifyLocalScripts($client, (string) $dashboard->getBody());
    } finally {
        $server->stop();
    }
    echo "Upgrade 0.1.0 -> $expectedVersion passed: content, admin, settings, custom table, uploads, APP_KEY, theme, plugin, config and view override preserved.\n";
}
