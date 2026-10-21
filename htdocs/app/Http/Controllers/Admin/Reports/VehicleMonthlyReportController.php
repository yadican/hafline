<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehicleMonthlyReportRequest;
use App\Services\Reports\VehicleMonthlyReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleMonthlyReportController extends AdminController
{
    public function __construct(private VehicleMonthlyReportService $reports) {}

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

    public function export(VehicleMonthlyReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
