<?php

namespace Tests\Feature\Admin;

use App\Enums\PartyRole;
use App\Enums\PartySide;
use App\Enums\PartyType;
use App\Exceptions\OperationRejectedException;
use App\Models\Haul;
use App\Models\Party;
use App\Models\User;
use App\Models\Vehicle;
use App\Services\Ledger\HaulLedger;
use App\Services\PartyService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class PartyTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_party_list(): void
    {
        $this->get(route('admin.persons.index'))
            ->assertRedirect(route('admin.login'));
        $this->get(route('admin.companies.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_and_update_a_person(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parties.create', ['type' => 'person']))
            ->assertOk()
            ->assertSee('name="first_name"', false)
            ->assertDontSee('name="title"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'person',
                'first_name' => 'Ali',
                'last_name' => 'Yılmaz',
                'national_id' => '12345678901',
                'mobile' => '05321234567',
                'roles' => ['customer'],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.persons.index'))
            ->assertSessionHas('success');

        $party = Party::query()->sole();
        $this->assertSame(PartyType::Person, $party->type);
        $this->assertSame('Ali Yılmaz', $party->name);
        $this->assertSame('12345678901', $party->person->national_id);
        $this->assertSame([PartyRole::Customer], $party->roles);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $party))
            ->assertOk()
            ->assertSee('value="Yılmaz"', false)
            ->assertSee('Bu cari kartta henüz hareket yok.');

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $party), [
                'type' => 'company',
                'first_name' => 'Ali',
                'last_name' => 'Yıldız',
                'roles' => ['supplier', 'market'],
            ])
            ->assertRedirect(route('admin.parties.edit', $party));

        $party->refresh();
        $this->assertSame(PartyType::Person, $party->type, 'Type cannot be changed after creation.');
        $this->assertSame('Ali Yıldız', $party->name);
        $this->assertFalse($party->active);
        $this->assertSame([PartyRole::Supplier, PartyRole::Market], $party->roles);
    }

    public function test_admin_can_create_a_company(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'company',
                'title' => 'KAYA HAFRİYAT LTD. ŞTİ.',
                'tax_office' => 'Bağcılar',
                'tax_number' => '1234567890',
                'active' => '1',
            ])
            ->assertSessionHasNoErrors();

        $party = Party::query()->sole();
        $this->assertSame(PartyType::Company, $party->type);
        $this->assertSame('KAYA HAFRİYAT LTD. ŞTİ.', $party->name);
        $this->assertSame('1234567890', $party->company->tax_number);
        $this->assertNull($party->person);
    }

    public function test_identity_numbers_are_unique_among_active_parties(): void
    {
        $person = Party::factory()->create();
        $person->person->update(['national_id' => '11111111111']);

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), ['type' => 'person', 'first_name' => 'A', 'last_name' => 'B', 'national_id' => '11111111111'])
            ->assertSessionHasErrors('national_id');

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $person), ['first_name' => 'A', 'last_name' => 'B', 'national_id' => '11111111111'])
            ->assertSessionHasNoErrors();

        $person->delete();

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), ['type' => 'person', 'first_name' => 'A', 'last_name' => 'B', 'national_id' => '11111111111'])
            ->assertSessionHasNoErrors();
    }

    public function test_required_fields_depend_on_the_type(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), ['type' => 'person'])
            ->assertSessionHasErrors(['first_name', 'last_name'])
            ->assertSessionDoesntHaveErrors('title');

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), ['type' => 'company'])
            ->assertSessionHasErrors('title')
            ->assertSessionDoesntHaveErrors(['first_name', 'last_name']);
    }

    public function test_vehicle_owner_role_stays_while_the_party_has_vehicles(): void
    {
        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner)->create();

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $owner), ['first_name' => 'A', 'last_name' => 'B', 'roles' => []])
            ->assertSessionHasNoErrors();

        $this->assertTrue($owner->fresh()->hasRole(PartyRole::VehicleOwner));
    }

    public function test_party_with_records_cannot_be_deleted(): void
    {
        $owner = Party::factory()->create();
        Vehicle::factory()->ownedBy($owner)->create();
        $unused = Party::factory()->create();

        $this->actingAs($this->admin)
            ->delete(route('admin.parties.destroy', $owner))
            ->assertSessionHas('error');
        $this->assertNotSoftDeleted($owner);

        $this->actingAs($this->admin)
            ->delete(route('admin.parties.destroy', $unused))
            ->assertRedirect(route('admin.persons.index'));
        $this->assertSoftDeleted($unused);
    }

    public function test_list_filters_and_shows_balance(): void
    {
        $owner = Party::factory()->create(['name' => 'Kemal Aslan']);
        $vehicle = Vehicle::factory()->ownedBy($owner)->create();
        $haul = Haul::factory()->create(['vehicle_id' => $vehicle->id, 'party_id' => $owner->id, 'price' => 1250, 'dump_price' => 0]);
        app(HaulLedger::class)->sync($haul);
        Party::factory()->company()->create(['name' => 'ZEYTİN A.Ş.']);

        $this->actingAs($this->admin)
            ->get(route('admin.persons.index'))
            ->assertOk()
            ->assertSee('Kemal Aslan')
            ->assertSee('1.250,00 TL')
            ->assertDontSee('ZEYTİN A.Ş.');

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index', ['sort' => 'balance']))
            ->assertOk()
            ->assertSee('ZEYTİN A.Ş.')
            ->assertDontSee('Kemal Aslan');

        $this->actingAs($this->admin)
            ->get(route('admin.persons.index', ['role' => 'customer']))
            ->assertDontSee('Kemal Aslan');

        $this->actingAs($this->admin)
            ->get(route('admin.persons.index', ['q' => 'kemal asl', 'type' => 'company']))
            ->assertSee('Kemal Aslan');
    }

    public function test_edit_page_lists_the_statement_with_a_running_balance(): void
    {
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner)->create(['plate' => '34 RB 1']);

        foreach ([['T-1', 1000, '-2 days'], ['T-2', 500, '-1 day']] as [$ticket, $price, $when]) {
            $haul = Haul::factory()->create([
                'ticket_no' => $ticket,
                'vehicle_id' => $vehicle->id,
                'party_id' => $owner->id,
                'price' => $price,
                'dump_price' => 0,
                'hauled_at' => now()->modify($when),
            ]);
            app(HaulLedger::class)->sync($haul);
        }

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $owner))
            ->assertOk()
            ->assertSeeInOrder(['Taşıma fişi T-2', '1.500,00 TL', 'Taşıma fişi T-1', '1.000,00 TL'])
            ->assertSee('34 RB 1');
    }

    public function test_statement_and_totals_leave_out_cancelled_postings_unless_asked(): void
    {
        $owner = Party::factory()->create();
        $vehicle = Vehicle::factory()->ownedBy($owner)->create(['plate' => '34 RB 1']);
        $haul = Haul::factory()->create([
            'ticket_no' => 'T-1',
            'vehicle_id' => $vehicle->id,
            'party_id' => $owner->id,
            'price' => 1000,
            'dump_price' => 0,
            'hauled_at' => now()->subDays(2),
        ]);
        app(HaulLedger::class)->sync($haul);
        $haul->update(['price' => 1200]);
        app(HaulLedger::class)->sync($haul->refresh());

        $response = $this->actingAs($this->admin)->get(route('admin.parties.edit', $owner))->assertOk();

        $this->assertSame(['debit' => 0.0, 'credit' => 1200.0, 'balance' => -1200.0], $response->viewData('totals'));
        $this->assertCount(1, $response->viewData('entries'));
        $response->assertSee('İptal edilen kayıtları göster');

        $withReversals = $this->actingAs($this->admin)->get(route('admin.parties.edit', [$owner, 'iptaller' => 1]))->assertOk();

        $this->assertCount(3, $withReversals->viewData('entries'));
        $this->assertSame(-1200.0, (float) $withReversals->viewData('entries')->first()->running_balance);
        $withReversals->assertSee('İptal edilen kayıtları gizle');
    }

    public function test_company_is_listed_separately_and_returns_to_its_list(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), ['type' => 'company', 'title' => 'YILDIZ İNŞAAT A.Ş.', 'tax_number' => '1234567890'])
            ->assertRedirect(route('admin.companies.index'));

        $company = Party::query()->sole();

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index'))
            ->assertSee('YILDIZ İNŞAAT A.Ş.')
            ->assertSee('1234567890')
            ->assertSee('Yeni şirket')
            ->assertDontSee('Yeni kişi');

        $this->actingAs($this->admin)
            ->get(route('admin.persons.index'))
            ->assertDontSee('YILDIZ İNŞAAT A.Ş.');

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $company))
            ->assertSee(route('admin.companies.index'), false);

        $this->actingAs($this->admin)
            ->delete(route('admin.parties.destroy', $company))
            ->assertRedirect(route('admin.companies.index'));
    }

    public function test_person_only_roles_are_hidden_on_company_forms(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parties.create', ['type' => 'company']))
            ->assertOk()
            ->assertDontSee('value="employee"', false)
            ->assertDontSee('value="machine_operator"', false)
            ->assertSee('value="customer"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.create', ['type' => 'person']))
            ->assertOk()
            ->assertSee('value="employee"', false)
            ->assertSee('value="machine_operator"', false);
    }

    public function test_company_cannot_be_assigned_person_only_roles(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'company',
                'title' => 'YANLIŞ PERSONEL A.Ş.',
                'roles' => [PartyRole::Employee->value],
                'active' => '1',
            ])
            ->assertSessionHasErrors('roles.0');

        $this->assertDatabaseCount('parties', 0);

        $company = Party::factory()->company()->create();

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $company), [
                'title' => 'YANLIŞ PERSONEL A.Ş.',
                'roles' => [PartyRole::MachineOperator->value],
            ])
            ->assertSessionHasErrors('roles.0');

        $this->assertFalse($company->fresh()->hasRole(PartyRole::MachineOperator));
    }

    public function test_operator_roles_are_for_companies_and_commission_for_both(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parties.create', ['type' => 'person']))
            ->assertOk()
            ->assertDontSee('value="dump_operator"', false)
            ->assertDontSee('value="fuel"', false)
            ->assertDontSee('value="site"', false)
            ->assertDontSee('value="quarry"', false)
            ->assertSee('value="commission"', false);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.create', ['type' => 'company']))
            ->assertOk()
            ->assertSee('value="site"', false)
            ->assertSee('value="quarry"', false)
            ->assertSee('value="commission"', false);

        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => 'person',
                'first_name' => 'Veli',
                'last_name' => 'Kaya',
                'roles' => [PartyRole::Commission->value, PartyRole::DumpOperator->value],
            ])
            ->assertSessionHasErrors(['roles.1' => 'Döküm işletmecisi yalnızca şirket cari kartlarına verilebilir.'])
            ->assertSessionDoesntHaveErrors('roles.0');

        $this->assertDatabaseCount('parties', 0);
    }

    public function test_a_role_outside_the_party_type_is_refused_at_assignment(): void
    {
        $person = Party::factory()->person()->create();

        $this->expectException(OperationRejectedException::class);

        $person->assignRole(PartyRole::Site);
    }

    public function test_temporary_party_is_created_once_as_a_company_vehicle_owner(): void
    {
        $parties = app(PartyService::class);

        $temporary = $parties->temporaryParty();

        $this->assertTrue($temporary->isTemporary());
        $this->assertSame(PartyType::Company, $temporary->type);
        $this->assertSame('GEÇİCİ CARİ', $temporary->company->title);
        $this->assertTrue($temporary->hasRole(PartyRole::VehicleOwner));
        $this->assertSame($temporary->id, $parties->temporaryParty()->id);
        $this->assertDatabaseCount('parties', 1);
    }

    public function test_temporary_party_cannot_be_renamed_deactivated_or_deleted(): void
    {
        $temporary = app(PartyService::class)->temporaryParty();

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $temporary))
            ->assertOk()
            ->assertSee('Sistem carisidir');

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index'))
            ->assertSee('Sistem')
            ->assertDontSee('Cari kart silinsin mi?');

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $temporary), ['title' => 'BAŞKA AD', 'active' => '1'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $temporary), ['title' => 'GEÇİCİ CARİ'])
            ->assertSessionHas('error');

        $this->actingAs($this->admin)
            ->delete(route('admin.parties.destroy', $temporary))
            ->assertSessionHas('error');

        $temporary->refresh();
        $this->assertSame('GEÇİCİ CARİ', $temporary->name);
        $this->assertTrue($temporary->active);
        $this->assertNotSoftDeleted($temporary);

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $temporary), ['title' => 'GEÇİCİ CARİ', 'active' => '1', 'mobile' => '05321234567'])
            ->assertSessionHas('success');

        $this->assertSame('05321234567', $temporary->fresh()->mobile);
    }

    public function test_list_filters_by_side(): void
    {
        Party::factory()->company()->withRole(PartyRole::Customer)->create(['name' => 'ALICI A.Ş.']);
        Party::factory()->company()->withRole(PartyRole::Supplier)->create(['name' => 'SATICI A.Ş.']);
        Party::factory()->company()->withRole(PartyRole::Market)->create(['name' => 'PİYASA A.Ş.']);

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index', ['side' => PartySide::Receivable->value]))
            ->assertOk()
            ->assertSee('ALICI A.Ş.')
            ->assertSee('PİYASA A.Ş.')
            ->assertDontSee('SATICI A.Ş.');

        $this->actingAs($this->admin)
            ->get(route('admin.companies.index', ['side' => PartySide::Payable->value]))
            ->assertOk()
            ->assertSee('SATICI A.Ş.')
            ->assertSee('PİYASA A.Ş.')
            ->assertDontSee('ALICI A.Ş.');
    }

    public function test_company_create_ignores_incompatible_role_query(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.parties.create', ['type' => 'company', 'role' => PartyRole::Employee->value]))
            ->assertOk()
            ->assertDontSee('value="employee"', false)
            ->assertDontSee('value="machine_operator"', false);
    }
}
