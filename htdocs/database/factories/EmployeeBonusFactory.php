<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeeBonus;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeeBonus>
 */
class EmployeeBonusFactory extends Factory
{
    protected $model = EmployeeBonus::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'amount' => fake()->randomFloat(2, 500, 10000),
            'occurred_on' => fake()->dateTimeBetween('-6 months', 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
