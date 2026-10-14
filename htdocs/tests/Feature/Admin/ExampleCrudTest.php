<?php

namespace Tests\Feature\Admin;

use App\Models\Example;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ExampleCrudTest extends TestCase
{
    use RefreshDatabase;

    public function test_guest_is_redirected_from_the_example_list(): void
    {
        $this->get(route('admin.examples.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_an_example(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->post(route('admin.examples.store'), [
                'title' => 'Saha notu',
                'note' => 'İlk kayıt',
            ])
            ->assertRedirect(route('admin.examples.index'))
            ->assertSessionHas('success');

        $example = Example::query()->where('title', 'Saha notu')->first();
        $this->assertNotNull($example);
        $this->assertSame('İlk kayıt', $example->note);

        $this->actingAs($admin)
            ->put(route('admin.examples.update', $example), [
                'title' => 'Güncel not',
                'note' => null,
            ])
            ->assertRedirect(route('admin.examples.index'))
            ->assertSessionHas('success');

        $this->assertSame('Güncel not', $example->refresh()->title);

        $this->actingAs($admin)
            ->delete(route('admin.examples.destroy', $example))
            ->assertRedirect(route('admin.examples.index'))
            ->assertSessionHas('success');

        $this->assertDatabaseMissing('examples', ['id' => $example->id]);
    }

    public function test_admin_can_search_and_filter_the_example_list(): void
    {
        $admin = User::factory()->create();

        Example::factory()->create(['title' => 'Saha notu', 'active' => true, 'shift' => 'day']);
        Example::factory()->create(['title' => 'Depo sayımı', 'active' => false, 'shift' => 'night']);

        $this->actingAs($admin)
            ->get(route('admin.examples.index', ['q' => 'SAHA']))
            ->assertOk()
            ->assertSee('Saha notu')
            ->assertDontSee('Depo sayımı');

        $this->actingAs($admin)
            ->get(route('admin.examples.index', ['active' => '0', 'shift' => 'night']))
            ->assertOk()
            ->assertSee('Depo sayımı')
            ->assertDontSee('Saha notu');

        $this->actingAs($admin)
            ->get(route('admin.examples.index', ['q' => 'yok böyle bir kayıt']))
            ->assertOk()
            ->assertSee('Aramanızla eşleşen kayıt bulunamadı.');
    }

    public function test_invalid_example_is_not_stored(): void
    {
        $admin = User::factory()->create();

        $this->actingAs($admin)
            ->from(route('admin.examples.create'))
            ->post(route('admin.examples.store'), ['title' => ''])
            ->assertRedirect(route('admin.examples.create'))
            ->assertSessionHasErrors('title');

        $this->assertDatabaseCount('examples', 0);
    }
}
