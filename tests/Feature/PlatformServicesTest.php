<?php

namespace Tests\Feature;

use BadMethodCallException;
use Composer\Autoload\ClassLoader;
use Google\Analytics\Data\V1beta\Client\BetaAnalyticsDataClient;
use Google\Analytics\Data\V1beta\RunReportResponse;
use Google\ApiCore\InsecureCredentialsWrapper;
use Google\ApiCore\Testing\MockTransport;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Http;
use Kern\Analytics\Analytics;
use Kern\Base\Facades\BaseHelper;
use Kern\Base\Facades\Form;
use Kern\Base\Rules\OnOffRule;
use Kern\Base\Services\ClearCacheService;
use Kern\Base\Services\DownloadLocaleService;
use Kern\Base\Supports\Zipper;
use Kern\JsValidation\Facades\JsValidator;
use Kern\Theme\Facades\Theme;
use Kris\LaravelFormBuilder\Facades\FormBuilder;
use Tests\TestCase;

class PlatformServicesTest extends TestCase
{
    public function test_clearing_purifier_cache_keeps_directories_available_for_active_requests(): void
    {
        $directory = sys_get_temp_dir().'/kern-purifier-'.uniqid();
        config(['purifier.cachePath' => $directory]);

        try {
            BaseHelper::clean('<p>First request</p>');
            app(ClearCacheService::class)->clearPurifier();

            $this->assertDirectoryExists($directory.'/HTML');
            $this->assertSame('<p>Safe</p>', BaseHelper::clean('<p>Safe<script>alert(1)</script></p>', ['HTML.Allowed' => 'p']));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_unknown_theme_methods_throw_a_catchable_exception(): void
    {
        $this->expectException(BadMethodCallException::class);

        Theme::undefinedKernMethod();
    }

    public function test_form_fields_keep_html_validation_attributes_with_modern_rules(): void
    {
        $form = FormBuilder::plain()->add('name', 'text', [
            'rules' => ['required', 'max:20', new OnOffRule],
        ]);

        $html = $form->renderForm();
        $this->assertStringContainsString('required', $html);
        $this->assertStringContainsString('maxlength="20"', $html);
    }

    public function test_javascript_validation_renders_modern_custom_rules(): void
    {
        $script = JsValidator::make(['enabled' => ['required', new OnOffRule]])->render();

        $this->assertStringContainsString('laravelValidationRemote', $script);
        $this->assertStringContainsString('enabled', $script);
    }

    public function test_month_selects_use_application_locale_and_php_date_formats(): void
    {
        $this->app->setLocale('en');
        $this->assertStringContainsString('<option value="2" selected="selected">February</option>', Form::selectMonth('month', 2)->toHtml());
        $this->assertStringContainsString('>Feb</option>', Form::selectMonth('month', null, [], 'M')->toHtml());

        $this->app->setLocale('vi');
        $this->assertStringContainsString('>tháng 2</option>', Form::selectMonth('month')->toHtml());
    }

    public function test_native_zip_archives_preserve_nested_files(): void
    {
        $directory = sys_get_temp_dir().'/kern-zip-'.uniqid();
        File::ensureDirectoryExists($directory.'/source/nested');
        File::put($directory.'/source/nested/content.txt', 'Kern content');
        try {
            $zipper = new Zipper;
            $this->assertTrue($zipper->compress($directory.'/source', $directory.'/backup.zip'));
            $this->assertTrue($zipper->extract($directory.'/backup.zip', $directory.'/restored'));
            $this->assertSame('Kern content', File::get($directory.'/restored/nested/content.txt'));
            $this->assertFalse($zipper->compress($directory.'/missing', $directory.'/missing.zip'));
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_bundled_locales_install_without_contacting_a_remote_service(): void
    {
        $directory = sys_get_temp_dir().'/kern-locale-'.uniqid();
        File::ensureDirectoryExists($directory);
        $this->app->useLangPath($directory);
        Http::preventStrayRequests();
        try {
            $service = new DownloadLocaleService;
            $this->assertContains('vi', $service->getAvailableLocales());
            $service->handle('vi');
            $this->assertFileExists($directory.'/vi.json');
            $this->assertFileExists($directory.'/vendor/core/base/vi/base.php');
            Http::assertNothingSent();
        } finally {
            File::deleteDirectory($directory);
        }
    }

    public function test_analytics_uses_the_current_report_request_contract(): void
    {
        $loader = new ClassLoader;
        $loader->addPsr4('Kern\\Analytics\\', plugin_path('analytics/src'));
        $loader->register();
        $transport = new MockTransport;
        $transport->addResponse(new RunReportResponse);
        $client = new BetaAnalyticsDataClient([
            'transport' => $transport,
            'credentials' => new InsecureCredentialsWrapper,
        ]);
        $analytics = self::getStubBuilder(Analytics::class)->setConstructorArgs(['123', '{}'])
            ->onlyMethods(['getClient'])->getStub();
        $analytics->method('getClient')->willReturn($client);

        $this->assertCount(0, $analytics->get()->table);
        $calls = $transport->popReceivedCalls();
        $this->assertCount(1, $calls);
        $this->assertSame('properties/123', $calls[0]->getRequestObject()->getProperty());
    }
}
