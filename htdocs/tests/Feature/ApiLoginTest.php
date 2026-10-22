<?php

namespace Tests\Feature;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ApiLoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_login_returns_a_token(): void
    {
        $user = User::factory()->create([
            'email' => 'admin@hafline.test',
        ]);

        $response = $this->postJson('/api/v1/auth/login', [
            'login' => $user->email,
            'password' => 'HafLine34!',
        ]);

        $response->assertOk()
            ->assertJsonPath('token_type', 'Bearer')
            ->assertJsonPath('user.email', 'admin@hafline.test')
            ->assertJsonStructure(['token', 'token_type', 'expires_in', 'user']);

        $token = $response->json('token');
        $this->assertCount(3, explode('.', $token), 'JWT must have three segments');

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk()
            ->assertJsonPath('data.email', 'admin@hafline.test');
    }

    public function test_login_accepts_username_without_email(): void
    {
        User::factory()->create(['username' => 'santiye1', 'email' => null]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'santiye1',
            'password' => 'HafLine34!',
        ])->assertOk()->assertJsonPath('user.username', 'santiye1');
    }

    public function test_passive_user_cannot_login(): void
    {
        User::factory()->create(['username' => 'pasif', 'active' => false]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'pasif',
            'password' => 'HafLine34!',
        ])->assertUnprocessable()->assertJsonValidationErrors('login');
    }
}
