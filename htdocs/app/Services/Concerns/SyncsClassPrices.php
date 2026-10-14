<?php

namespace App\Services\Concerns;

use App\Models\VehicleClass;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

trait SyncsClassPrices
{
    /**
     * A blank value removes the class price; classes missing from the input keep their price.
     *
     * @param  array<string, mixed>  $prices  vehicle_class_id => price
     */
    protected function syncClassPrices(Model $owner, string $relation, array $prices): void
    {
        $known = VehicleClass::query()->whereIn('id', array_keys($prices))->pluck('id');

        foreach ($known as $classId) {
            /** @var HasMany<Model, Model> $query */
            $query = $owner->{$relation}();
            $existing = $query->where('vehicle_class_id', $classId)->first();
            $price = $prices[$classId];

            if ($price === null || $price === '') {
                $existing?->delete();

                continue;
            }

            $existing
                ? $existing->update(['price' => $price])
                : $owner->{$relation}()->create(['vehicle_class_id' => $classId, 'price' => $price]);
        }
    }
}
