<?php

namespace Tests\Feature\Admin;

use App\Enums\EmployeeOccupation;
use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\Party;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
    }

    public function test_guest_is_redirected_from_the_employee_list(): void
    {
        $this->get(route('admin.employees.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_admin_can_create_update_and_delete_an_employee_linked_to_party(): void
    {
        $party = Party::factory()->person()->create(['name' => 'Ahmet Çetin']);

        $this->actingAs($this->admin)->get(route('admin.employees.create'))->assertOk();

        $this->actingAs($this->admin)
            ->post(route('admin.employees.store'), [
                'party_id' => $party->id,
                'occupation' => EmployeeOccupation::Driver->value,
                'salary' => '35000',
                'started_on' => '2026-01-15',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('success');

        $employee = Employee::query()->firstOrFail();
        $this->assertSame($party->id, $employee->party_id);
        $this->assertSame('Ahmet Çetin', $employee->name);
        $this->assertSame(EmployeeOccupation::Driver, $employee->occupation);
        $this->assertTrue($party->fresh()->hasRole(PartyRole::Employee));
        $this->assertCount(1, $employee->periods);
        $this->assertNull($employee->periods->first()->ended_on);

        $this->actingAs($this->admin)
            ->get(route('admin.employees.show', $employee))
            ->assertOk()
            ->assertSee('Ahmet Çetin')
            ->assertSee('15.01.2026');

        $this->actingAs($this->admin)
            ->put(route('admin.employees.update', $employee), [
                'occupation' => EmployeeOccupation::Operator->value,
                'salary' => '40000',
                'started_on' => '2026-01-15',
                'active' => '1',
                'exit_on' => '2026-09-30',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $employee->refresh();
        $this->assertSame(EmployeeOccupation::Operator, $employee->occupation);
        $this->assertSame('40000.00', $employee->salary);
        $this->assertSame('Ahmet Çetin', $employee->party->fresh()->name);
        $this->assertSame('2026-09-30', $employee->periods()->first()->ended_on?->toDateString());

        $this->actingAs($this->admin)
            ->put(route('admin.employees.update', $employee), [
                'occupation' => EmployeeOccupation::Operator->value,
                'salary' => '40000',
                'started_on' => '2026-01-15',
                'active' => '1',
                'rehire_on' => '2026-10-01',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $this->assertSame(2, $employee->periods()->count());
        $this->assertNotNull($employee->periods()->whereNull('ended_on')->first());

        $this->actingAs($this->admin)
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'));

        $this->assertSoftDeleted($employee);
        $this->assertNotSoftDeleted($party);
        $this->assertFalse($party->fresh()->hasRole(PartyRole::Employee));
    }

    public function test_assigning_employee_role_on_party_creates_payroll_card(): void
    {
        $this->actingAs($this->admin)
            ->post(route('admin.parties.store'), [
                'type' => PartyType::Person->value,
                'first_name' => 'Ali',
                'last_name' => 'Tan',
                'roles' => [PartyRole::Employee->value, PartyRole::MachineOperator->value],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.persons.index'));

        $party = Party::query()->where('name', 'Ali Tan')->firstOrFail();
        $this->assertTrue($party->hasRole(PartyRole::Employee));
        $this->assertNotNull($party->employee);
        $this->assertSame('0.00', $party->employee->salary);

        $this->actingAs($this->admin)
            ->get(route('admin.parties.edit', $party))
            ->assertOk()
            ->assertSee('Personel kartı açık', false)
            ->assertSee('fa-lock', false)
            ->assertDontSee('data-role-confirm-title="Personel rolü açılsın mı?"', false);
    }

    public function test_employee_role_cannot_be_removed_from_cari_while_card_exists(): void
    {
        $party = Party::factory()->person()->withRole(PartyRole::Employee)->create();
        $this->actingAs($this->admin)->get(route('admin.employees.index'));

        $this->assertNotNull($party->fresh()->employee);

        $this->actingAs($this->admin)
            ->put(route('admin.parties.update', $party), [
                'first_name' => $party->person->first_name,
                'last_name' => $party->person->last_name,
                'roles' => [PartyRole::Customer->value],
                'active' => '1',
            ])
            ->assertRedirect(route('admin.parties.edit', $party));

        $this->assertTrue($party->fresh()->hasRole(PartyRole::Employee));
        $this->assertTrue($party->fresh()->hasRole(PartyRole::Customer));
    }

    public function test_index_backfills_cards_for_parties_that_already_have_employee_role(): void
    {
        $party = Party::factory()->person()->withRole(PartyRole::Employee)->create(['name' => 'Ali Tan']);

        $this->assertNull($party->employee);

        $this->actingAs($this->admin)
            ->get(route('admin.employees.index'))
            ->assertOk()
            ->assertSee('Ali Tan');

        $this->assertNotNull($party->fresh()->employee);
    }

    public function test_cannot_attach_second_employee_card_to_same_party(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)
            ->from(route('admin.employees.create'))
            ->post(route('admin.employees.store'), [
                'party_id' => $employee->party_id,
                'salary' => '10000',
                'active' => '1',
            ])
            ->assertRedirect(route('admin.employees.create'))
            ->assertSessionHasErrors('party_id');
    }

    public function test_salary_change_updates_current_month_card(): void
    {
        $employee = Employee::factory()->create(['salary' => 30000]);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => now()->startOfMonth()->toDateString(),
            'base_salary' => 30000,
            'worked_days' => 30,
            'earned_salary' => 30000,
        ]);

        $this->actingAs($this->admin)
            ->put(route('admin.employees.update', $employee), [
                'occupation' => $employee->occupation?->value,
                'salary' => '33000',
                'started_on' => $employee->started_on?->toDateString(),
                'active' => '1',
            ])
            ->assertRedirect(route('admin.employees.index'));

        $card = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period', now()->startOfMonth()->toDateString())
            ->firstOrFail();

        $this->assertSame('33000.00', $card->base_salary);
        $this->assertSame('33000.00', $card->earned_salary);
    }

    public function test_employee_with_salary_cannot_be_deleted(): void
    {
        $employee = Employee::factory()->create();
        EmployeeSalary::factory()->create(['employee_id' => $employee->id]);

        $this->actingAs($this->admin)
            ->from(route('admin.employees.index'))
            ->delete(route('admin.employees.destroy', $employee))
            ->assertRedirect(route('admin.employees.index'))
            ->assertSessionHas('error');

        $this->assertNotSoftDeleted($employee);
    }

    public function test_list_searches_by_party_name(): void
    {
        $match = Employee::factory()->create();
        $match->party->update(['name' => 'Zeynep Ak']);

        $other = Employee::factory()->create();
        $other->party->update(['name' => 'Mehmet Demir']);

        $this->actingAs($this->admin)
            ->get(route('admin.employees.index', ['q' => 'zeynep']))
            ->assertOk()
            ->assertSee('Zeynep Ak')
            ->assertDontSee('Mehmet Demir');
    }

    public function test_balance_endpoint_includes_carry_from_earlier_months(): void
    {
        $employee = Employee::factory()->create();
        $employee->party->update(['name' => 'Ali Tan']);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-08-01',
            'earned_salary' => 28000,
        ]);
        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-08-01',
            'amount' => 10000,
        ]);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-09-01',
            'earned_salary' => 30000,
        ]);
        EmployeeBonus::factory()->create([
            'employee_id' => $employee->id,
            'occurred_on' => '2026-09-12',
            'amount' => 2000,
        ]);
        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-09-01',
            'amount' => 5000,
        ]);

        $this->actingAs($this->admin)
            ->getJson(route('admin.employees.balance', ['employee' => $employee, 'year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertJson([
                'name' => 'Ali Tan',
                'carry' => 18000,
                'earned' => 30000,
                'bonus' => 2000,
                'paid' => 5000,
                'balance' => 45000,
            ]);
    }
}
