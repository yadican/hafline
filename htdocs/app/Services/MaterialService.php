<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\Material;
use App\Models\Shipment;
use App\Repositories\MaterialRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class MaterialService extends Service
{
    public function __construct(private MaterialRepository $materials) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->materials->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Material
    {
        /** @var Material */
        return $this->transaction(fn () => $this->materials->create($attributes));
    }

    /**
     * The unit is fixed once shipments use the material, since their quantities are in it.
     *
     * @param  array<string, mixed>  $attributes
     */
    public function update(Material $material, array $attributes): Material
    {
        if ($attributes['unit'] !== $material->unit->value && Shipment::query()->where('material_id', $material->id)->exists()) {
            throw new OperationRejectedException('Bu malzemeyle sefer girilmiş; birimi değiştirilemez.');
        }

        /** @var Material */
        return $this->transaction(fn () => $this->materials->update($material, $attributes));
    }

    public function delete(Material $material): void
    {
        if (Shipment::query()->where('material_id', $material->id)->exists()) {
            throw new OperationRejectedException('Bu malzemeyle sefer girilmiş; silmek yerine pasife alın.');
        }

        $this->transaction(fn () => $this->materials->delete($material));
    }
}
