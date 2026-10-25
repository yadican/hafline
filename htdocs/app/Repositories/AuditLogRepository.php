<?php

namespace App\Repositories;

use App\Models\AuditLog;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Carbon;

class AuditLogRepository extends Repository
{
    public function __construct(AuditLog $model)
    {
        parent::__construct($model);
    }

    /**
     * @param  array{
     *     q?: string,
     *     action?: string,
     *     method?: string,
     *     ip?: string,
     *     user_id?: string,
     *     from?: string,
     *     to?: string
     * }  $filters
     */
    public function search(string $channel, array $filters, ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        $query = $this->query()
            ->with('user')
            ->where('channel', $channel)
            ->when($filters['q'] ?? null, function (Builder $query, string $term): void {
                $like = $this->likePattern($term);

                $query->where(function (Builder $query) use ($like): void {
                    $query->whereLike('route', $like)
                        ->orWhereLike('url', $like)
                        ->orWhereLike('ip_address', $like);
                });
            })
            ->when($filters['action'] ?? null, fn (Builder $query, string $action) => $query->where('action', $action))
            ->when($filters['method'] ?? null, fn (Builder $query, string $method) => $query->where('method', $method))
            ->when($filters['ip'] ?? null, fn (Builder $query, string $ip) => $query->where('ip_address', $ip))
            ->when($filters['user_id'] ?? null, fn (Builder $query, string $userId) => $query->where('user_id', $userId))
            ->when($filters['from'] ?? null, fn (Builder $query, string $from) => $query->where('created_at', '>=', Carbon::parse($from)->startOfDay()))
            ->when($filters['to'] ?? null, fn (Builder $query, string $to) => $query->where('created_at', '<', Carbon::parse($to)->addDay()->startOfDay()));

        return $this->applySort($query, $sort)
            ->paginate($perPage)
            ->withQueryString();
    }

    protected function sortColumns(): array
    {
        return [
            'created_at' => 'created_at',
            'action' => 'action',
            'method' => 'method',
            'status' => 'status',
            'route' => 'route',
        ];
    }

    protected function defaultSort(Builder $query): void
    {
        $query->orderByDesc('created_at');
    }
}
