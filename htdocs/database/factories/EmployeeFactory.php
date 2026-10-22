<?php

namespace Database\Factories;

use App\Enums\EmployeeOccupation;
use App\Enums\PartyRole;
use App\Models\Employee;
use App\Models\Party;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Employee>
 */
class EmployeeFactory extends Factory
{
    protected $model = Employee::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'party_id' => Party::factory()->person()->withRole(PartyRole::Employee),
            'started_on' => fake()->dateTimeBetween('-2 years', '-1 month')->format('Y-m-d'),
            'occupation' => fake()->randomElement(EmployeeOccupation::cases()),
            'salary' => fake()->randomElement([28000, 35000, 42000, 55000]),
            'bank_account_no' => fake()->optional()->numerify('TR########################'),
            'active' => true,
        ];
    }

    public function configure(): static
    {
        return $this->afterCreating(function (Employee $employee): void {
            if ($employee->periods()->doesntExist()) {
                $employee->periods()->create([
                    'started_on' => $employee->started_on,
                    'ended_on' => null,
                ]);
            }
        });
    }

    public function inactive(): static
    {
        return $this->state(['active' => false]);
    }
}
