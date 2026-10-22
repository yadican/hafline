<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeSalary;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeSalary>
 */
class EmployeeSalaryFactory extends Factory
{
    protected $model = EmployeeSalary::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $base = fake()->randomElement([28000, 35000, 42000, 55000]);
        $days = fake()->numberBetween(20, 30);

        return [
            'employee_id' => Employee::factory(),
            'period' => now()->subMonthNoOverflow()->startOfMonth()->toDateString(),
            'base_salary' => $base,
            'worked_days' => $days,
            'earned_salary' => round($base / 30 * $days, 2),
        ];
    }
}
