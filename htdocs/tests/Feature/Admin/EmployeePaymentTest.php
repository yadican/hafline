<?php

namespace Tests\Feature\Admin;

use App\Enums\AccountType;
use App\Enums\PaymentMethod;
use App\Models\Bank;
use App\Models\Employee;
use App\Models\EmployeePayment;
use App\Models\EmployeeSalary;
use App\Models\User;
use App\Repositories\PartyRepository;
use App\Services\Ledger\Ledger;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class EmployeePaymentTest extends TestCase
{
    use RefreshDatabase;

    private User $admin;

    private Bank $cashBox;

    private Bank $bankAccount;

    protected function setUp(): void
    {
        parent::setUp();

        $this->admin = User::factory()->yonetici()->create();
        $this->cashBox = Bank::factory()->create(['type' => AccountType::Cash]);
        $this->bankAccount = Bank::factory()->create(['type' => AccountType::Bank]);
    }

    public function test_admin_can_create_update_and_delete_a_payment(): void
    {
        $employee = Employee::factory()->create(['salary' => 28000]);

        $this->actingAs($this->admin)
            ->get(route('admin.employee-payments.create'))
            ->assertOk()
            ->assertSee('data-employee-balance-form', false)
            ->assertSee('data-employee-balance', false);

        $this->actingAs($this->admin)
            ->post(route('admin.employee-payments.store'), [
                'employee_id' => $employee->id,
                'amount' => '5000',
                'payment_method' => PaymentMethod::Cash->value,
                'bank_id' => $this->cashBox->id,
                'year' => 2026,
                'month' => 9,
                'occurred_on' => '2026-09-15',
                'description' => 'Avans',
            ])
            ->assertRedirect(route('admin.employee-payments.index'))
            ->assertSessionHas('success');

        $payment = EmployeePayment::query()->firstOrFail();
        $this->assertSame('5000.00', $payment->amount);
        $this->assertSame('2026-09-01', $payment->period->toDateString());

        $this->assertTrue(
            EmployeeSalary::query()
                ->where('employee_id', $employee->id)
                ->whereDate('period', '2026-09-01')
                ->where('base_salary', 28000)
                ->where('worked_days', 30)
                ->exists()
        );

        $this->actingAs($this->admin)
            ->put(route('admin.employee-payments.update', $payment), [
                'employee_id' => $employee->id,
                'amount' => '6000',
                'payment_method' => PaymentMethod::BankTransfer->value,
                'bank_id' => $this->bankAccount->id,
                'year' => 2026,
                'month' => 9,
                'occurred_on' => '2026-09-16',
                'description' => 'Maaş',
            ])
            ->assertRedirect(route('admin.employee-payments.index'));

        $this->assertSame('6000.00', $payment->fresh()->amount);

        $this->actingAs($this->admin)
            ->delete(route('admin.employee-payments.destroy', $payment))
            ->assertRedirect(route('admin.employee-payments.index'));

        $this->assertSoftDeleted($payment);
    }

    public function test_payment_and_its_salary_card_post_to_the_employee_cari_and_follow_edits(): void
    {
        $employee = Employee::factory()->create(['salary' => 28000]);
        $ledger = app(Ledger::class);

        $this->actingAs($this->admin)
            ->post(route('admin.employee-payments.store'), [
                'employee_id' => $employee->id,
                'amount' => '5000',
                'payment_method' => PaymentMethod::Cash->value,
                'bank_id' => $this->cashBox->id,
                'year' => 2026,
                'month' => 9,
                'occurred_on' => '2026-09-15',
            ])
            ->assertSessionHasNoErrors();

        $payment = EmployeePayment::query()->sole();
        $salary = $ledger->live(EmployeeSalary::query()->sole())->sole();
        $this->assertSame($employee->party_id, $salary->party_id);
        $this->assertSame('2026-09-30', $salary->occurred_on->toDateString());
        $this->assertSame('28000.00', $salary->credit);

        $paid = $ledger->live($payment)->sole();
        $this->assertSame($employee->party_id, $paid->party_id);
        $this->assertSame('5000.00', $paid->debit);
        $this->assertSame(PaymentMethod::Cash, $paid->payment_method);

        $this->actingAs($this->admin)
            ->put(route('admin.employee-payments.update', $payment), [
                'employee_id' => $employee->id,
                'amount' => '6000',
                'payment_method' => PaymentMethod::BankTransfer->value,
                'bank_id' => $this->bankAccount->id,
                'year' => 2026,
                'month' => 9,
                'occurred_on' => '2026-09-16',
            ])
            ->assertSessionHasNoErrors();

        $paid = $ledger->live($payment)->sole();
        $this->assertSame('6000.00', $paid->debit);
        $this->assertSame(PaymentMethod::BankTransfer, $paid->payment_method);
        $this->assertSame($this->bankAccount->id, $paid->bank_id);

        $this->actingAs($this->admin)->delete(route('admin.employee-payments.destroy', $payment));

        $this->assertCount(0, $ledger->live($payment));
        $this->assertSame(-28000.0, app(PartyRepository::class)->totals($employee->party)['balance']);
    }

    public function test_creating_payment_does_not_duplicate_existing_salary_card(): void
    {
        $employee = Employee::factory()->create(['salary' => 28000]);

        EmployeeSalary::factory()->create([
            'employee_id' => $employee->id,
            'period' => '2026-09-01',
            'base_salary' => 28000,
            'worked_days' => 22,
            'earned_salary' => 20533.33,
        ]);

        $this->actingAs($this->admin)
            ->post(route('admin.employee-payments.store'), [
                'employee_id' => $employee->id,
                'amount' => '1000',
                'year' => 2026,
                'month' => 9,
                'occurred_on' => '2026-09-20',
            ])
            ->assertSessionHas('success');

        $this->assertSame(1, EmployeeSalary::query()->where('employee_id', $employee->id)->count());
        $this->assertSame(22, EmployeeSalary::query()->first()->worked_days);
    }
}
