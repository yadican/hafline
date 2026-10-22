<?php

namespace Database\Factories;

use App\Models\Role;
use App\Models\User;
use App\Support\ApiPermissions;
use Database\Seeders\RolePermissionSeeder;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;

/**
 * @extends Factory<User>
 */
class UserFactory extends Factory
{
    /**
     * The current password being used by the factory.
     */
    protected static ?string $password;

    /**
     * Define the model's default state.
     *
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => fake()->name(),
            'username' => fake()->unique()->userName(),
            'email' => fake()->unique()->safeEmail(),
            'email_verified_at' => now(),
            'password' => static::$password ??= Hash::make('HafLine34!'), // password
            'remember_token' => Str::random(10),
            'active' => true,
        ];
    }

    /**
     * Indicate that the model's email address should be unverified.
     */
    public function unverified(): static
    {
        return $this->state(fn (array $attributes) => [
            'email_verified_at' => null,
        ]);
    }

    /**
     * Assign the seeded yonetici role (all permissions).
     */
    public function yonetici(): static
    {
        return $this->afterCreating(function (User $user) {
            (new RolePermissionSeeder)->run();
            $user->syncRoles(['yonetici']);
        });
    }

    /**
     * Assign the seeded api-operator role (api guard → field-haul.operate).
     */
    public function apiOperator(): static
    {
        return $this->afterCreating(function (User $user) {
            (new RolePermissionSeeder)->run();
            $user->assignRole(
                Role::findByName('api-operator', ApiPermissions::GUARD)
            );
        });
    }
}
