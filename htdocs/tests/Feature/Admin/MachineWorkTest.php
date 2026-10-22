<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Models\LedgerEntry;
use App\Models\MachineWork;
use App\Models\Party;
use App\Models\Site;
use App\Models\User;
use App\Models\Vehicle;
use App\Models\VehicleClass;
use App\Repositories\PartyRepository;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class MachineWorkTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Party $owner;

    private Vehicle $machine;

    private Site $site;

    private Party $operator;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->owner = Party::factory()->create();
        $this->machine = Vehicle::factory()
            ->ownedBy($this->owner, '2026-01-01')
            ->create(['plate' => '34 MK 100', 'vehicle_class_id' => VehicleClass::factory()->hourly()]);
        $this->site = Site::factory()->create();
        $this->operator = Party::factory()->person()->withRole(PartyRole::MachineOperator)->create(['name' => 'Kemal Usta']);

        $this->machine->rates()->create(['hourly_rate' => 1200, 'started_on' => '2026-01-01', 'ended_on' => '2026-05-31']);
        $this->machine->rates()->create(['hourly_rate' => 1500, 'started_on' => '2026-06-01']);
    }

    public function test_guest_is_redirected_from_the_machine_work_page(): void
    {
        $this->get(route('admin.machine-works.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_ticket_takes_the_rate_of_its_date_and_credits_the_owner(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['worked_on' => '2026-05-20', 'hours' => '4,5']))
            ->assertRedirect(route('admin.machine-works.index'))
            ->assertSessionHas('success');

        $work = MachineWork::query()->sole();

        $this->assertSame('1200.00', $work->hourly_rate);
        $this->assertSame('4.50', $work->hours);
        $this->assertSame($this->owner->id, $work->party_id);
        $this->assertSame($this->admin->id, $work->created_by);

        $entry = LedgerEntry::query()->sole();
        $this->assertSame('machine_work', $entry->source_type);
        $this->assertSame($this->owner->id, $entry->party_id);
        $this->assertSame('5400.00', $entry->credit);
        $this->assertSame($this->machine->id, $entry->vehicle_id);
    }

    public function test_operator_must_have_the_machine_operator_role(): void
    {
        $stranger = Party::factory()->person()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['operator_id' => $stranger->id]))
            ->assertSessionHasErrors('operator_id');

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['operator_id' => '']))
            ->assertSessionHasNoErrors();

        $this->assertNull(MachineWork::query()->sole()->operator_id);
    }

    public function test_a_freelance_operator_is_credited_hours_times_their_rate(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['operator_id' => $this->operator->id, 'operator_rate' => '']))
            ->assertSessionHasErrors('operator_rate');

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['operator_id' => $this->operator->id, 'operator_rate' => '250', 'hours' => '8']))
            ->assertSessionHasNoErrors();

        $work = MachineWork::query()->sole();
        $this->assertSame('250.00', $work->operator_rate);
        $this->assertSame(-2000.0, $this->balance($this->operator));
        $this->assertSame(-12000.0, $this->balance());

        $this->actingAs($this->admin)
            ->put(route('admin.machine-works.update', $work), $this->payload(['operator_id' => '', 'operator_rate' => '250']));

        $this->assertNull($work->fresh()->operator_rate);
        $this->assertSame(0.0, $this->balance($this->operator));
        $this->assertSame(-12000.0, $this->balance());
    }

    public function test_a_salaried_operator_is_never_credited(): void
    {
        $staff = Party::factory()->person()->withRole(PartyRole::Employee, PartyRole::MachineOperator)->create();

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['operator_id' => $staff->id, 'operator_rate' => '300']))
            ->assertSessionHasNoErrors();

        $work = MachineWork::query()->sole();
        $this->assertSame($staff->id, $work->operator_id);
        $this->assertNull($work->operator_rate);
        $this->assertSame(1, LedgerEntry::query()->count());

        $this->actingAs($this->admin)
            ->getJson(route('admin.machine-works.lookups.operator', ['id' => $staff->id]))
            ->assertOk()
            ->assertJson(['salaried' => true, 'rate' => null]);
    }

    public function test_operator_lookup_offers_the_rate_of_their_latest_ticket(): void
    {
        MachineWork::factory()->create(['operator_id' => $this->operator->id, 'operator_rate' => 200, 'worked_on' => '2026-06-01']);
        MachineWork::factory()->create(['operator_id' => $this->operator->id, 'operator_rate' => 275, 'worked_on' => '2026-06-05']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.machine-works.lookups.operator', ['id' => $this->operator->id]))
            ->assertOk()
            ->assertJson(['salaried' => false, 'rate' => '275.00']);
    }

    public function test_form_lists_operators_and_the_list_filters_by_operator(): void
    {
        $other = Party::factory()->person()->withRole(PartyRole::MachineOperator)->create(['name' => 'Hasan Demir']);
        Party::factory()->person()->create(['name' => 'Rolsüz Kişi']);

        $this->actingAs($this->admin)
            ->get(route('admin.machine-works.create'))
            ->assertSee('Kemal Usta')
            ->assertSee('Hasan Demir')
            ->assertDontSee('Rolsüz Kişi');

        MachineWork::factory()->create(['ticket_no' => 'M-KEMAL', 'operator_id' => $this->operator->id, 'worked_on' => today()]);
        MachineWork::factory()->create(['ticket_no' => 'M-HASAN', 'operator_id' => $other->id, 'worked_on' => today()]);

        $this->actingAs($this->admin)
            ->get(route('admin.machine-works.index', ['f' => ['operator' => $other->id]]))
            ->assertSee('M-HASAN')
            ->assertDontSee('M-KEMAL');

        $this->assertContains(PartyRole::MachineOperator, app(PartyRepository::class)->lockedRoles($other));
    }

    public function test_a_typed_rate_overrides_the_period_rate(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['hourly_rate' => '1750', 'hours' => '2']));

        $this->assertSame('1750.00', MachineWork::query()->sole()->hourly_rate);
        $this->assertSame('3500.00', LedgerEntry::query()->sole()->credit);
    }

    public function test_vehicles_that_are_not_billed_hourly_are_rejected(): void
    {
        Vehicle::factory()->create(['plate' => '34 TR 1']);

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['plate' => '34 TR 1']))
            ->assertSessionHasErrors('plate');

        $this->assertSame(0, MachineWork::query()->count());
    }

    public function test_a_blank_rate_needs_a_rate_period_on_the_date(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['worked_on' => '2025-12-31']))
            ->assertSessionHasErrors(['hourly_rate', 'plate']);

        $other = Vehicle::factory()->create(['plate' => '34 MK 200', 'vehicle_class_id' => $this->machine->vehicle_class_id]);

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['plate' => $other->plate]))
            ->assertSessionHasErrors('hourly_rate');

        $this->assertSame(0, MachineWork::query()->count());
    }

    public function test_duplicate_ticket_numbers_are_rejected(): void
    {
        MachineWork::factory()->create(['ticket_no' => 'M-1']);

        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['ticket_no' => 'M-1']))
            ->assertSessionHasErrors('ticket_no');
    }

    public function test_updating_reposts_and_deleting_reverses_the_ledger(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['hours' => '8']));
        $work = MachineWork::query()->sole();

        $this->actingAs($this->admin)
            ->put(route('admin.machine-works.update', $work), $this->payload(['hours' => '10', 'hourly_rate' => '1500']))
            ->assertRedirect(route('admin.machine-works.index'));

        $this->assertSame('10.00', $work->fresh()->hours);
        $this->assertSame(-15000.0, $this->balance());

        $this->actingAs($this->admin)
            ->delete(route('admin.machine-works.destroy', $work))
            ->assertRedirect(route('admin.machine-works.index'));

        $this->assertSoftDeleted($work);
        $this->assertSame(0.0, $this->balance());
    }

    public function test_list_and_forms_render(): void
    {
        $work = MachineWork::factory()->create(['vehicle_id' => $this->machine->id, 'party_id' => $this->owner->id, 'site_id' => $this->site->id]);

        $this->actingAs($this->admin)->get(route('admin.machine-works.index'))->assertOk()->assertSee($work->ticket_no);
        $this->actingAs($this->admin)->get(route('admin.machine-works.create'))->assertOk();
        $this->actingAs($this->admin)->get(route('admin.machine-works.edit', $work))->assertOk()->assertSee('34 MK 100');
    }

    public function test_lookups_list_only_machines_and_quote_the_rate_of_the_date(): void
    {
        Vehicle::factory()->create(['plate' => '34 MK 900']);

        $this->actingAs($this->admin)
            ->getJson(route('admin.machine-works.lookups.machines', ['q' => '34MK']))
            ->assertOk()
            ->assertJsonCount(1)
            ->assertJsonPath('0.value', '34 MK 100');

        $this->actingAs($this->admin)
            ->getJson(route('admin.machine-works.lookups.rate', ['plate' => '34mk100', 'date' => '2026-03-01']))
            ->assertOk()
            ->assertJsonPath('rate', '1200.00')
            ->assertJsonPath('since', '01.01.2026');
    }

    public function test_transferring_the_machine_moves_its_tickets_to_the_new_owner(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.machine-works.store'), $this->payload(['worked_on' => '2026-06-10', 'hours' => '2']));

        $buyer = Party::factory()->create();

        $this->actingAs($this->admin)
            ->post(route('admin.vehicles.ownerships.store', $this->machine), ['party_id' => $buyer->id, 'started_on' => '2026-06-05'])
            ->assertSessionHas('success');

        $this->assertSame($buyer->id, MachineWork::query()->sole()->party_id);
        $this->assertSame(0.0, $this->balance());
        $this->assertSame(-3000.0, $this->balance($buyer));
    }

    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    private function payload(array $overrides = []): array
    {
        return [
            'ticket_no' => 'M-100',
            'plate' => '34 MK 100',
            'worked_on' => '2026-06-10',
            'shift' => 'day',
            'site_id' => $this->site->id,
            'operator_id' => '',
            'hours' => '8',
            'hourly_rate' => '',
            ...$overrides,
        ];
    }

    private function balance(?Party $party = null): float
    {
        return (float) LedgerEntry::query()
            ->where('party_id', ($party ?? $this->owner)->id)
            ->selectRaw('COALESCE(SUM(debit - credit), 0) AS balance')
            ->value('balance');
    }
}
