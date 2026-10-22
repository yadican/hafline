<?php

namespace Database\Factories;

use App\Enums\PaymentMethod;
use App\Models\Employee;
use App\Models\EmployeePayment;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<EmployeePayment>
 */
class EmployeePaymentFactory extends Factory
{
    protected $model = EmployeePayment::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        $period = now()->subMonthNoOverflow()->startOfMonth()->toDateString();

        return [
            'employee_id' => Employee::factory(),
            'payment_method' => fake()->randomElement(PaymentMethod::cases()),
            'amount' => fake()->randomFloat(2, 1000, 50000),
            'period' => $period,
            'occurred_on' => fake()->dateTimeBetween($period, 'now')->format('Y-m-d'),
            'description' => fake()->optional()->sentence(),
            'created_by' => null,
        ];
    }
}
