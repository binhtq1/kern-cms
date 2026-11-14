<?php

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\File;
use Tests\TestCase;

class ExampleTest extends TestCase
{
    use RefreshDatabase;

    /**
     * A basic test example.
     */
    public function test_home_redirects_to_installation_on_a_fresh_database(): void
    {
        $directory = sys_get_temp_dir().'/kern-fresh-install-'.uniqid();
        File::ensureDirectoryExists($directory);
        $this->app->useStoragePath($directory);

        try {
            $this->get('/')->assertRedirect(route('installers.welcome'));
        } finally {
            File::deleteDirectory($directory);
        }
    }
}
