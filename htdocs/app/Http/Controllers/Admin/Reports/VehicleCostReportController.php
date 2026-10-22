<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehicleCostReportRequest;
use App\Services\Reports\VehicleCostReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehicleCostReportController extends AdminController
{
    public function __construct(private VehicleCostReportService $reports) {}

    public function index(VehicleCostReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.vehicle-cost.index', [
            'title' => 'Araç maliyet',
            'options' => $this->reports->formOptions($request->includeOwner()),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->vehicleReport($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(VehicleCostReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        $response = $this->reports->exportVehicleCsv($filters);

        abort_if($response === null, 404);

        return $response;
    }
}
