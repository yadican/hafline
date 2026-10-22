<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeBonusTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_admin_can_create_update_and_delete_a_bonus(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.employee-bonuses.create'))
            ->assertOk()
            ->assertSee('data-employee-balance-form', false)
            ->assertSee('data-employee-balance', false);

        $this->actingAs($this->admin)
            ->post(route('admin.employee-bonuses.store'), [
                'employee_id' => $employee->id,
                'amount' => '1500',
                'occurred_on' => '2026-09-12',
                'description' => 'Performans',
            ])
            ->assertRedirect(route('admin.employee-bonuses.index'))
            ->assertSessionHas('success');

        $bonus = EmployeeBonus::query()->firstOrFail();
        $this->assertSame('1500.00', $bonus->amount);

        $this->actingAs($this->admin)
            ->put(route('admin.employee-bonuses.update', $bonus), [
                'employee_id' => $employee->id,
                'amount' => '2000',
                'occurred_on' => '2026-09-12',
                'description' => 'Prim',
            ])
            ->assertRedirect(route('admin.employee-bonuses.index'));

        $this->assertSame('2000.00', $bonus->fresh()->amount);

        $this->actingAs($this->admin)
            ->delete(route('admin.employee-bonuses.destroy', $bonus))
            ->assertRedirect(route('admin.employee-bonuses.index'));

        $this->assertSoftDeleted($bonus);
    }

    public function test_list_filters_by_employee(): void
    {
        $match = Employee::factory()->create();
        $other = Employee::factory()->create();

        EmployeeBonus::factory()->create(['employee_id' => $match->id, 'amount' => 1111]);
        EmployeeBonus::factory()->create(['employee_id' => $other->id, 'amount' => 2222]);

        $this->actingAs($this->admin)
            ->get(route('admin.employee-bonuses.index', ['employee_id' => $match->id]))
            ->assertOk()
            ->assertSee('1.111,00')
            ->assertDontSee('2.222,00');
    }
}
