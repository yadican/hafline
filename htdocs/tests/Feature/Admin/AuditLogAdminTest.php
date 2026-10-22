<?php

namespace Tests\Feature\Admin;

use App\Models\AuditLog;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class AuditLogAdminTest extends TestCase
{
    use RefreshDatabase;

    public function test_yonetici_can_browse_web_and_api_logs_separately(): void
    {
        $admin = User::factory()->yonetici()->create();

        $webLog = AuditLog::query()->create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'channel' => AuditLog::CHANNEL_WEB,
            'action' => 'created',
            'method' => 'POST',
            'route' => 'admin.examples.store',
            'url' => 'http://localhost/admin/examples',
            'status' => 302,
            'ip_address' => '127.0.0.1',
            'payload' => ['title' => 'Web'],
        ]);

        $apiLog = AuditLog::query()->create([
            'id' => (string) Str::uuid7(),
            'user_id' => $admin->id,
            'channel' => AuditLog::CHANNEL_API,
            'action' => 'viewed',
            'method' => 'GET',
            'route' => 'api.me',
            'url' => 'http://localhost/api/v1/me',
            'status' => 200,
            'ip_address' => '10.0.0.1',
            'payload' => [],
        ]);

        $this->actingAs($admin)
            ->get(route('admin.web-audit-logs.index'))
            ->assertOk()
            ->assertSee('admin.examples.store')
            ->assertDontSee('api.me');

        $this->actingAs($admin)
            ->get(route('admin.api-audit-logs.index'))
            ->assertOk()
            ->assertSee('api.me')
            ->assertDontSee('admin.examples.store');

        $this->actingAs($admin)
            ->get(route('admin.web-audit-logs.show', $webLog))
            ->assertOk()
            ->assertSee('Web');

        $this->actingAs($admin)
            ->get(route('admin.api-audit-logs.show', $apiLog))
            ->assertOk()
            ->assertSee('api.me');

        $this->actingAs($admin)
            ->get(route('admin.web-audit-logs.show', $apiLog))
            ->assertNotFound();

        $this->actingAs($admin)
            ->get(route('admin.api-audit-logs.show', $webLog))
            ->assertNotFound();
    }

    public function test_user_without_permission_cannot_view_audit_logs(): void
    {
        $user = User::factory()->create();

        $this->actingAs($user)
            ->get(route('admin.web-audit-logs.index'))
            ->assertForbidden();

        $this->actingAs($user)
            ->get(route('admin.api-audit-logs.index'))
            ->assertForbidden();
    }
}
