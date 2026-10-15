<?php

namespace Database\Seeders;

use App\Models\City;
use App\Models\District;
use App\Models\Neighborhood;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

/**
 * Turkish cities, districts and neighborhoods/villages (TurkiyeAPI 2025 compilation).
 *
 * Safe to re-run with refreshed JSON files: rows are matched by official code (district and
 * neighborhood by name and type within their parent), names are updated and ids are kept, so
 * parties linked to a location stay linked. Rows missing from the files are left untouched.
 */
class LocationSeeder extends Seeder
{
    public function run(): void
    {
        $path = database_path('seeders/data/locations');
        $now = now();

        $cities = $this->read($path.'/cities.json');
        City::query()->upsert(
            array_map(fn (array $city) => [
                'id' => (string) Str::uuid7(),
                'code' => (int) $city['code'],
                'name' => $city['name'],
                'created_at' => $now,
                'updated_at' => $now,
            ], $cities),
            ['code'],
            ['name', 'updated_at'],
        );
        $cityIds = City::query()->pluck('id', 'code');

        $districts = array_values(array_filter(
            $this->read($path.'/districts.json'),
            fn (array $district) => isset($cityIds[(int) $district['city_code']]),
        ));

        foreach (array_chunk($districts, 500) as $chunk) {
            District::query()->upsert(
                array_map(fn (array $district) => [
                    'id' => (string) Str::uuid7(),
                    'city_id' => $cityIds[(int) $district['city_code']],
                    'code' => (int) $district['code'],
                    'name' => $district['name'],
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk),
                ['code'],
                ['city_id', 'name', 'updated_at'],
            );
        }
        $districtIds = District::query()->pluck('id', 'code');

        $neighborhoods = array_values(array_filter(
            $this->read($path.'/neighborhoods.json'),
            fn (array $neighborhood) => isset($districtIds[(int) $neighborhood['district_code']]),
        ));

        foreach (array_chunk($neighborhoods, 1000) as $chunk) {
            Neighborhood::query()->upsert(
                array_map(fn (array $neighborhood) => [
                    'id' => (string) Str::uuid7(),
                    'district_id' => $districtIds[(int) $neighborhood['district_code']],
                    'name' => $neighborhood['name'],
                    'type' => $neighborhood['type'] ?? 'mahalle',
                    'postal_code' => $neighborhood['postal_code'] ?: null,
                    'created_at' => $now,
                    'updated_at' => $now,
                ], $chunk),
                ['district_id', 'name', 'type'],
                ['postal_code', 'updated_at'],
            );
        }

        $this->command?->info(sprintf('%d il, %d ilçe, %d mahalle / köy işlendi.', count($cities), count($districts), count($neighborhoods)));
    }

    /**
     * @return list<array<string, mixed>>
     */
    private function read(string $file): array
    {
        return json_decode((string) file_get_contents($file), true, flags: JSON_THROW_ON_ERROR);
    }
}
