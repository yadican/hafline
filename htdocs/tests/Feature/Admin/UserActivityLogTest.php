<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\PreventDuplicateSubmissions;
use App\Models\AuditLog;
use App\Models\Example;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Tests\TestCase;

class UserActivityLogTest extends TestCase
{
    use RefreshDatabase;

    public function test_form_submission_stores_every_posted_field_with_the_request(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.examples.store'), [
                'title' => 'Saha notu',
                'note' => 'İlk kayıt',
                'email' => 'saha@hafline.test',
                'phone' => '05001112233',
                'quantity' => 4,
                'amount' => '1250.50',
                'tags' => ['cash'],
                'channels' => ['panel'],
                'active' => '1',
                'secret' => 'saha-kodu',
                'password' => 'should-not-land',
                'attachment' => UploadedFile::fake()->create('fis.pdf', 12, 'application/pdf'),
            ])
            ->assertRedirect(route('admin.examples.index'));

        $log = AuditLog::query()->sole();
        $example = Example::query()->where('title', 'Saha notu')->firstOrFail();

        $this->assertSame($admin->id, $log->user_id);
        $this->assertSame('created', $log->action);
        $this->assertNull($log->auditable_type);
        $this->assertNull($log->auditable_id);
        $this->assertSame($example->title, $log->payload['title']);
        $this->assertSame('POST', $log->method);
        $this->assertSame('admin.examples.store', $log->route);
        $this->assertSame(302, $log->status);
        $this->assertSame('Saha notu', $log->payload['title']);
        $this->assertSame('İlk kayıt', $log->payload['note']);
        $this->assertSame('saha@hafline.test', $log->payload['email']);
        $this->assertSame('05001112233', $log->payload['phone']);
        $this->assertEquals(4, $log->payload['quantity']);
        $this->assertSame('1250.50', $log->payload['amount']);
        $this->assertSame(['cash'], $log->payload['tags']);
        $this->assertSame(['panel'], $log->payload['channels']);
        $this->assertSame('saha-kodu', $log->payload['secret']);
        $this->assertSame([
            'name' => 'fis.pdf',
            'size' => 12 * 1024,
            'mime' => 'application/pdf',
        ], $log->payload['attachment']);
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayNotHasKey('_token', $log->payload);
        $this->assertSame('Kayıt oluşturuldu.', $log->context['messages']['success']);
    }

    public function test_update_keeps_the_record_before_and_after_alongside_the_posted_fields(): void
    {
        $admin = User::factory()->create();
        $example = Example::factory()->create(['title' => 'Saha notu', 'note' => 'İlk kayıt']);

        $this->actingAs($admin)
            ->put(route('admin.examples.update', $example), [
                'title' => 'Güncel not',
                'note' => 'Düzeltildi',
                'email' => 'guncel@hafline.test',
            ])
            ->assertRedirect(route('admin.examples.index'));

        $log = AuditLog::query()->sole();

        $this->assertSame('updated', $log->action);
        $this->assertSame($example->getMorphClass(), $log->auditable_type);
        $this->assertSame($example->id, $log->auditable_id);
        $this->assertSame('Saha notu', $log->old_values['example']['title']);
        $this->assertSame('İlk kayıt', $log->old_values['example']['note']);
        $this->assertSame('Güncel not', $log->new_values['example']['title']);
        $this->assertSame('Düzeltildi', $log->new_values['example']['note']);
        $this->assertSame('Güncel not', $log->payload['title']);
        $this->assertSame('Düzeltildi', $log->payload['note']);
        $this->assertSame('guncel@hafline.test', $log->payload['email']);
        $this->assertSame($example->id, $log->context['parameters']['example']);
    }

    public function test_rejected_form_is_logged_with_the_posted_fields_and_errors(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.examples.store'), [
                'title' => '',
                'note' => 'Eksik başlık',
                'email' => 'saha@hafline.test',
            ])
            ->assertSessionHasErrors('title');

        $log = AuditLog::query()->sole();

        $this->assertArrayHasKey('title', $log->payload);
        $this->assertNull($log->payload['title']);
        $this->assertSame('Eksik başlık', $log->payload['note']);
        $this->assertSame('saha@hafline.test', $log->payload['email']);
        $this->assertArrayHasKey('title', $log->context['errors']);
        $this->assertSame(0, Example::query()->count());
    }

    public function test_login_records_the_account_without_the_password(): void
    {
        $user = User::factory()->create(['username' => 'santiye1', 'email' => null]);

        $this->post(route('admin.login.store'), [
            'login' => 'santiye1',
            'password' => 'password',
        ])->assertRedirect();

        $log = AuditLog::query()->sole();

        $this->assertSame('login', $log->action);
        $this->assertSame($user->id, $log->user_id);
        $this->assertSame('santiye1', $log->payload['login']);
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertStringNotContainsString('password', json_encode($log->getAttributes()));
    }

    public function test_failed_login_is_logged_without_a_user_or_password(): void
    {
        User::factory()->create(['username' => 'santiye1']);

        $this->post(route('admin.login.store'), [
            'login' => 'santiye1',
            'password' => 'yanlis',
        ])->assertSessionHasErrors('login');

        $log = AuditLog::query()->sole();

        $this->assertNull($log->user_id);
        $this->assertSame('santiye1', $log->payload['login']);
        $this->assertArrayNotHasKey('password', $log->payload);
        $this->assertArrayHasKey('login', $log->context['errors']);
    }

    public function test_opening_a_page_does_not_write_an_activity_row(): void
    {
        $this->actingAs(User::factory()->create())
            ->get(route('admin.examples.index'))
            ->assertOk();

        $this->assertSame(0, AuditLog::query()->count());
    }

    public function test_replayed_duplicate_submission_is_logged_once(): void
    {
        $token = '0123456789abcdef0123456789abcdef';

        $this->actingAs(User::factory()->create())
            ->withCookie(config('session.cookie'), Str::random(40));

        $payload = ['title' => 'Saha notu', PreventDuplicateSubmissions::FIELD => $token];

        $this->post(route('admin.examples.store'), $payload)->assertRedirect();
        $this->post(route('admin.examples.store'), $payload)->assertRedirect();

        $this->assertSame(1, AuditLog::query()->count());
        $this->assertArrayNotHasKey(PreventDuplicateSubmissions::FIELD, AuditLog::query()->sole()->payload);
    }
}
