<?php

namespace App\Services;

use App\Cache\VehicleClassCache;
use App\Exceptions\OperationRejectedException;
use App\Models\VehicleClass;
use App\Repositories\VehicleClassRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class VehicleClassService extends Service
{
    public function __construct(private VehicleClassRepository $classes) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = VehicleClassCache::PER_PAGE): LengthAwarePaginator
    {
        return VehicleClassCache::rememberFirstPage(
            $filters,
            $sort,
            fn () => $this->classes->search($filters, $sort, $perPage),
        );
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): VehicleClass
    {
        /** @var VehicleClass */
        return $this->transaction(fn () => $this->classes->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(VehicleClass $class, array $attributes): VehicleClass
    {
        /** @var VehicleClass */
        return $this->transaction(fn () => $this->classes->update($class, $attributes));
    }

    public function delete(VehicleClass $class): void
    {
        if ($class->vehicles()->exists()) {
            throw new OperationRejectedException('Bu sınıfta kayıtlı araçlar var. Önce araçları başka bir sınıfa taşıyın.');
        }

        $this->transaction(fn () => $this->classes->delete($class));
    }
}
