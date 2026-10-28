<?php

use Dotenv\Dotenv;
use GuzzleHttp\Client;
use Illuminate\Contracts\Console\Kernel as ConsoleKernel;
use Psr\Http\Message\ResponseInterface;
use Symfony\Component\Process\Process;

require __DIR__.'/../vendor/autoload.php';

function failProjectCheck(Throwable $exception): never
{
    fwrite(STDERR, (string) $exception.PHP_EOL);
    exit(1);
}

try {
    $app = require __DIR__.'/../bootstrap/app.php';
    $app->make(ConsoleKernel::class)->bootstrap();
} catch (Throwable $exception) {
    failProjectCheck($exception);
}
set_exception_handler(failProjectCheck(...));

function check(bool $condition, string $message): void
{
    if (! $condition) {
        throw new RuntimeException($message);
    }
}

function pageDocument(ResponseInterface $response): DOMDocument
{
    check($response->getStatusCode() === 200, 'Expected a page, received HTTP '.$response->getStatusCode());
    $document = new DOMDocument;
    @$document->loadHTML((string) $response->getBody());

    return $document;
}

function submitForm(Client $client, ResponseInterface $page, string $formId, array $values): ResponseInterface
{
    $document = pageDocument($page);
    $form = $document->getElementById($formId);
    check($form instanceof DOMElement, 'Missing form: '.$formId);
    $token = (new DOMXPath($document))->query('.//input[@name="_token"]', $form)->item(0);
    check($token instanceof DOMElement, 'Missing CSRF token: '.$formId);

    return $client->post($form->getAttribute('action'), [
        'form_params' => ['_token' => $token->getAttribute('value'), ...$values],
    ]);
}

function followRedirect(Client $client, ResponseInterface $response, string $expectedPath): ResponseInterface
{
    $location = $response->getHeaderLine('Location');
    check(in_array($response->getStatusCode(), [302, 303], true), 'Expected a redirect to '.$expectedPath.', received HTTP '.$response->getStatusCode());
    if (parse_url($location, PHP_URL_PATH) !== $expectedPath) {
        $document = pageDocument($client->get($location));
        $errors = (new DOMXPath($document))->query('//*[@data-error-summary]')->item(0);
        $message = trim(preg_replace('/\s+/', ' ', $errors?->textContent ?? ''));
        throw new RuntimeException('Expected redirect to '.$expectedPath.', received '.$location.'. '.$message);
    }

    return $client->get($location);
}

check((bool) config('app.key'), 'The project must have its own APP_KEY.');
check(is_file(base_path('.env')), 'The project must have an environment file.');
check(is_file(database_path('database.sqlite')), 'The default SQLite file must exist.');
check(! is_file(storage_path('installed')), 'A new project must open the installation wizard.');
check(! is_file(storage_path('installing')), 'A new project must not include an installation session.');
check(! is_dir(base_path('node_modules')), 'Installation must not require Node.js dependencies.');
check(config('database.default') === 'sqlite', 'Run this test only on a fresh project using SQLite.');

$databasePath = database_path('database.sqlite');
check(config('database.connections.sqlite.database') === $databasePath, 'Run this test only with the default SQLite database.');
$database = new PDO('sqlite:'.$databasePath, options: [PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION]);
check((int) $database->query("SELECT count(*) FROM sqlite_master WHERE type = 'table' AND name NOT LIKE 'sqlite_%'")->fetchColumn() === 0, 'Run this test only with an empty SQLite database.');

$theme = $argv[1] ?? 'magazine';
check(preg_match('/^[a-z0-9-]+$/', $theme) === 1 && is_file(theme_path($theme.'/theme.json')), 'Unknown test theme.');

foreach (['build/admin/manifest.json', 'build/admin/admin-registry.json', 'vendor/core/packages/installer/css/style.css'] as $path) {
    check(is_file(public_path($path)), 'Missing published asset: '.$path);
}

$socket = stream_socket_server('tcp://127.0.0.1:0', $errorCode, $errorMessage);
check($socket !== false, 'Cannot reserve a test port: '.$errorMessage);
$address = stream_socket_get_name($socket, false);
check(is_string($address), 'Cannot determine the test address.');
fclose($socket);
$origin = 'http://'.$address;
$server = new Process([
    PHP_BINARY, 'artisan', 'serve', '--host=127.0.0.1',
    '--port='.parse_url($origin, PHP_URL_PORT), '--no-reload', '--no-interaction',
], base_path(), ['APP_ENV' => 'local', 'APP_DEBUG' => 'false', 'APP_URL' => $origin]);
$server->setTimeout(180);
$options = ['base_uri' => $origin, 'cookies' => true, 'allow_redirects' => false, 'http_errors' => false, 'timeout' => 60];
$client = new Client($options);

try {
    $server->start();
    $deadline = microtime(true) + 15;
    do {
        check($server->isRunning(), 'The test server exited: '.$server->getErrorOutput());
        $connection = @stream_socket_client('tcp://'.$address, $errorCode, $errorMessage, 0.1);
        if ($connection !== false) {
            fclose($connection);
            break;
        }
        check(microtime(true) < $deadline, 'The test server did not start.');
        usleep(100_000);
    } while (true);

    $welcome = $client->get('/install/welcome');
    $requirements = followRedirect($client, submitForm($client, $welcome, 'welcome-form', ['language' => 'en']), '/install/requirements');
    $document = pageDocument($requirements);
    $continue = (new DOMXPath($document))->query('//a[contains(@href, "/install/environments")]')->item(0);
    check($continue instanceof DOMElement, 'Server requirements must allow installation to continue.');

    $environment = $client->get($continue->getAttribute('href'));
    $document = pageDocument($environment);
    $databaseField = $document->getElementById('database_name');
    check($databaseField instanceof DOMElement, 'Missing SQLite database field.');
    check($databaseField->getAttribute('value') === $databasePath, 'The wizard must use the new project database.');
    check((int) $databaseField->getAttribute('maxlength') >= strlen($databasePath), 'The database field must allow the full SQLite path.');

    $themes = followRedirect($client, submitForm($client, $environment, 'environment-form', [
        'app_name' => 'Kern CMS',
        'app_url' => $origin,
        'database_connection' => 'sqlite',
        'database_name' => $databasePath,
    ]), '/install/themes');
    check(is_file(storage_path('installing')), 'Database setup must start an installation session.');

    $account = followRedirect($client, submitForm($client, $themes, 'choose-theme-form', ['theme' => $theme]), '/install/accounts');
    $password = bin2hex(random_bytes(16));
    $finished = followRedirect($client, submitForm($client, $account, 'create-account-form', [
        'first_name' => 'Kern',
        'last_name' => 'Admin',
        'username' => 'kernadmin',
        'email' => 'admin@example.test',
        'password' => $password,
        'password_confirmation' => $password,
    ]), '/install/final');
    $document = pageDocument($finished);
    check(is_file(storage_path('installed')), 'Finishing the wizard must lock the installer.');
    check(! is_file(storage_path('installing')), 'Finishing the wizard must clear the installation session.');

    $values = Dotenv::createArrayBacked(base_path())->load();
    check($values['DB_CONNECTION'] === 'sqlite' && $values['DB_DATABASE'] === $databasePath, 'Database configuration must persist in .env.');
    check($values['APP_KEY'] === config('app.key'), 'Setup must preserve the project key.');
    $user = $database->query("SELECT users.* FROM users JOIN activations ON activations.user_id = users.id AND activations.completed = 1 WHERE users.username = 'kernadmin'")->fetch(PDO::FETCH_ASSOC);
    check(is_array($user) && (bool) $user['super_user'], 'Setup must create an active administrator.');
    check(password_verify($password, $user['password']), 'Setup must store the administrator password securely.');
    check($database->query("SELECT value FROM settings WHERE key = 'theme'")->fetchColumn() === $theme, 'Setup must activate the selected theme.');

    $loginLink = (new DOMXPath($document))->query('//a[contains(@class, "installer-button")]')->item(0);
    check($loginLink instanceof DOMElement, 'The completion page must link to administrator login.');
    $client = new Client($options);
    $loginUrl = $loginLink->getAttribute('href');
    $document = pageDocument($client->get($loginUrl));
    $csrf = (new DOMXPath($document))->query('//meta[@name="csrf-token"]')->item(0);
    check($csrf instanceof DOMElement, 'Administrator login must provide a CSRF token.');
    $dashboard = followRedirect($client, $client->post($loginUrl, ['form_params' => [
        '_token' => $csrf->getAttribute('content'),
        'username' => 'kernadmin',
        'password' => $password,
    ]]), parse_url(route('dashboard.index'), PHP_URL_PATH));
    pageDocument($dashboard);
    pageDocument($client->get('/'));

    $locked = $client->get('/install/welcome');
    check($locked->getStatusCode() === 302 && (parse_url($locked->getHeaderLine('Location'), PHP_URL_PATH) ?: '/') === '/', 'The installer must stay locked after setup.');
    $assetPaths = ['vendor/core/packages/installer/css/style.css'];
    foreach (['build/admin', 'themes/'.$theme.'/build'] as $directory) {
        $manifest = json_decode(file_get_contents(public_path($directory.'/manifest.json')), true, flags: JSON_THROW_ON_ERROR);
        foreach ($manifest as $entry) {
            if (! ($entry['isEntry'] ?? false)) {
                continue;
            }
            foreach ([$entry['file'], ...($entry['css'] ?? [])] as $file) {
                $assetPaths[] = $directory.'/'.$file;
            }
        }
    }
    foreach (array_unique($assetPaths) as $path) {
        check($client->get('/'.$path)->getStatusCode() === 200, 'The installed website must serve asset: '.$path);
    }

    echo 'Fresh Composer project passed: complete wizard, '.$theme.' theme, administrator login, installer lock and published assets. SQLite path length: '.strlen($databasePath).".\n";
} catch (Throwable $exception) {
    fwrite(STDERR, $server->getOutput().$server->getErrorOutput());
    throw $exception;
} finally {
    $server->stop(3);
}
