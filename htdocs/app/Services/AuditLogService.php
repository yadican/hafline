<?php

namespace App\Services;

use App\Models\AuditLog;
use App\Repositories\AuditLogRepository;
use App\Support\Sort;
use Illuminate\Contracts\Pagination\LengthAwarePaginator;

class AuditLogService extends Service
{
    public function __construct(private AuditLogRepository $logs) {}

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
    public function paginate(string $channel, array $filters = [], ?Sort $sort = null, int $perPage = 20): LengthAwarePaginator
    {
        return $this->logs->search($channel, $filters, $sort, $perPage);
    }

    public function findForChannel(string $channel, AuditLog $log): AuditLog
    {
        abort_unless($log->channel === $channel, 404);

        return $log->loadMissing('user');
    }
}
