<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\OwnerBalanceReportRequest;
use App\Services\Reports\OwnerBalanceReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class OwnerBalanceReportController extends AdminController
{
    public function __construct(private OwnerBalanceReportService $reports) {}

    public function index(OwnerBalanceReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.owner-balance.index', [
            'title' => 'Plaka sahibi bakiye',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(OwnerBalanceReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
