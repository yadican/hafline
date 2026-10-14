<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\VehicleOwner;
use App\Repositories\VehicleOwnerRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VehicleOwnerService extends Service
{
    public function __construct(private VehicleOwnerRepository $owners) {}

    /**
     * @param  array{q?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->owners->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VehicleOwner
    {
        /** @var VehicleOwner */
        return $this->transaction(fn () => $this->owners->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(VehicleOwner $owner, array $attributes): VehicleOwner
    {
        /** @var VehicleOwner */
        return $this->transaction(fn () => $this->owners->update($owner, $attributes));
    }

    public function delete(VehicleOwner $owner): void
    {
        if ($owner->vehicles()->exists()) {
            throw new OperationRejectedException('Bu sahibe kayıtlı araçlar var. Önce araçları başka bir sahibe aktarın.');
        }

        $this->transaction(fn () => $this->owners->delete($owner));
    }
}
