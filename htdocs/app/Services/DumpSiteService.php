<?php

namespace App\Services;

use App\Enums\PartyRole;
use App\Exceptions\OperationRejectedException;
use App\Models\DumpSite;
use App\Repositories\DumpSiteRepository;
use App\Repositories\PartyRepository;
use App\Repositories\VehicleClassRepository;
use App\Services\Concerns\SyncsClassPrices;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Support\Arr;

class DumpSiteService extends Service
{
    use SyncsClassPrices;

    public function __construct(
        private DumpSiteRepository $dumpSites,
        private VehicleClassRepository $classes,
        private PartyRepository $parties,
    ) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->dumpSites->search($filters, $sort, $perPage);
    }

    /**
     * @return array<string, string>
     */
    public function classOptions(?DumpSite $dumpSite = null): array
    {
        return $this->classes->options(activeOnly: true, include: $dumpSite?->classPrices->pluck('vehicle_class_id')->all());
    }

    /**
     * @return array<string, string>
     */
    public function partyOptions(?DumpSite $dumpSite = null): array
    {
        return $this->parties->options(include: $dumpSite?->party_id);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DumpSite
    {
        $prices = Arr::pull($attributes, 'class_prices', []);

        /** @var DumpSite */
        return $this->transaction(function () use ($attributes, $prices): DumpSite {
            /** @var DumpSite $dumpSite */
            $dumpSite = $this->dumpSites->create($attributes);
            $this->syncClassPrices($dumpSite, 'classPrices', $prices);
            $dumpSite->party->assignRole(PartyRole::DumpOperator);

            return $dumpSite;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(DumpSite $dumpSite, array $attributes): DumpSite
    {
        $prices = Arr::pull($attributes, 'class_prices', []);

        /** @var DumpSite */
        return $this->transaction(function () use ($dumpSite, $attributes, $prices): DumpSite {
            /** @var DumpSite $updated */
            $updated = $this->dumpSites->update($dumpSite, $attributes);
            $this->syncClassPrices($updated, 'classPrices', $prices);
            $updated->party->assignRole(PartyRole::DumpOperator);

            return $updated;
        });
    }

    public function delete(DumpSite $dumpSite): void
    {
        if ($dumpSite->routes()->exists()) {
            throw new OperationRejectedException('Bu döküm yerine bağlı güzergahlar var. Döküm yerini silmek yerine pasif yapabilirsiniz.');
        }

        $this->transaction(fn () => $this->dumpSites->delete($dumpSite));
    }
}
