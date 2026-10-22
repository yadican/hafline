<?php

namespace Tests\Feature\Admin;

use App\Http\Middleware\PreventDuplicateSubmissions;
use App\Models\Example;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Str;
use Tests\TestCase;

class DuplicateSubmissionTest extends TestCase
{
    use RefreshDatabase;

    private const TOKEN = '0123456789abcdef0123456789abcdef';

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->yonetici()->create())
            ->withCookie(config('session.cookie'), Str::random(40));
    }

    public function test_repeated_submission_replays_the_first_result_without_storing_twice(): void
    {
        $payload = ['title' => 'Saha notu', PreventDuplicateSubmissions::FIELD => self::TOKEN];

        $this->post(route('admin.examples.store'), $payload)
            ->assertRedirect(route('admin.examples.index'))
            ->assertSessionHas('success');

        $this->post(route('admin.examples.store'), $payload)
            ->assertRedirect(route('admin.examples.index'))
            ->assertSessionHas('success');

        $this->assertSame(1, Example::query()->where('title', 'Saha notu')->count());
    }

    public function test_failed_submission_releases_the_token_for_a_retry(): void
    {
        $this->post(route('admin.examples.store'), ['title' => '', PreventDuplicateSubmissions::FIELD => self::TOKEN])
            ->assertSessionHasErrors('title');

        $this->post(route('admin.examples.store'), ['title' => 'Saha notu', PreventDuplicateSubmissions::FIELD => self::TOKEN])
            ->assertRedirect(route('admin.examples.index'));

        $this->assertSame(1, Example::query()->where('title', 'Saha notu')->count());
    }

    public function test_different_tokens_are_separate_submissions(): void
    {
        $this->post(route('admin.examples.store'), ['title' => 'Saha notu', PreventDuplicateSubmissions::FIELD => self::TOKEN]);
        $this->post(route('admin.examples.store'), ['title' => 'Saha notu', PreventDuplicateSubmissions::FIELD => strrev(self::TOKEN)]);

        $this->assertSame(2, Example::query()->where('title', 'Saha notu')->count());
    }
}
