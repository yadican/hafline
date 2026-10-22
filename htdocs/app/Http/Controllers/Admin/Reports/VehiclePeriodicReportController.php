<?php

namespace App\Http\Controllers\Admin\Reports;

use App\Http\Controllers\Admin\AdminController;
use App\Http\Requests\Admin\VehiclePeriodicReportRequest;
use App\Services\Reports\VehiclePeriodicReportService;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class VehiclePeriodicReportController extends AdminController
{
    protected ?string $permissionAbility = 'view-report-vehicle-periodic';

    public function __construct(private VehiclePeriodicReportService $reports) {}

    public function index(VehiclePeriodicReportRequest $request): View
    {
        $filters = $request->filters();

        return view('admin.reports.vehicle-periodic.index', [
            'title' => 'Plaka dönemsel',
            'options' => $this->reports->formOptions(),
            'filters' => $request->displayFilters(),
            'report' => $filters === null ? null : $this->reports->report($filters),
            'ready' => $filters !== null,
        ]);
    }

    public function export(VehiclePeriodicReportRequest $request): StreamedResponse
    {
        $filters = $request->filters();

        abort_if($filters === null, 404);

        return $this->reports->exportCsv($filters);
    }
}
