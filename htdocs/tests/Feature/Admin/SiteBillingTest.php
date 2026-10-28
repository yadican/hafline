<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Enums\SiteBilling;
use App\Models\Bank;
use App\Models\DumpSiteClassPrice;
use App\Models\Haul;
use App\Models\HaulRoute;
use App\Models\LedgerEntry;
use App\Models\Party;
use App\Models\RouteClassPrice;
use App\Models\RouteSitePrice;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehiclePayment;
use App\Repositories\BankRepository;
use App\Services\PartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class SiteBillingTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private HaulRoute $route;

    private Party $contractor;

    private Vehicle $truck;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->route = HaulRoute::factory()->create();
        $this->contractor = Party::factory()->company()->create();
        $this->truck = Vehicle::factory()->ownedBy(Party::factory()->create(), '2026-01-01')->create(['plate' => '34 TR 100']);

        RouteClassPrice::query()->create(['route_id' => $this->route->id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 2500]);
        DumpSiteClassPrice::query()->create(['dump_site_id' => $this->route->dump_site_id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 400]);
        RouteSitePrice::query()->create(['route_id' => $this->route->id, 'vehicle_class_id' => $this->truck->vehicle_class_id, 'price' => 3000]);
    }

    public function test_a_per_trip_site_owes_the_site_price_for_every_haul_and_its_collection_credits_it_once(): void
    {
        $this->billSite(SiteBilling::PerTrip);

        $this->haul('34 TR 100', 2);

        $this->assertSame(6000.0, $this->balance($this->contractor));
        $this->assertTrue($this->contractor->fresh()->hasRole(PartyRole::Site));

        $cashBox = Bank::factory()->create(['type' => AccountType::Cash]);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'site',
                'direction' => 'credit',
                'site_id' => $this->route->site_id,
                'amount' => '4000',
                'occurred_on' => '2026-03-12',
                'payment_method' => PaymentMethod::Cash->value,
                'bank_id' => $cashBox->id,
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(2000.0, $this->balance($this->contractor));
        $this->assertSame(4000.0, app(BankRepository::class)->balances([$cashBox->id])[$cashBox->id]);

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_a_progress_billed_site_is_charged_by_its_bills_not_by_hauls(): void
    {
        $this->billSite(SiteBilling::Progress);

        $this->haul('34 TR 100', 1);

        $this->assertSame(0.0, $this->balance($this->contractor));

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'site_bill',
                'site_id' => $this->route->site_id,
                'amount' => '15000',
                'occurred_on' => '2026-03-31',
            ])
            ->assertSessionHasNoErrors();

        $this->assertSame(15000.0, $this->balance($this->contractor));

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    public function test_a_site_bill_needs_the_site_to_have_a_cari(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [
                'kind' => 'site_bill',
                'site_id' => $this->route->site_id,
                'amount' => '15000',
                'occurred_on' => '2026-03-31',
            ])
            ->assertSessionHasErrors('site_id');
    }

    public function test_a_temporary_plate_moves_to_its_owner_with_tickets_and_payments(): void
    {
        $temporary = app(PartyService::class)->temporaryParty();
        $stranger = Vehicle::factory()->ownedBy($temporary, '2026-03-01')->create([
            'plate' => '06 YB 123',
            'vehicle_class_id' => $this->truck->vehicle_class_id,
        ]);
        $this->haul('06 YB 123', 1);

        $payment = [
            'kind' => 'vehicle_payment',
            'plate' => '06 YB 123',
            'amount' => '1000',
            'occurred_on' => '2026-03-10',
            'payment_method' => PaymentMethod::Cash->value,
            'bank_id' => Bank::factory()->create(['type' => AccountType::Cash])->id,
        ];

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), $payment)
            ->assertSessionHasErrors(['receiver_name', 'receiver_national_id']);

        $this->actingAs($this->admin)
            ->post(route('admin.cari-entries.store'), [...$payment, 'receiver_name' => 'Ali Veli', 'receiver_national_id' => '12345678901'])
            ->assertSessionHasNoErrors();

        $this->actingAs($this->admin)
            ->get(route('admin.parties.show', $temporary))
            ->assertOk()
            ->assertSee('06 YB 123')
            ->assertSee('1.500,00 TL alacaklı');

        $owner = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.reassign', $stranger), ['party_id' => $owner->id])
            ->assertSessionHas('success');

        $this->assertSame($owner->id, $stranger->currentOwnership()->first()->party_id);
        $this->assertSame($owner->id, Haul::query()->where('vehicle_id', $stranger->id)->sole()->party_id);
        $this->assertSame($owner->id, VehiclePayment::query()->sole()->party_id);
        $this->assertSame(0.0, $this->balance($temporary));
        $this->assertSame(-1500.0, $this->balance($owner));
        $this->assertTrue($owner->fresh()->hasRole(PartyRole::VehicleOwner));

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.reassign', $stranger), ['party_id' => Party::factory()->create()->id])
            ->assertSessionHas('error');

        $this->artisan('ledger:audit')
            ->expectsOutputToContain('Cari, belgeler ve raporlar birebir tutuyor.')
            ->assertSuccessful();
    }

    private function billSite(SiteBilling $billing): void
    {
        $this->actingAs($this->admin)
            ->put(route('admin.sites.update', $this->route->site_id), [
                'name' => $this->route->site->name,
                'party_id' => $this->contractor->id,
                'billing_mode' => $billing->value,
                'active' => '1',
            ])
            ->assertSessionHasNoErrors();
    }

    private function haul(string $plate, int $trips): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.hauls.store'), [
                'ticket_no' => 'F-'.fake()->unique()->numberBetween(100, 999),
                'plate' => $plate,
                'trips' => $trips,
                'hauled_at' => '2026-03-10T10:00',
                'site_id' => $this->route->site_id,
                'dump_site_id' => $this->route->dump_site_id,
                'tonnage' => 18,
                'shift' => 'day',
            ])
            ->assertSessionHasNoErrors();
    }

    /**
     * Positive: the party owes the company.
     */
    private function balance(Party $party): float
    {
        return round((float) LedgerEntry::query()->where('party_id', $party->id)->sum('debit')
            - (float) LedgerEntry::query()->where('party_id', $party->id)->sum('credit'), 2);
    }
}
