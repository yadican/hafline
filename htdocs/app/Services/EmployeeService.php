<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Cache\PartyCache;
use App\Enums\PartyRole;
use App\Enums\PartyType;
use App\Exceptions\OperationRejectedException;
use App\Models\Employee;
use App\Models\EmployeeSalary;
use App\Models\Party;
use App\Repositories\EmployeeRepository;
use App\Repositories\EmployeeSalaryRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Carbon;

class EmployeeService extends Service
{
    private const EMPLOYEE_FIELDS = ['started_on', 'occupation', 'salary', 'bank_account_no', 'active'];

    public function __construct(
        private EmployeeRepository $employees,
        private EmployeeSalaryRepository $salaries,
    ) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->employees->search($filters, $sort, $perPage);
    }

    /**
     * @return array<string, string>
     */
    public function options(?string $include = null, bool $withPeriodOnly = false): array
    {
        return $this->employees->options($include, $withPeriodOnly);
    }

    /**
     * Persons that can receive a new payroll card (no active employee yet).
     *
     * @return array<string, string>
     */
    public function availablePartyOptions(?string $include = null): array
    {
        return $this->employees->availablePartyOptions($include);
    }

    /**
     * Backfill payroll cards for parties that already have the Employee role but no employees row
     * (e.g. role was assigned before this sync existed).
     */
    public function syncMissingFromRoles(): void
    {
        $parties = Party::query()
            ->where('type', PartyType::Person)
            ->withRole(PartyRole::Employee)
            ->whereDoesntHave('employee')
            ->with('roleAssignments')
            ->get();

        if ($parties->isEmpty()) {
            return;
        }

        foreach ($parties as $party) {
            $this->ensureForParty($party);
        }

        $this->flushCaches();
    }

    /**
     * Ensure a payroll card exists when the party has the Employee role (e.g. from Cari form).
     */
    public function ensureForParty(Party $party): ?Employee
    {
        if ($party->type !== PartyType::Person || ! $party->hasRole(PartyRole::Employee)) {
            return null;
        }

        $existing = Employee::query()->where('party_id', $party->id)->first();

        if ($existing !== null) {
            return $existing;
        }

        /** @var Employee $employee */
        $employee = $this->employees->create([
            'party_id' => $party->id,
            'started_on' => null,
            'salary' => 0,
            'active' => true,
        ]);

        $employee->periods()->create([
            'started_on' => null,
            'ended_on' => null,
        ]);

        return $employee;
    }

    /**
     * @return array{name: string, carry: float, earned: float, bonus: float, paid: float, balance: float}
     */
    public function balanceSummary(Employee $employee, string $period): array
    {
        return [
            'name' => $employee->name,
            ...$this->salaries->balanceSummary($employee->id, $period),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    public function formValues(Employee $employee): array
    {
        $employee->loadMissing(['party.person', 'periods']);

        $openPeriod = $employee->periods->first(fn ($period) => $period->ended_on === null);

        return [
            ...$employee->only(self::EMPLOYEE_FIELDS),
            'open_period_started_on' => $openPeriod?->started_on?->toDateString(),
            'has_open_period' => $openPeriod !== null,
        ];
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function create(array $data): Employee
    {
        return $this->transaction(function () use ($data): Employee {
            $party = Party::query()->findOrFail($data['party_id']);

            if ($party->type !== PartyType::Person) {
                throw new OperationRejectedException('Personel kartı yalnızca kişi cari kartına bağlanabilir.');
            }

            if (Employee::query()->where('party_id', $party->id)->exists()) {
                throw new OperationRejectedException('Bu kişinin zaten personel kartı var.');
            }

            $party->assignRole(PartyRole::Employee);

            /** @var Employee $employee */
            $employee = $this->employees->create([
                ...$this->employeeAttributes($data),
                'party_id' => $party->id,
            ]);

            $employee->periods()->create([
                'started_on' => $data['started_on'] ?? null,
                'ended_on' => null,
            ]);

            $this->flushCaches();

            return $employee;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     */
    public function update(Employee $employee, array $data): Employee
    {
        return $this->transaction(function () use ($employee, $data): Employee {
            $employee->loadMissing(['periods']);

            $previousSalary = (float) $employee->salary;

            /** @var Employee $updated */
            $updated = $this->employees->update($employee, $this->employeeAttributes($data));

            $this->syncEmploymentPeriod($updated, $data);

            if (round((float) $updated->salary, 2) !== round($previousSalary, 2)) {
                $this->syncCurrentMonthBaseSalary($updated);
            }

            $this->flushCaches();

            return $updated->fresh(['party.person', 'periods']);
        });
    }

    public function delete(Employee $employee): void
    {
        if ($employee->salaries()->exists() || $employee->payments()->exists() || $employee->bonuses()->exists()) {
            throw new OperationRejectedException('Bu personelin maaş, ödeme veya prim kaydı var; silinemez. Kullanılmayacaksa pasife alın.');
        }

        $this->transaction(function () use ($employee): void {
            $party = $employee->party;
            $employee->periods()->delete();
            $this->employees->delete($employee);

            $party?->roleAssignments()->where('role', PartyRole::Employee)->delete();
            $party?->unsetRelation('roleAssignments');

            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        PartyCache::flush();
        FormOptionsCache::flush(FormOptionsCache::PARTIES, FormOptionsCache::EMPLOYEES);
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function syncEmploymentPeriod(Employee $employee, array $data): void
    {
        $open = $employee->periods()->whereNull('ended_on')->latest('started_on')->first();

        if (! empty($data['exit_on'])) {
            if ($open === null) {
                throw new OperationRejectedException('Açık iş dönemi yok; çıkış kaydedilemez.');
            }

            $exitOn = Carbon::parse($data['exit_on'])->toDateString();
            $started = $open->started_on?->toDateString();

            if ($started !== null && $exitOn < $started) {
                throw new OperationRejectedException('İşten çıkış tarihi, giriş tarihinden önce olamaz.');
            }

            $open->update(['ended_on' => $exitOn]);
            $open = null;
        }

        if (! empty($data['rehire_on'])) {
            if ($open !== null) {
                throw new OperationRejectedException('Zaten açık bir iş dönemi var; önce çıkış kaydedin.');
            }

            $employee->periods()->create([
                'started_on' => Carbon::parse($data['rehire_on'])->toDateString(),
                'ended_on' => null,
            ]);
        }
    }

    private function syncCurrentMonthBaseSalary(Employee $employee): void
    {
        $salary = EmployeeSalary::query()
            ->where('employee_id', $employee->id)
            ->whereDate('period', now()->startOfMonth()->toDateString())
            ->first();

        if ($salary === null) {
            return;
        }

        $salary->update([
            'base_salary' => $employee->salary,
            'earned_salary' => round((float) $employee->salary / 30 * (int) $salary->worked_days, 2),
        ]);
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array<string, mixed>
     */
    private function employeeAttributes(array $data): array
    {
        return array_intersect_key($data, array_flip(self::EMPLOYEE_FIELDS));
    }
}
