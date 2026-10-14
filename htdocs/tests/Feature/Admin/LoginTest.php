<?php

namespace Tests\Feature\Admin;

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LoginTest extends TestCase
{
    use RefreshDatabase;

    public function test_user_can_login_with_email(): void
    {
        $user = User::factory()->create(['email' => 'admin@hafline.test']);

        $this->post(route('admin.login.store'), ['login' => 'admin@hafline.test', 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_user_can_login_with_username(): void
    {
        $user = User::factory()->create(['username' => 'santiye1', 'email' => null]);

        $this->post(route('admin.login.store'), ['login' => 'santiye1', 'password' => 'password'])
            ->assertRedirect();

        $this->assertAuthenticatedAs($user);
    }

    public function test_wrong_password_and_passive_user_are_rejected(): void
    {
        User::factory()->create(['username' => 'santiye1']);
        User::factory()->create(['username' => 'pasif', 'active' => false]);

        $this->post(route('admin.login.store'), ['login' => 'santiye1', 'password' => 'yanlis'])
            ->assertSessionHasErrors('login');

        $this->post(route('admin.login.store'), ['login' => 'pasif', 'password' => 'password'])
            ->assertSessionHasErrors('login');

        $this->assertGuest();
    }
}
