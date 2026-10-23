<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
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
        return $this->transaction(function () use ($attributes): VehicleClass {
            /** @var VehicleClass $class */
            $class = $this->classes->create($attributes);
            $this->flushCaches();

            return $class;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(VehicleClass $class, array $attributes): VehicleClass
    {
        return $this->transaction(function () use ($class, $attributes): VehicleClass {
            /** @var VehicleClass $updated */
            $updated = $this->classes->update($class, $attributes);
            $this->flushCaches();

            return $updated;
        });
    }

    public function delete(VehicleClass $class): void
    {
        if ($class->vehicles()->exists()) {
            throw new OperationRejectedException('Bu sınıfta kayıtlı araçlar var. Önce araçları başka bir sınıfa taşıyın.');
        }

        $this->transaction(function () use ($class): void {
            $this->classes->delete($class);
            $this->flushCaches();
        });
    }

    private function flushCaches(): void
    {
        VehicleClassCache::flush();
        FormOptionsCache::flush(FormOptionsCache::VEHICLE_CLASSES);
    }
}
