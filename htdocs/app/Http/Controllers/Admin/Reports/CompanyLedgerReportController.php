<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\CompanyLedgerReportRequest;
use App\Services\Reports\CompanyLedgerReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CompanyLedgerReportController extends AdminController
{
    public function __construct(private CompanyLedgerReportService $reports) {}

    public function index(CompanyLedgerReportRequest $request): View
    {
        $filters = $request->filters();
        $display = $request->displayFilters();

        return view('admin.reports.company-ledger.index', [
            'title' => 'Firma cari',
            'options' => $this->reports->formOptions($display['role']),
            'filters' => $display,
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(CompanyLedgerReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
