<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeMonthlyReportTest extends TestCase
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
        $this->get(route('admin.reports.employee-monthly'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_page_shows_filters_without_running(): void
    {
        $this->actingAs($this->admin)
            ->get(route('admin.reports.employee-monthly'))
            ->assertOk()
            ->assertViewHas('ready', false);
    }

    public function test_report_calculates_remaining_with_earned_bonus_and_carry(): void
    {
        $employee = Employee::factory()->create();
        $employee->party->update(['name' => 'Ahmet Çetin']);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-08-01',
            'earned_salary' => 10000,
        ]);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-09-01',
            'base_salary' => 30000,
            'worked_days' => 15,
            'earned_salary' => 15000,
        ]);

        EmployeeBonus::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 2000,
            'occurred_on' => '2026-09-05',
        ]);

        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 4000,
            'period' => '2026-08-01',
            'occurred_on' => '2026-08-20',
        ]);

        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 3000,
            'period' => '2026-09-01',
            'occurred_on' => '2026-09-20',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.employee-monthly', ['year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertSee('Ahmet Çetin');

        $report = $response->viewData('report');
        $row = $report['rows'][0];

        $this->assertSame(15000.0, $row['earned']);
        $this->assertSame(2000.0, $row['bonus']);
        $this->assertSame(6000.0, $row['carry']); // 10000 - 4000
        $this->assertSame(3000.0, $row['paid']);
        $this->assertSame(20000.0, $row['remaining']); // 15000 + 2000 + 6000 - 3000
    }

    public function test_export_downloads_csv(): void
    {
        $employee = Employee::factory()->create();
        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-09-01',
            'earned_salary' => 10000,
        ]);

        $this->actingAs($this->admin)
            ->get(route('admin.reports.employee-monthly.export', ['year' => 2026, 'month' => 9]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
