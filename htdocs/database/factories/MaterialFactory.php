<?php

namespace Database\Factories;

use App\Enums\MaterialUnit;
use App\Models\Material;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<Material>
 */
class MaterialFactory extends Factory
{
    protected $model = Material::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->word(), 'UTF-8').' KUM',
            'unit' => MaterialUnit::Ton,
            'active' => true,
        ];
    }
}
