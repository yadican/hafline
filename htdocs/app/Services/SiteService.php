<?php

namespace App\Services;

use App\Exceptions\OperationRejectedException;
use App\Models\Site;
use App\Repositories\SiteRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class SiteService extends Service
{
    public function __construct(private SiteRepository $sites) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 15): LengthAwarePaginator
    {
        return $this->sites->search($filters, $sort, $perPage);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): Site
    {
        /** @var Site */
        return $this->transaction(fn () => $this->sites->create($attributes));
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(Site $site, array $attributes): Site
    {
        /** @var Site */
        return $this->transaction(fn () => $this->sites->update($site, $attributes));
    }

    public function delete(Site $site): void
    {
        if ($site->routes()->exists()) {
            throw new OperationRejectedException('Bu şantiyeye bağlı güzergahlar var. Şantiyeyi silmek yerine pasif yapabilirsiniz.');
        }

        $this->transaction(fn () => $this->sites->delete($site));
    }
}
