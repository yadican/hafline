<?php

namespace App\Http\Controllers\Admin;

use App\Models\City;
use App\Models\District;
use App\Repositories\LocationRepository;
use Illuminate\Http\JsonResponse;

class LocationController extends AdminController
{
    protected ?string $permissionAbility = 'view-party';

    public function __construct(private LocationRepository $locations) {}

    public function districts(City $city): JsonResponse
    {
        return $this->options($this->locations->districts($city->id));
    }

    public function neighborhoods(District $district): JsonResponse
    {
        return $this->options($this->locations->neighborhoods($district->id));
    }

    /**
     * @param  array<string, string>  $options
     */
    private function options(array $options): JsonResponse
    {
        return response()->json(collect($options)->map(fn (string $name, string $id) => ['id' => $id, 'name' => $name])->values());
    }
}
