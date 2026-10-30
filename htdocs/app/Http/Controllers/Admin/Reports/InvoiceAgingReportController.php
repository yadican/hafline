<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\ReportFormat;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\InvoiceAgingReportRequest;
use App\Services\Reports\Export\ReportExporter;
use App\Services\Reports\InvoiceAgingReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class InvoiceAgingReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-invoice-aging';

    public function __construct(
        private InvoiceAgingReportService $reports,
        private ReportExporter $exporter,
    ) {}

    public function index(InvoiceAgingReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.invoice-aging.index', [
            'title' => 'Fatura yaşlandırma',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(InvoiceAgingReportRequest $request, ReportFormat $format): Response
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->exporter->download(
            $this->reports->exportDocument($filters),
            $format,
            $request->boolean('print'),
        );
    }
}
