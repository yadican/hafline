<?php

namespace Database\Factories;

use App\Models\DumpSite;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<DumpSite>
 */
class DumpSiteFactory extends Factory
{
    protected $model = DumpSite::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'name' => mb_strtoupper(fake()->unique()->city(), 'UTF-8'),
            'active' => true,
        ];
    }
}
