<?php

namespace Database\Factories;

use App\Models\Employee;
use App\Models\EmployeePeriod;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePeriod>
 */
class EmployeePeriodFactory extends Factory
{
    protected $model = EmployeePeriod::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'employee_id' => Employee::factory(),
            'started_on' => fake()->dateTimeBetween('-1 year', '-1 month')->format('Y-m-d'),
            'ended_on' => null,
        ];
    }

    public function ended(?string $endedOn = null): static
    {
        return $this->state(fn (array $attributes) => [
            'ended_on' => $endedOn ?? now()->toDateString(),
        ]);
    }
}
