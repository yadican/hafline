<?php

namespace Tests\Feature\Admin;

use App\Models\City;
use App\Models\District;
use App\Models\Neighborhood;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class LocationTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private City $istanbul;

    private District $tuzla;

    private Neighborhood $aydinli;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->istanbul = City::query()->create(['code' => 34, 'name' => 'İstanbul']);
        $this->tuzla = $this->istanbul->districts()->create(['code' => 1835, 'name' => 'Tuzla']);
        $this->aydinli = $this->tuzla->neighborhoods()->create(['name' => 'Aydınlı', 'type' => 'mahalle', 'postal_code' => '34953']);
        $this->tuzla->neighborhoods()->create(['name' => 'Akfırat', 'type' => 'koy']);
    }

    public function test_guest_cannot_read_locations(): void
    {
        $this->getJson(route('admin.locations.districts', $this->istanbul))->assertUnauthorized();
    }

    public function test_districts_and_neighborhoods_follow_the_parent(): void
    {
        $ankara = City::query()->create(['code' => 6, 'name' => 'Ankara']);
        $ankara->districts()->create(['code' => 1130, 'name' => 'Çankaya']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.locations.districts', $this->istanbul))
            ->assertExactJson([['id' => $this->tuzla->id, 'name' => 'Tuzla']]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.locations.neighborhoods', $this->tuzla))
            ->assertJsonPath('0.name', 'Akfırat (Köy)')
            ->assertJsonPath('1.name', 'Aydınlı');
    }

    public function test_party_is_saved_with_its_location_and_edit_page_preloads_the_lists(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'company',
                'title' => 'TUZLA HAFRİYAT LTD. ŞTİ.',
                'city_id' => $this->istanbul->id,
                'district_id' => $this->tuzla->id,
                'neighborhood_id' => $this->aydinli->id,
                'address' => 'İçmeler Cd. No: 5',
            ])
            ->assertSessionHasNoErrors();

        $party = Party::query()->sole();
        $this->assertSame([$this->istanbul->id, $this->tuzla->id, $this->aydinli->id], [$party->city_id, $party->district_id, $party->neighborhood_id]);
        $this->assertSame('Aydınlı', $party->neighborhood->name);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $party))
            ->assertOk()
            ->assertSee('data-location-district', false)
            ->assertSeeInOrder(['name="district_id"', 'value="'.$this->tuzla->id.'"', 'selected', 'Tuzla'], false)
            ->assertSee('Akfırat (Köy)');
    }

    public function test_district_must_belong_to_the_city_and_neighborhood_to_the_district(): void
    {
        $ankara = City::query()->create(['code' => 6, 'name' => 'Ankara']);
        $cankaya = $ankara->districts()->create(['code' => 1130, 'name' => 'Çankaya']);

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'person',
                'first_name' => 'Ali',
                'last_name' => 'Kaya',
                'city_id' => $ankara->id,
                'district_id' => $this->tuzla->id,
            ])
            ->assertSessionHasErrors('district_id');

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'person',
                'first_name' => 'Ali',
                'last_name' => 'Kaya',
                'city_id' => $ankara->id,
                'district_id' => $cankaya->id,
                'neighborhood_id' => $this->aydinli->id,
            ])
            ->assertSessionHasErrors('neighborhood_id')
            ->assertSessionDoesntHaveErrors('district_id');

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'person',
                'first_name' => 'Ali',
                'last_name' => 'Kaya',
                'district_id' => $cankaya->id,
            ])
            ->assertSessionHasErrors('district_id');

        $this->assertDatabaseCount('parties', 0);
    }
}
