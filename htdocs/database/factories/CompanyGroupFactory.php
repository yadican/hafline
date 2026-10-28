<?php

namespace Database\Factories;

use App\Models\CompanyGroup;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<CompanyGroup>
 */
class CompanyGroupFactory extends Factory
{
    protected $model = CompanyGroup::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->company(), 'UTF-8').' GRUBU',
            'active' => true,
        ];
    }
}
