<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AuditLogSearchRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\View\View;

class ApiAuditLogController extends AdminController
{
    protected ?string $permissionAbility = 'view-api-audit-log';

    public function __construct(private AuditLogService $logs) {}

    public function index(AuditLogSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.api-audit-logs.index', [
            'title' => 'API işlem logları',
            'logs' => $this->logs->paginate(AuditLog::CHANNEL_API, $filters, $request->sort()),
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'indexRoute' => 'admin.api-audit-logs.index',
            'showRoute' => 'admin.api-audit-logs.show',
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $log = $this->logs->findForChannel(AuditLog::CHANNEL_API, $auditLog);

        return view('admin.api-audit-logs.show', [
            'title' => 'API işlem logu',
            'log' => $log,
            'indexRoute' => 'admin.api-audit-logs.index',
            'channelLabel' => 'API',
        ]);
    }
}
