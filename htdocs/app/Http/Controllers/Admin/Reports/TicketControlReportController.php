<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\TicketControlReportRequest;
use App\Services\Reports\TicketControlReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class TicketControlReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-ticket-control';

    public function __construct(private TicketControlReportService $reports) {}

    public function index(TicketControlReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.ticket-control.index', [
            'title' => 'Hareket fişi kontrol',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(TicketControlReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
