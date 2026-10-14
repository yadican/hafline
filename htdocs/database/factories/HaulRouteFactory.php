<?php

namespace Database\Factories;

use App\Models\DumpSite;
use App\Models\HaulRoute;
use App\Models\Site;
use Illuminate\Database\Eloquent\Factories\Factory;

/**
 * @extends Factory<HaulRoute>
 */
class HaulRouteFactory extends Factory
{
    protected $model = HaulRoute::class;

    /**
     * @return array<string, mixed>
     */
    public function definition(): array
    {
        return [
            'site_id' => Site::factory(),
            'dump_site_id' => DumpSite::factory(),
            'active' => true,
        ];
    }
}
