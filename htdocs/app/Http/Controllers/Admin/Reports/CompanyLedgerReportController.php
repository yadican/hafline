<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\ReportFormat;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\CompanyLedgerReportRequest;
use App\Services\Reports\CompanyLedgerReportService;
use App\Services\Reports\Export\ReportExporter;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class CompanyLedgerReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-company-ledger';

    public function __construct(
        private CompanyLedgerReportService $reports,
        private ReportExporter $exporter,
    ) {}

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

    public function export(CompanyLedgerReportRequest $request, ReportFormat $format): Response
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->exporter->download($this->reports->exportDocument($filters), $format, $request->boolean('print'));
    }
}
