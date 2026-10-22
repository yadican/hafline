<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\CompanyBalanceReportRequest;
use App\Services\Reports\CompanyBalanceReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyBalanceReportController extends AdminController
{
    public function __construct(private CompanyBalanceReportService $reports) {}

    public function index(CompanyBalanceReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.company-balance.index', [
            'title' => 'Firma bakiye',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(CompanyBalanceReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
