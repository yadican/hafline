<?php

namespace Database\Factories;

use App\Enums\ExampleChannel;
use App\Enums\ExampleShift;
use App\Enums\ExampleStatus;
use App\Enums\ExampleTag;
use App\Models\Example;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Example>
 */
class ExampleFactory extends Factory
{
    protected $model = Example::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'title' => fake()->sentence(3),
            'note' => fake()->optional()->sentence(),
            'email' => fake()->optional()->safeEmail(),
            'phone' => fake()->optional()->numerify('05#########'),
            'website' => fake()->optional()->url(),
            'secret' => fake()->optional()->password(8),
            'reference' => fake()->optional()->bothify('SF-####'),
            'quantity' => fake()->optional()->numberBetween(1, 40),
            'amount' => fake()->optional()->randomFloat(2, 100, 5000),
            'event_date' => fake()->optional()->date(),
            'event_time' => fake()->optional()->time('H:i'),
            'starts_at' => fake()->optional()->dateTime(),
            'event_month' => fake()->optional()->date('Y-m'),
            'event_week' => fake()->optional()->date('o-\WW'),
            'color' => fake()->optional()->hexColor(),
            'level' => fake()->numberBetween(0, 100),
            'status' => fake()->randomElement(ExampleStatus::cases()),
            'shift' => fake()->randomElement(ExampleShift::cases()),
            'tags' => fake()->randomElements(ExampleTag::cases(), 2),
            'channels' => fake()->randomElements(ExampleChannel::cases(), 2),
            'active' => fake()->boolean(),
            'source' => 'panel',
        ];
    }
}
