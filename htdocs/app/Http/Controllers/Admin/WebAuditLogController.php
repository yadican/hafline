<?php

namespace App\Http\Controllers\Admin;

use App\Http\Requests\Admin\AuditLogSearchRequest;
use App\Models\AuditLog;
use App\Models\User;
use App\Services\AuditLogService;
use Illuminate\View\View;

class WebAuditLogController extends AdminController
{
    protected ?string $permissionAbility = 'view-web-audit-log';

    public function __construct(private AuditLogService $logs) {}

    public function index(AuditLogSearchRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.web-audit-logs.index', [
            'title' => 'Web işlem logları',
            'logs' => $this->logs->paginate(AuditLog::CHANNEL_WEB, $filters, $request->sort()),
            'filters' => $filters,
            'users' => User::query()->orderBy('name')->get(['id', 'name', 'username']),
            'indexRoute' => 'admin.web-audit-logs.index',
            'showRoute' => 'admin.web-audit-logs.show',
        ]);
    }

    public function show(AuditLog $auditLog): View
    {
        $log = $this->logs->findForChannel(AuditLog::CHANNEL_WEB, $auditLog);

        return view('admin.web-audit-logs.show', [
            'title' => 'Web işlem logu',
            'log' => $log,
            'indexRoute' => 'admin.web-audit-logs.index',
            'channelLabel' => 'Web',
        ]);
    }
}
