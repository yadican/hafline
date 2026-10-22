<?php

namespace Tests\Feature\Api;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PHPOpenSourceSaver\JWTAuth\Facades\JWTAuth;
use Tests\TestCase;

class ApiActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_api_get_is_logged_on_the_api_channel(): void
    {
        $user = User::factory()->apiOperator()->create();
        $token = JWTAuth::fromUser($user);

        $this->withHeader('Authorization', 'Bearer '.$token)
            ->getJson('/api/v1/me')
            ->assertOk();

        $log = AuditLog::query()->sole();

        $this->assertSame('api', $log->channel);
        $this->assertSame('viewed', $log->action);
        $this->assertSame('GET', $log->method);
        $this->assertSame('api.me', $log->route);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame(200, $log->status);
        $this->assertSame('json', $log->response['type']);
        $this->assertSame($user->email, $log->response['body']['data']['email']);
    }

    public function test_api_login_is_logged_without_password_or_token_fields(): void
    {
        $user = User::factory()->apiOperator()->create([
            'username' => 'apiuser1',
            'password' => 'HafLine34!',
        ]);

        $this->postJson('/api/v1/auth/login', [
            'login' => 'apiuser1',
            'password' => 'HafLine34!',
            'token' => 'should-not-land',
        ])->assertOk();

        $log = AuditLog::query()->sole();

        $this->assertSame('api', $log->channel);
        $this->assertSame('login', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('apiuser1', $log->payload['login']);
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayNotHasKey('token', $log->payload);
        $this->assertSame('json', $log->response['type']);
        $this->assertSame('[redacted]', $log->response['body']['token']);
        $this->assertSame('Bearer', $log->response['body']['token_type']);
        $this->assertSame('apiuser1', $log->response['body']['user']['username']);
    }

    public function test_api_validation_errors_land_in_context(): void
    {
        $this->postJson('/api/v1/auth/login', [
            'login' => 'missing',
        ])->assertStatus(422);

        $log = AuditLog::query()->sole();

        $this->assertSame('api', $log->channel);
        $this->assertSame(422, $log->status);
        $this->assertArrayHasKey('password', $log->context['errors']);
        $this->assertSame('json', $log->response['type']);
        $this->assertArrayHasKey('password', $log->response['body']['errors']);
    }
}
