<?php

namespace Database\Seeders\LoadTest;

use App\Enums\EmployeeOccupation;
use App\Enums\PartyRole;
use App\Enums\PaymentMethod;
use App\Models\User;
use Database\Seeders\LoadTest\Support\Progress;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Seed dönemi boyunca personel maaş / hakediş / ödeme / prim verisi.
 */
final class PayrollStage
{
    public function __construct(private Progress $progress) {}

    public function run(CatalogStage $catalog, Carbon $from, Carbon $to): void
    {
        $this->progress->stage('Personel bordro');

        $staff = $catalog->partiesFor(PartyRole::Employee)->unique('id')->values();

        if ($staff->isEmpty()) {
            $this->progress->done('personel yok — atlandı');

            return;
        }

        $userId = User::query()->value('id');
        $occupations = EmployeeOccupation::cases();
        $periods = $this->periods($from);
        $this->progress->note(sprintf(
            '%d personel · %d ay (maaş kartı / ödeme / prim)',
            $staff->count(),
            count($periods),
        ));

        $employees = [];
        $periodRows = [];
        $salaries = [];
        $payments = [];
        $bonuses = [];
        $now = now();

        foreach ($staff->values() as $index => $party) {
            $employeeId = (string) Str::uuid7();
            $baseSalary = fake()->randomElement([28000, 32000, 35000, 38000, 42000, 48000, 55000]);
            $startedOn = $from->copy()->addDays(fake()->numberBetween(0, 20))->toDateString();
            $occupation = $occupations[$index % count($occupations)];

            $employees[] = [
                'id' => $employeeId,
                'party_id' => $party->id,
                'started_on' => $startedOn,
                'occupation' => $occupation->value,
                'salary' => $baseSalary,
                'bank_account_no' => fake()->numerify('TR########################'),
                'active' => true,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $periodRows[] = [
                'id' => (string) Str::uuid7(),
                'employee_id' => $employeeId,
                'started_on' => $startedOn,
                'ended_on' => null,
                'created_at' => $now,
                'updated_at' => $now,
                'deleted_at' => null,
            ];

            $currentBase = $baseSalary;

            foreach ($periods as $monthIndex => $period) {
                // Mid-year raise for roughly every third person.
                if ($monthIndex === 6 && $index % 3 === 0) {
                    $currentBase = round($currentBase * 1.1, 2);
                }

                $days = fake()->numberBetween(18, 26);
                $earned = round($currentBase / 30 * $days, 2);

                $salaries[] = [
                    'id' => (string) Str::uuid7(),
                    'employee_id' => $employeeId,
                    'period' => $period,
                    'base_salary' => $currentBase,
                    'worked_days' => $days,
                    'earned_salary' => $earned,
                    'created_at' => $now,
                    'updated_at' => $now,
                    'deleted_at' => null,
                ];

                // Paid early next month; occasionally partial (avans + kalan).
                $payDate = Carbon::parse($period)->addMonth()->day(fake()->numberBetween(3, 10));

                if ($payDate->lte($to) && fake()->boolean(92)) {
                    $amount = fake()->boolean(15)
                        ? round($earned * fake()->randomFloat(2, 0.4, 0.7), 2)
                        : $earned;

                    $payments[] = [
                        'id' => (string) Str::uuid7(),
                        'employee_id' => $employeeId,
                        'payment_method' => fake()->randomElement([
                            PaymentMethod::BankTransfer->value,
                            PaymentMethod::Cash->value,
                            PaymentMethod::BankTransfer->value,
                        ]),
                        'amount' => max(100, $amount),
                        'period' => $period,
                        'occurred_on' => $payDate->toDateString(),
                        'description' => Carbon::parse($period)->translatedFormat('F Y').' maaşı',
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ];

                    $restDate = $payDate->copy()->addDays(fake()->numberBetween(5, 12));

                    if ($amount < $earned && $restDate->lte($to) && fake()->boolean(60)) {
                        $rest = round($earned - $amount, 2);
                        $payments[] = [
                            'id' => (string) Str::uuid7(),
                            'employee_id' => $employeeId,
                            'payment_method' => PaymentMethod::Cash->value,
                            'amount' => max(100, $rest),
                            'period' => $period,
                            'occurred_on' => $restDate->toDateString(),
                            'description' => 'Kalan ödeme',
                            'created_by' => $userId,
                            'created_at' => $now,
                            'updated_at' => $now,
                            'deleted_at' => null,
                        ];
                    }
                }

                if (fake()->boolean(22)) {
                    $bonuses[] = [
                        'id' => (string) Str::uuid7(),
                        'employee_id' => $employeeId,
                        'amount' => fake()->randomElement([1500, 2500, 4000, 5000, 7500]),
                        'occurred_on' => Carbon::parse($period)->day(min(28, fake()->numberBetween(10, 25)))->toDateString(),
                        'description' => fake()->randomElement(['Saha pirimi', 'Performans', 'Fazla mesai pirimi', 'Bayram ikramiyesi']),
                        'created_by' => $userId,
                        'created_at' => $now,
                        'updated_at' => $now,
                        'deleted_at' => null,
                    ];
                }
            }

            // Keep card default salary at latest base.
            $employees[array_key_last($employees)]['salary'] = $currentBase;
        }

        $this->insert('employees', $employees);
        $this->insert('employee_periods', $periodRows);
        $this->insert('employee_salaries', $salaries);
        $this->insert('employee_payments', $payments);
        $this->insert('employee_bonuses', $bonuses);

        $this->progress->done(sprintf(
            '%d kart · %d maaş · %d ödeme · %d prim',
            count($employees),
            count($salaries),
            count($payments),
            count($bonuses),
        ));
    }

    /**
     * Closed months from seed start through last month before "now" (maaş kartı kuralı).
     *
     * @return list<string>
     */
    private function periods(Carbon $from): array
    {
        $periods = [];
        $cursor = $from->copy()->startOfMonth();
        $last = now()->startOfMonth()->subMonth();

        while ($cursor->lte($last)) {
            $periods[] = $cursor->toDateString();
            $cursor->addMonth();
        }

        return $periods;
    }

    /**
     * @param  list<array<string, mixed>>  $rows
     */
    private function insert(string $table, array $rows): void
    {
        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table($table)->insert($chunk);
        }
    }
}
