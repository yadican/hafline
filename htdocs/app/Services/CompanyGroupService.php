<?php

namespace App\Services;

use App\Cache\FormOptionsCache;
use App\Exceptions\OperationRejectedException;
use App\Models\CompanyGroup;
use App\Repositories\CompanyGroupRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class CompanyGroupService extends Service
{
    public function __construct(private CompanyGroupRepository $groups) {}

    /**
     * @param  array{q?: string, active?: string}  $filters
     */
    public function paginate(array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->groups->search($filters, $sort, $perPage);
    }

    /**
     * @return array<string, string>
     */
    public function options(?string $include = null): array
    {
        return $this->groups->options($include);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function create(array $attributes): CompanyGroup
    {
        return $this->transaction(function () use ($attributes): CompanyGroup {
            /** @var CompanyGroup $group */
            $group = $this->groups->create($attributes);
            FormOptionsCache::flush(FormOptionsCache::COMPANY_GROUPS);

            return $group;
        });
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    public function update(CompanyGroup $group, array $attributes): CompanyGroup
    {
        return $this->transaction(function () use ($group, $attributes): CompanyGroup {
            /** @var CompanyGroup $updated */
            $updated = $this->groups->update($group, $attributes);
            FormOptionsCache::flush(FormOptionsCache::COMPANY_GROUPS);

            return $updated;
        });
    }

    public function delete(CompanyGroup $group): void
    {
        if ($this->groups->hasCompanies($group)) {
            throw new OperationRejectedException('Bu grupta şirket var; silinemez. Önce şirketleri gruptan çıkarın veya grubu pasife alın.');
        }

        $this->transaction(function () use ($group): void {
            $this->groups->delete($group);
            FormOptionsCache::flush(FormOptionsCache::COMPANY_GROUPS);
        });
    }
}
