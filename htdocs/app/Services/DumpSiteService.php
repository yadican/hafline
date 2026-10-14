<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\DumpSite;
use App\Repositories\DumpSiteRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class DumpSiteService extends Service
{
    public function __construct(private DumpSiteRepository $dumpSites) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->dumpSites->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): DumpSite
    {
        /** @var DumpSite */
        return $this->transaction(fn () => $this->dumpSites->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(DumpSite $dumpSite, array $attributes): DumpSite
    {
        /** @var DumpSite */
        return $this->transaction(fn () => $this->dumpSites->update($dumpSite, $attributes));
    }

    public function delete(DumpSite $dumpSite): void
    {
        if ($dumpSite->routes()->exists()) {
            throw new OperationRejectedException('Bu döküm yerine bağlı güzergahlar var. Döküm yerini silmek yerine pasif yapabilirsiniz.');
        }

        $this->transaction(fn () => $this->dumpSites->delete($dumpSite));
    }
}
