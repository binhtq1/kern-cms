<?php

namespace Tests\Feature;

use Database\Factories\UserFactory;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Auth;
use Tests\TestCase;

class ApiAuthenticationTest extends TestCase
{
    use RefreshDatabase;

    public function test_enabled_api_can_issue_and_authenticate_a_sanctum_token(): void
    {
        config(['packages.installer.installer.enabled' => false]);
        setting()->set('api_enabled', 1)->save();
        require platform_path('packages/api/routes/api.php');
        $user = UserFactory::new()->create(['email' => 'api@example.test']);

        $response = $this->postJson('/api/v1/login', ['email' => $user->email, 'password' => 'password123']);
        $response->assertOk()->assertJsonPath('error', false);
        $token = $response->json('data.token');
        $this->assertIsString($token);
        $this->assertDatabaseCount('personal_access_tokens', 1);
        Auth::forgetGuards();
        $this->withToken($token)->getJson('/api/v1/me')->assertOk();
    }
}
