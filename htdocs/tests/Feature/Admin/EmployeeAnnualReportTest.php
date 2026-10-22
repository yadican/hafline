<?php

namespace Tests\Feature\Admin;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeeAnnualReportTest extends TestCase
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
        $this->get(route('admin.reports.employee-annual'))
            ->assertRedirect(route('admin.login'));
    }

    public function test_report_builds_ledger_with_opening_and_running_balance(): void
    {
        $employee = Employee::factory()->create();
        $employee->party->update(['name' => 'Ali Güneş']);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2025-12-01',
            'earned_salary' => 8000,
        ]);

        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 3000,
            'period' => '2025-12-01',
            'occurred_on' => '2025-12-20',
        ]);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-01-01',
            'earned_salary' => 10000,
        ]);

        EmployeeBonus::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 500,
            'occurred_on' => '2026-01-15',
            'description' => 'Prim',
        ]);

        EmployeePayment::factory()->create([
            'employee_id' => $employee->id,
            'amount' => 4000,
            'period' => '2026-01-01',
            'occurred_on' => '2026-01-25',
            'description' => 'Ödeme',
        ]);

        $response = $this->actingAs($this->admin)
            ->get(route('admin.reports.employee-annual', [
                'employee_id' => $employee->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertSee('Ali Güneş')
            ->assertSee('Geçmiş Yıllar Devir');

        $report = $response->viewData('report');
        $this->assertSame(5000.0, $report['opening']); // 8000 - 3000
        $this->assertSame(5000.0, $report['lines'][0]['balance']);
        $this->assertSame(10000.0, $report['totals']['salary']);
        $this->assertSame(500.0, $report['totals']['bonus']);
        $this->assertSame(4000.0, $report['totals']['payment']);
        $this->assertSame(11500.0, $report['totals']['closing']); // 5000 + 10000 + 500 - 4000
    }

    public function test_export_downloads_csv(): void
    {
        $employee = Employee::factory()->create();

        $this->actingAs($this->admin)
            ->get(route('admin.reports.employee-annual.export', [
                'employee_id' => $employee->id,
                'year' => 2026,
            ]))
            ->assertOk()
            ->assertHeader('content-disposition');
    }
}
