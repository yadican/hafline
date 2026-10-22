<?php

namespace Tests\Feature\Admin;

use App\Enums\EmployeeOccupation;
use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeSalaryTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->create();
    }

    public function test_guest_is_redirected(): void
    {
        $this->get(route('admin.employee-salaries.index'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.employee-salaries.index'))
            ->assertOk()
            ->assertViewHas('ready', false);
    }

    public function test_grid_lists_employees_and_calculates_payable(): void
    {
        $employee = Employee::factory()->create([
            'salary' => 30000,
            'occupation' => EmployeeOccupation::Driver,
            'started_on' => '2026-01-01',
        ]);
        $employee->party->update(['name' => 'Ali Güneş']);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-08-01',
            'base_salary' => 30000,
            'worked_days' => 30,
            'earned_salary' => 30000,
        ]);

        EmployeeBonus::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 1000,
            'occurred_on' => '2026-09-10',
        ]);

        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 5000,
            'period' => '2026-08-01',
            'occurred_on' => '2026-08-15',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.employee-salaries.index', ['year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertViewHas('ready', true);

        $grid = $response->viewData('grid');
        $this->assertCount(1, $grid['rows']);
        $row = $grid['rows'][0];
        $this->assertSame('Ali Güneş', $row['name']);
        $this->assertSame(25000.0, $row['carry']); // 30000 - 5000
        $this->assertSame(1000.0, $row['bonus']);
        $this->assertSame(30, $row['worked_days']);
        $this->assertSame(30000.0, $row['earned']);
        $this->assertSame(56000.0, $row['payable']); // 25000 + 30000 + 1000
    }

    public function test_cannot_open_current_or_future_month(): void
    {
        Employee::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.employee-salaries.index', [
                'year' => now()->year,
                'month' => now()->month,
            ]))
            ->assertRedirect();
    }

    public function test_save_upserts_salary_cards_with_earned_formula(): void
    {
        $employee = Employee::factory()->create(['salary' => 30000, 'started_on' => '2026-01-01']);

        $this->actingAs($this->admin)
            ->post(route('admin.employee-salaries.store'), [
                'year' => 2026,
                'month' => 9,
                'rows' => [
                    [
                        'employee_id' => $employee->id,
                        'base_salary' => '30000',
                        'worked_days' => '15',
                    ],
                ],
            ])
            ->assertRedirect(route('admin.employee-salaries.index', ['year' => 2026, 'month' => 9]))
            ->assertSessionHas('success');

        $card = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period', '2026-09-01')
            ->firstOrFail();

        $this->assertSame(15, $card->worked_days);
        $this->assertSame('15000.00', $card->earned_salary);

        $this->actingAs($this->admin)
            ->post(route('admin.employee-salaries.store'), [
                'year' => 2026,
                'month' => 9,
                'rows' => [
                    [
                        'employee_id' => $employee->id,
                        'base_salary' => '30000',
                        'worked_days' => '20',
                    ],
                ],
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, EmployeeSalary::query()->where('employee_id', $employee->id)->count());
        $this->assertSame('20000.00', $card->fresh()->earned_salary);
    }
}
