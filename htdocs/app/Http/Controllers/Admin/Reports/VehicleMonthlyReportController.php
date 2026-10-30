<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Enums\ReportFormat;
use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehicleMonthlyReportRequest;
use App\Services\Reports\Export\ReportExporter;
use App\Services\Reports\VehicleMonthlyReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

class VehicleMonthlyReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-vehicle-monthly';

    public function __construct(
        private VehicleMonthlyReportService $reports,
        private ReportExporter $exporter,
    ) {}

    public function index(VehicleMonthlyReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.vehicle-monthly.index', [
            'title' => 'Plaka aylık döküm',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(VehicleMonthlyReportRequest $request, ReportFormat $format): Response
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $document = $this->reports->exportDocument($filters);

        abort_if($document === null, 404);

        return $this->exporter->download($document, $format, $request->boolean('print'));
    }
}
