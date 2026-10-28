<?php

namespace Tests\Feature\Admin;

use App\Enums\TagTone;
use App\Models\Party;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class TagTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.tags.index'))->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_a_tag(): void
    {
        $this->actingAs($this->admin)->get(route('admin.tags.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.tags.store'), ['name' => 'Takipte', 'tone' => 'red', 'active' => '1'])
            ->assertRedirect(route('admin.tags.index'))
            ->assertSessionHas('success');

        $tag = Tag::query()->sole();
        $this->assertSame(TagTone::Red, $tag->tone);

        $this->actingAs($this->admin)
            ->post(route('admin.tags.store'), ['name' => 'Takipte', 'tone' => 'pink'])
            ->assertSessionHasErrors(['name', 'tone']);

        $this->actingAs($this->admin)
            ->put(route('admin.tags.update', $tag), ['name' => 'Yakın takip', 'tone' => 'orange', 'active' => '1'])
            ->assertRedirect(route('admin.tags.index'));

        $this->assertSame(TagTone::Orange, $tag->fresh()->tone);

        $this->actingAs($this->admin)->get(route('admin.tags.index'))->assertSee('Yakın takip');
    }

    public function test_tags_are_set_on_the_cari_card_filter_the_list_and_leave_with_a_deleted_tag(): void
    {
        $big = Tag::factory()->create(['name' => 'Büyük müşteri', 'tone' => TagTone::Green]);
        $watch = Tag::factory()->create(['name' => 'Takipte']);
        $party = Party::factory()->company()->create(['name' => 'ALICI A.Ş.']);
        Party::factory()->company()->create(['name' => 'DİĞER A.Ş.']);

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $party), ['title' => 'ALICI A.Ş.', 'active' => '1', 'tag_ids' => [$big->id, $watch->id]])
            ->assertSessionHasNoErrors();

        $this->assertEqualsCanonicalizing([$big->id, $watch->id], $party->tags()->pluck('tags.id')->all());

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index', ['tag' => $big->id]))
            ->assertSee('ALICI A.Ş.')
            ->assertSee('Büyük müşteri')
            ->assertDontSee('DİĞER A.Ş.');

        $this->actingAs($this->admin)
            ->delete(route('admin.tags.destroy', $big))
            ->assertRedirect(route('admin.tags.index'));

        $this->assertSoftDeleted($big);
        $this->assertSame([$watch->id], $party->tags()->pluck('tags.id')->all());
    }
}
