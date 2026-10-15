<?php

namespace App\Repositories;

use App\Models\City;
use App\Models\District;
use App\Models\Neighborhood;

class LocationRepository
{
    /**
     * @return array<string, string>
     */
    public function cities(): array
    {
        return City::query()->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function districts(?string $cityId): array
    {
        if (! $cityId) {
            return [];
        }

        return District::query()->where('city_id', $cityId)->orderBy('name')->pluck('name', 'id')->all();
    }

    /**
     * @return array<string, string>
     */
    public function neighborhoods(?string $districtId): array
    {
        if (! $districtId) {
            return [];
        }

        return Neighborhood::query()
            ->where('district_id', $districtId)
            ->orderBy('name')
            ->get(['id', 'name', 'type'])
            ->mapWithKeys(fn (Neighborhood $neighborhood) => [$neighborhood->id => $neighborhood->label])
            ->all();
    }
}
