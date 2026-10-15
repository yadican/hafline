<?php

namespace Tests\Feature\Admin;

use App\Models\DumpSite;
use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\RouteVehiclePrice;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

class HaulTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Vehicle $vehicle;

    private HaulRoute $route;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
        $this->vehicle = Vehicle::factory()->create(['plate' => '34 ABC 123']);
        $this->route = HaulRoute::factory()->create();

        RouteClassPrice::query()->create([
            'route_id' => $this->route->id,
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
            'price' => 2500,
        ]);

        DumpSiteClassPrice::query()->create([
            'dump_site_id' => $this->route->dump_site_id,
            'vehicle_class_id' => $this->vehicle->vehicle_class_id,
            'price' => 400,
        ]);
    }

    public function test_guest_is_redirected_from_the_haul_page(): void
    {
        $this->get(route('admin.hauls.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_each_trip_is_stored_with_the_class_price(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['plate' => '34abc 123', 'trips' => 5]))
            ->assertRedirect(route('admin.hauls.index'))
            ->assertSessionHas('success', '5 sefer kaydedildi.');

        $hauls = Haul::query()->where('ticket_no', 'F-100')->get();

        $this->assertCount(5, $hauls);
        $this->assertTrue($hauls->every(fn (Haul $haul) => $haul->vehicle_id === $this->vehicle->id
            && $haul->price === '2500.00'
            && $haul->dump_price === '400.00'
            && $haul->party_id === $this->vehicle->currentOwnership->party_id
            && $haul->dump_party_id === $this->route->dumpSite->party_id
            && $haul->created_by === $this->admin->id));
    }

    public function test_each_trip_credits_the_owner_and_the_dump_operator(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['trips' => 2]));

        $owner = $this->vehicle->currentOwnership->party_id;
        $operator = $this->route->dumpSite->party_id;

        $this->assertSame(4, LedgerEntry::query()->count());
        $this->assertSame(5000.0, (float) LedgerEntry::query()->where('party_id', $owner)->sum('credit'));
        $this->assertSame(800.0, (float) LedgerEntry::query()->where('party_id', $operator)->sum('credit'));
        $this->assertTrue(LedgerEntry::query()->get()->every(fn (LedgerEntry $entry) => $entry->source_type === 'haul'
            && $entry->vehicle_id === $this->vehicle->id
            && $entry->created_by === $this->admin->id
            && str_starts_with((string) $entry->description, 'Taşıma fişi F-100')));
    }

    public function test_haul_goes_to_whoever_owned_the_vehicle_on_the_haul_date(): void
    {
        $seller = $this->vehicle->currentOwnership->party;
        $buyer = Party::factory()->create();
        $this->vehicle->currentOwnership->update(['ended_on' => now()->subDays(3)->toDateString()]);
        $this->vehicle->ownerships()->create(['party_id' => $buyer->id, 'started_on' => now()->subDays(2)->toDateString()]);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['ticket_no' => 'OLD', 'hauled_at' => now()->subDays(5)->format('Y-m-d\TH:i')]));
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['ticket_no' => 'NEW']));

        $this->assertSame($seller->id, Haul::query()->where('ticket_no', 'OLD')->value('party_id'));
        $this->assertSame($buyer->id, Haul::query()->where('ticket_no', 'NEW')->value('party_id'));
    }

    public function test_haul_is_rejected_when_the_vehicle_had_no_owner_that_day(): void
    {
        $this->vehicle->currentOwnership->update(['started_on' => now()->subDay()->toDateString()]);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['hauled_at' => now()->subDays(10)->format('Y-m-d\TH:i')]))
            ->assertSessionHasErrors('plate');

        $this->assertDatabaseCount('hauls', 0);
        $this->assertDatabaseCount('ledger_entries', 0);
    }

    public function test_vehicle_specific_route_price_wins_over_the_class_price(): void
    {
        RouteVehiclePrice::query()->create([
            'route_id' => $this->route->id,
            'vehicle_id' => $this->vehicle->id,
            'price' => 3100,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload());

        $this->assertSame('3100.00', Haul::query()->sole()->price);
    }

    public function test_duplicate_ticket_unknown_plate_and_unknown_route_are_rejected(): void
    {
        Haul::factory()->create(['ticket_no' => 'F-100']);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload([
                'plate' => '06 ZZ 999',
                'dump_site_id' => DumpSite::factory()->create()->id,
            ]))
            ->assertSessionHasErrors([
                'ticket_no',
                'plate',
                'dump_site_id' => 'Bu şantiye ile döküm yeri arasında aktif güzergah yok.',
            ]);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload(['ticket_no' => 'F-101', 'site_id' => 'not-a-uuid']))
            ->assertSessionHasErrors(['site_id']);

        $this->assertDatabaseCount('hauls', 1);
    }

    public function test_closed_route_is_rejected_for_new_hauls_but_kept_on_existing_ones(): void
    {
        $haul = Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
        ]);
        $this->route->update(['active' => false]);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload())
            ->assertSessionHasErrors(['dump_site_id']);

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $haul), $this->payload(['ticket_no' => $haul->ticket_no]))
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.create'))
            ->assertViewHas('options', fn (array $options) => ! array_key_exists($this->route->site_id, $options['sites']));

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.edit', $haul))
            ->assertViewHas('options', fn (array $options) => array_key_exists($this->route->site_id, $options['sites'])
                && array_key_exists($this->route->dump_site_id, $options['dumpSites']));
    }

    public function test_closing_a_site_hides_it_from_the_entry_form(): void
    {
        $this->route->site->update(['active' => false]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.create'))
            ->assertViewHas('options', fn (array $options) => $options['sites'] === []);

        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload())
            ->assertSessionHasErrors(['dump_site_id']);
    }

    public function test_stored_haul_takes_site_and_dump_site_from_the_route(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload());

        $haul = Haul::query()->sole();
        $this->assertSame($this->route->id, $haul->route_id);
        $this->assertSame($this->route->site_id, $haul->site_id);
        $this->assertSame($this->route->dump_site_id, $haul->dump_site_id);
    }

    public function test_update_keeps_the_saved_price_unless_the_vehicle_or_route_changes(): void
    {
        $haul = Haul::factory()->create([
            'vehicle_id' => $this->vehicle->id,
            'route_id' => $this->route->id,
            'price' => 10,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $haul), $this->payload([
                'ticket_no' => $haul->ticket_no,
                'tonnage' => 22,
            ]))
            ->assertRedirect(route('admin.hauls.index'))
            ->assertSessionHas('success');

        $haul->refresh();
        $this->assertSame('10.00', $haul->price);
        $this->assertSame(22, $haul->tonnage);

        $other = HaulRoute::factory()->create(['site_id' => $this->route->site_id]);
        RouteClassPrice::query()->create(['route_id' => $other->id, 'vehicle_class_id' => $this->vehicle->vehicle_class_id, 'price' => 3300]);

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $haul), $this->payload([
                'ticket_no' => $haul->ticket_no,
                'dump_site_id' => $other->dump_site_id,
            ]))
            ->assertSessionHasNoErrors();

        $haul->refresh();
        $this->assertSame('3300.00', $haul->price);
        $this->assertSame($other->dumpSite->party_id, $haul->dump_party_id);
    }

    public function test_update_reverses_the_old_ledger_lines_and_posts_new_ones(): void
    {
        $this->actingAs($this->admin)->post(route('admin.hauls.store'), $this->payload());
        $haul = Haul::query()->sole();
        $owner = $haul->party_id;

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $haul), $this->payload(['ticket_no' => 'F-100', 'tonnage' => 30]));
        $this->assertSame(2, LedgerEntry::query()->count(), 'Changing only the tonnage posts nothing.');

        $other = HaulRoute::factory()->create(['site_id' => $this->route->site_id]);
        RouteClassPrice::query()->create(['route_id' => $other->id, 'vehicle_class_id' => $this->vehicle->vehicle_class_id, 'price' => 3000]);

        $this->actingAs($this->admin)
            ->put(route('admin.hauls.update', $haul), $this->payload(['ticket_no' => 'F-100', 'dump_site_id' => $other->dump_site_id]));

        $this->assertSame(2, LedgerEntry::query()->whereNotNull('reversal_of')->count());
        $this->assertSame(-3000.0, (float) LedgerEntry::query()->where('party_id', $owner)->sum(DB::raw('debit - credit')));
        $this->assertSame(0.0, (float) LedgerEntry::query()->where('party_id', $this->route->dumpSite->party_id)->sum(DB::raw('debit - credit')));
    }

    public function test_admin_can_delete_a_haul_and_its_ledger_lines_are_reversed(): void
    {
        $this->actingAs($this->admin)->post(route('admin.hauls.store'), $this->payload());
        $haul = Haul::query()->sole();

        $this->actingAs($this->admin)
            ->delete(route('admin.hauls.destroy', $haul))
            ->assertRedirect(route('admin.hauls.index'));

        $this->assertSoftDeleted($haul);
        $this->assertSame(4, LedgerEntry::query()->count());
        $this->assertSame(0.0, (float) LedgerEntry::query()->sum(DB::raw('debit - credit')));
    }

    public function test_list_shows_recent_hauls_by_default_and_filters_on_search(): void
    {
        Haul::factory()->create(['ticket_no' => 'RECENT-1', 'vehicle_id' => $this->vehicle->id]);
        Haul::factory()->create(['ticket_no' => 'OLD-1', 'created_at' => now()->subDays(10)]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index'))
            ->assertOk()
            ->assertSee('RECENT-1')
            ->assertDontSee('OLD-1');

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['f' => ['ticket' => 'OLD%']]))
            ->assertOk()
            ->assertSee('OLD-1')
            ->assertDontSee('RECENT-1');

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['f' => ['plate' => 'abc12']]))
            ->assertOk()
            ->assertSee('RECENT-1')
            ->assertDontSee('OLD-1');
    }

    public function test_list_sorts_by_a_whitelisted_column_in_both_directions(): void
    {
        Haul::factory()->create(['ticket_no' => 'CHEAP', 'price' => 1000]);
        Haul::factory()->create(['ticket_no' => 'PRICEY', 'price' => 9000]);
        Haul::factory()->create(['ticket_no' => 'MIDDLE', 'price' => 5000]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'price', 'dir' => 'asc']))
            ->assertOk()
            ->assertSeeInOrder(['CHEAP', 'MIDDLE', 'PRICEY'])
            ->assertSee('aria-sort="ascending"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'price', 'dir' => 'desc']))
            ->assertOk()
            ->assertSeeInOrder(['PRICEY', 'MIDDLE', 'CHEAP'])
            ->assertSee('aria-sort="descending"', false);
    }

    public function test_list_sorts_by_a_related_column(): void
    {
        $zeta = HaulRoute::factory()->create(['site_id' => Site::factory()->create(['name' => 'ZETA ŞANTİYE'])->id]);
        $alfa = HaulRoute::factory()->create(['site_id' => Site::factory()->create(['name' => 'ALFA ŞANTİYE'])->id]);

        Haul::factory()->create(['ticket_no' => 'AT-ZETA', 'route_id' => $zeta->id]);
        Haul::factory()->create(['ticket_no' => 'AT-ALFA', 'route_id' => $alfa->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'site']))
            ->assertOk()
            ->assertSeeInOrder(['AT-ALFA', 'AT-ZETA']);
    }

    public function test_unknown_sort_column_is_rejected(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.hauls.index', ['sort' => 'driver_name; drop table hauls']))
            ->assertRedirect(route('admin.hauls.index'));
    }

    public function test_create_page_is_displayed(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.hauls.create'))
            ->assertOk()
            ->assertSee('Yeni taşıma fişi')
            ->assertSee('name="site_id"', false)
            ->assertSee('name="dump_site_id"', false)
            ->assertSee('name="trips"', false)
            ->assertSeeInOrder(['name="tonnage"', 'name="trips"', 'value="1"'], false)
            ->assertSee($this->route->site->name);
    }

    public function test_a_single_trip_is_stored_once(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), $this->payload())
            ->assertSessionHas('success', 'Taşıma fişi kaydedildi.');

        $this->assertDatabaseCount('hauls', 1);
    }

    public function test_edit_page_prefills_the_form(): void
    {
        $haul = Haul::factory()->create(['vehicle_id' => $this->vehicle->id, 'route_id' => $this->route->id]);

        $this->actingAs($this->admin)
            ->get(route('admin.hauls.edit', $haul))
            ->assertOk()
            ->assertSee($haul->ticket_no.' numaralı fişi güncelleyin', false)
            ->assertSee('value="34 ABC 123"', false);
    }

    public function test_quote_lookup_explains_the_price_a_haul_would_get(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.quote', $this->quoteQuery(['plate' => '34abc123'])))
            ->assertOk()
            ->assertJson([
                'price' => '2500.00',
                'dump_price' => '400.00',
                'source' => 'class',
                'class' => $this->vehicle->vehicleClass->name,
                'dump_priced' => true,
            ]);

        RouteVehiclePrice::query()->create([
            'route_id' => $this->route->id,
            'vehicle_id' => $this->vehicle->id,
            'price' => 3100,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.quote', $this->quoteQuery()))
            ->assertOk()
            ->assertJson(['price' => '3100.00', 'source' => 'vehicle']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.quote', $this->quoteQuery(['plate' => '06 ZZ 999'])))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.quote', $this->quoteQuery(['dump_site' => 'x'])))
            ->assertNotFound();

        $this->route->update(['active' => false]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.quote', $this->quoteQuery()))
            ->assertNotFound();

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.quote', $this->quoteQuery(['current' => $this->route->id])))
            ->assertOk();
    }

    public function test_dump_site_lookup_lists_only_destinations_with_an_open_route(): void
    {
        $closed = HaulRoute::factory()->create(['site_id' => $this->route->site_id, 'active' => false]);
        HaulRoute::factory()->create();

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.dump-sites', ['site' => $this->route->site_id]))
            ->assertOk()
            ->assertExactJson([['id' => $this->route->dump_site_id, 'name' => $this->route->dumpSite->name]]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.dump-sites', ['site' => $this->route->site_id, 'current' => $closed->id]))
            ->assertOk()
            ->assertJsonCount(2);

        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.dump-sites', ['site' => 'x']))
            ->assertOk()
            ->assertExactJson([]);
    }

    public function test_lookup_returns_matching_vehicles(): void
    {
        $this->actingAs($this->admin)
            ->getJson(route('admin.hauls.lookups.vehicles', ['q' => '34 abc']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.value', '34 ABC 123')
            ->assertJsonStructure([['value', 'meta']]);
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'F-100',
            'plate' => '34 ABC 123',
            'trips' => 1,
            'hauled_at' => now()->format('Y-m-d\TH:i'),
            'site_id' => $this->route->site_id,
            'dump_site_id' => $this->route->dump_site_id,
            'tonnage' => 18,
            'shift' => 'day',
            ...$overrides,
        ];
    }

    /**
     * @param  array<string, string>  $overrides
     * @return array<string, string>
     */
    private function quoteQuery(array $overrides = []): array
    {
        return [
            'plate' => '34 ABC 123',
            'site' => $this->route->site_id,
            'dump_site' => $this->route->dump_site_id,
            ...$overrides,
        ];
    }
}
