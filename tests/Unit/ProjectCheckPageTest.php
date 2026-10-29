<?php

namespace Tests\Unit;

use GuzzleHttp\Psr7\Response;
use PHPUnit\Framework\Attributes\PreserveGlobalState;
use PHPUnit\Framework\Attributes\RunTestsInSeparateProcesses;
use PHPUnit\Framework\TestCase;
use RuntimeException;

#[RunTestsInSeparateProcesses]
#[PreserveGlobalState(false)]
class ProjectCheckPageTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();
        define('KERN_PROJECT_CHECK_HELPERS_ONLY', true);
        require __DIR__.'/../../scripts/check-project.php';
    }

    public function test_reads_installed_inertia_three_json_script_payload(): void
    {
        $page = ['component' => 'PostForm', 'props' => ['values' => ['name' => 'Bài viết <script> & "quote"']]];
        $html = '<script data-page="app" type="application/json">'.json_encode($page, JSON_HEX_TAG | JSON_THROW_ON_ERROR).'</script><div id="app"></div>';

        $this->assertSame($page, inertiaPage(new Response(200, body: $html)));
    }

    public function test_reads_legacy_inertia_root_attribute_payload(): void
    {
        $page = ['component' => 'Dashboard', 'props' => ['name' => 'Member & editor']];
        $html = '<div id="app" data-page="'.htmlspecialchars(json_encode($page, JSON_THROW_ON_ERROR), ENT_QUOTES, 'UTF-8').'"></div>';

        $this->assertSame($page, inertiaPage(new Response(200, body: $html)));
    }

    public function test_rejects_unsuccessful_page_responses(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Expected a page, received HTTP 500');

        inertiaPage(new Response(500, body: '<div id="app"></div>'));
    }

    public function test_rejects_script_data_without_the_mount_root(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must render its Inertia shell');

        inertiaPage(new Response(200, body: '<script data-page="app" type="application/json">{}</script>'));
    }

    public function test_rejects_missing_page_data(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must include its Inertia page data');

        inertiaPage(new Response(200, body: '<div id="app"></div>'));
    }

    public function test_rejects_non_object_page_data(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('must include an Inertia page object');

        inertiaPage(new Response(200, body: '<script data-page="app" type="application/json">null</script><div id="app"></div>'));
    }

    public function test_rejects_jquery_loaded_by_member_page(): void
    {
        $this->expectException(RuntimeException::class);
        $this->expectExceptionMessage('Member pages must not load jQuery');

        inertiaPage(new Response(200, body: '<script data-page="app" type="application/json">{}</script><div id="app"></div><script src="/js/jquery.min.js"></script>'));
    }
}
